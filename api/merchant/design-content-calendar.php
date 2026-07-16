<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

const MG_DESIGN_CALENDAR_TABLE = 'design_content_schedule';
const MG_DESIGN_CALENDAR_ASSET_TABLE = 'merchant_advertising_assets';
const MG_DESIGN_CALENDAR_MIGRATION = 'database/20260716_design_studio_advertising_workflow_v2.sql';
const MG_DESIGN_CALENDAR_DAYS = 30;

function mg_design_calendar_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function mg_design_calendar_column_exists(PDO $pdo, string $column): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
    $stmt->execute([MG_DESIGN_CALENDAR_TABLE, $column]);
    return (bool) $stmt->fetchColumn();
}

function mg_design_calendar_schema_ready(PDO $pdo): bool
{
    return mg_design_calendar_table_exists($pdo, MG_DESIGN_CALENDAR_TABLE)
        && mg_design_calendar_table_exists($pdo, MG_DESIGN_CALENDAR_ASSET_TABLE)
        && mg_design_calendar_column_exists($pdo, 'campaign_theme')
        && mg_design_calendar_column_exists($pdo, 'platform_copy_json');
}

function mg_design_calendar_require_schema(PDO $pdo): void
{
    if (!mg_design_calendar_schema_ready($pdo)) {
        mg_fail('Design Studio advertising setup is incomplete. Import ' . MG_DESIGN_CALENDAR_MIGRATION . ' before using this feature.', 503);
    }
}

function mg_design_calendar_date(mixed $value, ?string $fallback = null): string
{
    $raw = trim((string) $value);
    if ($raw === '' && $fallback !== null) $raw = $fallback;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ((int) ($errors['warning_count'] ?? 0) > 0 || (int) ($errors['error_count'] ?? 0) > 0)) || $date->format('Y-m-d') !== $raw) {
        mg_fail('Choose a valid calendar date.', 422);
    }
    return $date->format('Y-m-d');
}

function mg_design_calendar_time(mixed $value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') return null;
    $time = DateTimeImmutable::createFromFormat('!H:i', $raw);
    if (!$time || $time->format('H:i') !== $raw) mg_fail('Choose a valid posting time.', 422);
    return $time->format('H:i:00');
}

function mg_design_calendar_uuid(mixed $value): string
{
    $id = strtolower(trim((string) $value));
    if (preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) mg_fail('Calendar item not found.', 404);
    return $id;
}

function mg_design_calendar_choice(mixed $value, array $allowed, string $fallback): string
{
    $choice = strtolower(trim((string) $value));
    return in_array($choice, $allowed, true) ? $choice : $fallback;
}

function mg_design_calendar_format(mixed $value, string $fallback = 'square'): string
{
    return mg_design_calendar_choice($value, ['square', 'portrait', 'story'], $fallback);
}

function mg_design_calendar_layout(mixed $value, string $fallback = 'spotlight'): string
{
    return mg_design_calendar_choice($value, ['spotlight', 'split', 'bold'], $fallback);
}

function mg_design_calendar_status(mixed $value, string $fallback = 'planned'): string
{
    return mg_design_calendar_choice($value, ['planned', 'downloaded', 'posted', 'skipped'], $fallback);
}

function mg_design_calendar_theme(mixed $value, string $fallback = 'product_spotlight'): string
{
    return mg_design_calendar_choice($value, [
        'product_spotlight', 'gift_idea', 'reward_promotion', 'merchant_story', 'customer_review', 'local_support',
    ], $fallback);
}

function mg_design_calendar_text(mixed $value, int $max): string
{
    $text = trim((string) $value);
    return mb_substr($text, 0, $max);
}

function mg_design_calendar_json_object(mixed $value, int $maxBytes = 20000): ?string
{
    if ($value === null || $value === '' || $value === []) return null;
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) mg_fail('Posting copy must be a valid object.', 422);
        $value = $decoded;
    }
    if (!is_array($value) || array_is_list($value)) mg_fail('Posting copy must be a valid object.', 422);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > $maxBytes) mg_fail('Posting copy is too large.', 422);
    return $json;
}

function mg_design_calendar_owned_item(PDO $pdo, int $userId, string $publicId, bool $forUpdate = false): array
{
    $stmt = $pdo->prepare('SELECT * FROM design_content_schedule WHERE public_id = ? AND merchant_user_id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$publicId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) mg_fail('Calendar item not found.', 404);
    return $row;
}

function mg_design_calendar_product_link(array $product): string
{
    $slug = trim((string) ($product['slug'] ?? ''));
    $publicId = trim((string) ($product['public_id'] ?? ''));
    if ($slug !== '') return '/product.php?slug=' . rawurlencode($slug);
    return '/product.php?id=' . rawurlencode($publicId);
}

function mg_design_calendar_caption_bundle(array $product, string $merchantName, string $theme): array
{
    $title = mg_design_calendar_text($product['title'] ?? $product['slug'] ?? 'Local favorite', 140);
    $description = trim(strip_tags((string) ($product['description'] ?? '')));
    $description = preg_replace('/\s+/', ' ', $description) ?: '';
    $description = $description !== '' ? mb_substr($description, 0, 240) : 'A local product, service, or experience worth sharing.';
    $merchant = mg_design_calendar_text($merchantName !== '' ? $merchantName : 'this local business', 120);
    $link = mg_design_calendar_product_link($product);
    $themes = [
        'product_spotlight' => ['Product spotlight', "Take a closer look at {$title}.", 'Explore this local favorite'],
        'gift_idea' => ['Gift idea', "Make someone’s day with {$title}.", 'Send a local gift'],
        'reward_promotion' => ['Reward promotion', "Turn your next visit into something more with {$title}.", 'View the reward'],
        'merchant_story' => ['Merchant story', "Built locally and shared with care: {$title}.", 'Meet the merchant'],
        'customer_review' => ['Customer favorite', "See why customers keep coming back for {$title}.", 'See the product'],
        'local_support' => ['Support local', "Choose local with {$title} from {$merchant}.", 'Support this business'],
    ];
    [$label, $hook, $cta] = $themes[$theme] ?? $themes['product_spotlight'];
    $hashtags = '#ShopLocal #SupportLocal #Microgifter';
    $short = "{$hook} {$link}";
    $standard = "{$label}: {$hook}\n\n{$description}\n\n{$cta}: {$link}\n{$hashtags}";
    $extended = "{$label} from {$merchant}.\n\n{$hook}\n\n{$description}\n\nMicrogifter makes local products, services, experiences, and creative work easier to discover, purchase, send, and support.\n\n{$cta}: {$link}\n{$hashtags}";
    return [
        'caption_short' => mb_substr($short, 0, 280),
        'caption_standard' => mb_substr($standard, 0, 4000),
        'caption_extended' => mb_substr($extended, 0, 8000),
        'hashtags' => $hashtags,
        'product_link' => $link,
        'call_to_action' => $cta,
        'platform_copy' => [
            'general' => ['short' => $short, 'standard' => $standard, 'extended' => $extended],
            'facebook' => ['short' => $hook, 'standard' => $standard, 'extended' => $extended],
            'instagram' => ['short' => "{$hook}\n{$hashtags}", 'standard' => "{$hook}\n\n{$description}\n\n{$hashtags}", 'extended' => $extended],
            'linkedin' => ['short' => "{$label}: {$hook}", 'standard' => "{$label} from {$merchant}. {$description}\n\n{$cta}: {$link}", 'extended' => $extended],
        ],
    ];
}

function mg_design_calendar_rows(PDO $pdo, int $userId, string $from, string $to): array
{
    $stmt = $pdo->prepare(
        "SELECT s.public_id,s.scheduled_date,s.scheduled_time,s.timezone,s.post_format,s.layout_key,s.status,s.notes,
                s.campaign_theme,s.caption_short,s.caption_standard,s.caption_extended,s.hashtags,s.product_link,
                s.call_to_action,s.platform_copy_json,s.created_at,s.updated_at,
                p.public_id product_id,p.slug,p.status product_status,
                v.title,v.description,v.unit_value_cents,v.currency,
                (SELECT a.public_id FROM catalog_product_version_assets pva
                 INNER JOIN catalog_assets a ON a.id=pva.asset_id
                 WHERE pva.product_version_id=p.current_version_id AND a.asset_type='image' AND a.status='ready'
                 ORDER BY CASE LOWER(COALESCE(pva.role,'')) WHEN 'primary' THEN 0 WHEN 'cover' THEN 1 WHEN 'hero' THEN 2 WHEN 'product' THEN 3 ELSE 9 END,pva.sort_order,pva.id LIMIT 1) image_asset_id,
                (SELECT COUNT(*) FROM merchant_advertising_assets maa WHERE maa.schedule_item_id=s.id AND maa.merchant_user_id=s.merchant_user_id AND maa.status<>'removed') saved_asset_count
         FROM design_content_schedule s
         INNER JOIN catalog_products p ON p.id=s.catalog_product_id
         LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
         WHERE s.merchant_user_id=? AND s.scheduled_date BETWEEN ? AND ?
         ORDER BY s.scheduled_date ASC,COALESCE(s.scheduled_time,'23:59:59') ASC,s.id ASC"
    );
    $stmt->execute([$userId, $from, $to]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as &$item) {
        $assetId = trim((string) ($item['image_asset_id'] ?? ''));
        $item['image_url'] = $assetId !== '' ? '/api/catalog/asset-file.php?id=' . rawurlencode($assetId) : null;
        $item['platform_copy'] = json_decode((string) ($item['platform_copy_json'] ?? ''), true) ?: [];
        $item['creative_url'] = '/agent.php?view=design&mode=social'
            . '&product=' . rawurlencode((string) $item['product_id'])
            . '&format=' . rawurlencode((string) $item['post_format'])
            . '&layout=' . rawurlencode((string) $item['layout_key'])
            . '&schedule=' . rawurlencode((string) $item['public_id']);
        $item['saved_asset_count'] = (int) ($item['saved_asset_count'] ?? 0);
        unset($item['image_asset_id'], $item['platform_copy_json']);
    }
    unset($item);
    return $items;
}

function mg_design_calendar_weekdays(mixed $value): array
{
    $values = is_array($value) ? $value : [];
    $days = [];
    foreach ($values as $day) {
        $number = (int) $day;
        if ($number >= 1 && $number <= 7) $days[] = $number;
    }
    $days = array_values(array_unique($days));
    sort($days);
    return $days;
}

function mg_design_calendar_selected_weekdays(string $frequency, array $preferred): array
{
    $defaults = [
        'daily' => [1,2,3,4,5,6,7],
        'weekdays' => [1,2,3,4,5],
        'three_per_week' => [1,3,5],
        'twice_per_week' => [2,4],
        'weekly' => [3],
        'custom' => [],
    ];
    $frequency = mg_design_calendar_choice($frequency, array_keys($defaults), 'daily');
    if ($frequency === 'custom') {
        if ($preferred === []) mg_fail('Choose at least one weekday for a custom schedule.', 422);
        return $preferred;
    }
    if ($preferred === []) return $defaults[$frequency];
    if ($frequency === 'daily') return $preferred;
    if ($frequency === 'weekdays') {
        $filtered = array_values(array_intersect($preferred, [1,2,3,4,5]));
        return $filtered !== [] ? $filtered : $defaults[$frequency];
    }
    $needed = $frequency === 'three_per_week' ? 3 : ($frequency === 'twice_per_week' ? 2 : 1);
    return array_slice($preferred, 0, $needed) ?: $defaults[$frequency];
}

function mg_design_calendar_schedule_dates(DateTimeImmutable $start, array $weekdays): array
{
    $dates = [];
    for ($day = 0; $day < MG_DESIGN_CALENDAR_DAYS; $day++) {
        $date = $start->modify('+' . $day . ' days');
        if (in_array((int) $date->format('N'), $weekdays, true)) $dates[] = $date->format('Y-m-d');
    }
    return $dates;
}

function mg_design_calendar_product_map(PDO $pdo, int $userId, array $publicIds): array
{
    $placeholders = implode(',', array_fill(0, count($publicIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT p.id,p.public_id,p.slug,v.title,v.description
         FROM catalog_products p LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
         WHERE p.merchant_user_id=? AND p.status<>'archived' AND p.public_id IN ({$placeholders})"
    );
    $stmt->execute(array_merge([$userId], $publicIds));
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $product) $map[(string) $product['public_id']] = $product;
    foreach ($publicIds as $publicId) if (!isset($map[$publicId])) mg_fail('One or more selected products are unavailable.', 422);
    return $map;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_merchant_require_permission($method === 'GET' ? 'catalog.products.view' : 'catalog.products.manage');
$actorUserId = (int) ($user['id'] ?? 0);
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$merchantUserId = (int) ($workspace['merchant_user_id'] ?? $actorUserId);

if ($method === 'GET') {
    mg_rate_limit('merchant.design_calendar.read', 'user:' . $actorUserId, 180, 60);
    if (!mg_design_calendar_schema_ready($pdo)) {
        mg_ok(['items' => [], 'setup_required' => true, 'migration' => MG_DESIGN_CALENDAR_MIGRATION, 'days' => MG_DESIGN_CALENDAR_DAYS]);
    }
    $from = mg_design_calendar_date($_GET['from'] ?? '', date('Y-m-d'));
    $defaultTo = (new DateTimeImmutable($from))->modify('+' . (MG_DESIGN_CALENDAR_DAYS - 1) . ' days')->format('Y-m-d');
    $to = mg_design_calendar_date($_GET['to'] ?? '', $defaultTo);
    $span = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days;
    if ($to < $from || $span === false || $span > 92) mg_fail('Calendar ranges may contain up to 93 days.', 422);
    mg_ok(['items' => mg_design_calendar_rows($pdo, $merchantUserId, $from, $to), 'setup_required' => false, 'from' => $from, 'to' => $to, 'days' => MG_DESIGN_CALENDAR_DAYS]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
mg_rate_limit('merchant.design_calendar.write', 'user:' . $actorUserId, 80, 60);
$input = mg_input();
mg_require_csrf_for_write($input);
mg_design_calendar_require_schema($pdo);
$action = strtolower(trim((string) ($input['action'] ?? '')));
if (!in_array($action, ['generate', 'update', 'delete', 'duplicate', 'bulk_update', 'bulk_delete'], true)) mg_fail('Invalid calendar action.', 422);

try {
    if ($action === 'generate') {
        $productIds = is_array($input['product_ids'] ?? null) ? $input['product_ids'] : [];
        $productIds = array_values(array_unique(array_filter(array_map(static fn(mixed $v): string => strtolower(trim((string) $v)), $productIds))));
        if ($productIds === [] || count($productIds) > 50) mg_fail('Choose between 1 and 50 merchant products.', 422);
        $formats = array_values(array_unique(array_filter(array_map(static fn(mixed $v): string => mg_design_calendar_format($v, ''), is_array($input['formats'] ?? null) ? $input['formats'] : []))));
        $layouts = array_values(array_unique(array_filter(array_map(static fn(mixed $v): string => mg_design_calendar_layout($v, ''), is_array($input['layouts'] ?? null) ? $input['layouts'] : []))));
        $themes = array_values(array_unique(array_filter(array_map(static fn(mixed $v): string => mg_design_calendar_theme($v, ''), is_array($input['themes'] ?? null) ? $input['themes'] : []))));
        if ($formats === []) $formats = ['square'];
        if ($layouts === []) $layouts = ['spotlight'];
        if ($themes === []) $themes = ['product_spotlight'];
        $frequency = mg_design_calendar_choice($input['frequency'] ?? 'daily', ['daily','weekdays','three_per_week','twice_per_week','weekly','custom'], 'daily');
        $preferredDays = mg_design_calendar_weekdays($input['preferred_weekdays'] ?? []);
        $weekdays = mg_design_calendar_selected_weekdays($frequency, $preferredDays);
        $postingTime = mg_design_calendar_time($input['preferred_time'] ?? '');
        $timezone = mg_design_calendar_text($input['timezone'] ?? 'UTC', 64) ?: 'UTC';
        try { new DateTimeZone($timezone); } catch (Throwable) { mg_fail('Choose a valid timezone.', 422); }
        $start = mg_design_calendar_date($input['start_date'] ?? '', date('Y-m-d'));
        $startDate = new DateTimeImmutable($start);
        $end = $startDate->modify('+' . (MG_DESIGN_CALENDAR_DAYS - 1) . ' days')->format('Y-m-d');
        $dates = mg_design_calendar_schedule_dates($startDate, $weekdays);
        if ($dates === []) mg_fail('The selected frequency does not create any dates in this 30-day window.', 422);
        $products = mg_design_calendar_product_map($pdo, $merchantUserId, $productIds);
        $merchantName = trim((string) ($user['display_name'] ?? $user['full_name'] ?? 'Your business'));
        $contextJson = json_encode(['frequency'=>$frequency,'preferred_weekdays'=>$weekdays,'preferred_time'=>$postingTime,'formats'=>$formats,'layouts'=>$layouts,'themes'=>$themes], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $pdo->beginTransaction();
        if (!array_key_exists('replace', $input) || !empty($input['replace'])) {
            $delete = $pdo->prepare('DELETE FROM design_content_schedule WHERE merchant_user_id=? AND scheduled_date BETWEEN ? AND ?');
            $delete->execute([$merchantUserId, $start, $end]);
        }
        $insert = $pdo->prepare(
            "INSERT INTO design_content_schedule
             (public_id,merchant_user_id,catalog_product_id,scheduled_date,scheduled_time,timezone,post_format,layout_key,status,notes,
              campaign_theme,caption_short,caption_standard,caption_extended,hashtags,product_link,call_to_action,platform_copy_json,generation_context_json,
              created_by_user_id,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?, 'planned', NULL,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())"
        );
        $lastProductId = null;
        foreach ($dates as $index => $scheduledDate) {
            $productIndex = $index % count($productIds);
            $productPublicId = $productIds[$productIndex];
            if (count($productIds) > 1 && $productPublicId === $lastProductId) $productPublicId = $productIds[($productIndex + 1) % count($productIds)];
            $lastProductId = $productPublicId;
            $product = $products[$productPublicId];
            $format = $formats[$index % count($formats)];
            $layout = $layouts[$index % count($layouts)];
            $theme = $themes[$index % count($themes)];
            $copy = mg_design_calendar_caption_bundle($product, $merchantName, $theme);
            $insert->execute([
                mg_merchant_uuid(), $merchantUserId, (int) $product['id'], $scheduledDate, $postingTime, $timezone, $format, $layout,
                $theme, $copy['caption_short'], $copy['caption_standard'], $copy['caption_extended'], $copy['hashtags'],
                $copy['product_link'], $copy['call_to_action'], json_encode($copy['platform_copy'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $contextJson, $actorUserId,
            ]);
        }
        $pdo->commit();
        mg_audit('merchant.design_calendar_generated', 'design_content_schedule', ['start_date'=>$start,'end_date'=>$end,'frequency'=>$frequency,'weekdays'=>$weekdays,'product_count'=>count($productIds),'item_count'=>count($dates)], $actorUserId);
        mg_ok(['items'=>mg_design_calendar_rows($pdo,$merchantUserId,$start,$end),'from'=>$start,'to'=>$end,'created_count'=>count($dates)], '30-day advertising plan created.', 201);
    }

    if ($action === 'update') {
        $publicId = mg_design_calendar_uuid($input['schedule_id'] ?? '');
        $pdo->beginTransaction();
        $item = mg_design_calendar_owned_item($pdo, $merchantUserId, $publicId, true);
        $scheduledDate = array_key_exists('scheduled_date',$input) ? mg_design_calendar_date($input['scheduled_date']) : (string) $item['scheduled_date'];
        $scheduledTime = array_key_exists('scheduled_time',$input) ? mg_design_calendar_time($input['scheduled_time']) : ($item['scheduled_time'] ?? null);
        $format = array_key_exists('post_format',$input) ? mg_design_calendar_format($input['post_format']) : (string) $item['post_format'];
        $layout = array_key_exists('layout_key',$input) ? mg_design_calendar_layout($input['layout_key']) : (string) $item['layout_key'];
        $status = array_key_exists('status',$input) ? mg_design_calendar_status($input['status']) : (string) $item['status'];
        $theme = array_key_exists('campaign_theme',$input) ? mg_design_calendar_theme($input['campaign_theme']) : (string) ($item['campaign_theme'] ?? 'product_spotlight');
        $notes = array_key_exists('notes',$input) ? mg_design_calendar_text($input['notes'],500) : (string) ($item['notes'] ?? '');
        $captionShort = array_key_exists('caption_short',$input) ? mg_design_calendar_text($input['caption_short'],280) : (string) ($item['caption_short'] ?? '');
        $captionStandard = array_key_exists('caption_standard',$input) ? mg_design_calendar_text($input['caption_standard'],4000) : (string) ($item['caption_standard'] ?? '');
        $captionExtended = array_key_exists('caption_extended',$input) ? mg_design_calendar_text($input['caption_extended'],8000) : (string) ($item['caption_extended'] ?? '');
        $hashtags = array_key_exists('hashtags',$input) ? mg_design_calendar_text($input['hashtags'],500) : (string) ($item['hashtags'] ?? '');
        $productLink = array_key_exists('product_link',$input) ? mg_design_calendar_text($input['product_link'],500) : (string) ($item['product_link'] ?? '');
        $cta = array_key_exists('call_to_action',$input) ? mg_design_calendar_text($input['call_to_action'],160) : (string) ($item['call_to_action'] ?? '');
        $platformJson = array_key_exists('platform_copy',$input) ? mg_design_calendar_json_object($input['platform_copy']) : ($item['platform_copy_json'] ?? null);
        $update = $pdo->prepare(
            'UPDATE design_content_schedule SET scheduled_date=?,scheduled_time=?,post_format=?,layout_key=?,status=?,campaign_theme=?,notes=?,caption_short=?,caption_standard=?,caption_extended=?,hashtags=?,product_link=?,call_to_action=?,platform_copy_json=?,updated_at=NOW() WHERE id=?'
        );
        $update->execute([$scheduledDate,$scheduledTime,$format,$layout,$status,$theme,$notes!==''?$notes:null,$captionShort!==''?$captionShort:null,$captionStandard!==''?$captionStandard:null,$captionExtended!==''?$captionExtended:null,$hashtags!==''?$hashtags:null,$productLink!==''?$productLink:null,$cta!==''?$cta:null,$platformJson,(int)$item['id']]);
        $pdo->commit();
        mg_audit('merchant.design_calendar_updated','design_content_schedule',['schedule_id'=>$publicId,'scheduled_date'=>$scheduledDate,'post_format'=>$format,'layout_key'=>$layout,'status'=>$status,'campaign_theme'=>$theme],$actorUserId);
        mg_ok(['schedule_id'=>$publicId], 'Calendar item updated.');
    }

    if ($action === 'duplicate') {
        $publicId = mg_design_calendar_uuid($input['schedule_id'] ?? '');
        $pdo->beginTransaction();
        $item = mg_design_calendar_owned_item($pdo,$merchantUserId,$publicId,true);
        $target = array_key_exists('scheduled_date',$input) ? mg_design_calendar_date($input['scheduled_date']) : (new DateTimeImmutable((string)$item['scheduled_date']))->modify('+1 day')->format('Y-m-d');
        $newId = mg_merchant_uuid();
        $pdo->prepare(
            'INSERT INTO design_content_schedule (public_id,merchant_user_id,catalog_product_id,scheduled_date,scheduled_time,timezone,post_format,layout_key,status,notes,campaign_theme,caption_short,caption_standard,caption_extended,hashtags,product_link,call_to_action,platform_copy_json,generation_context_json,created_by_user_id,created_at,updated_at)
             SELECT ?,merchant_user_id,catalog_product_id,?,scheduled_time,timezone,post_format,layout_key,\'planned\',notes,campaign_theme,caption_short,caption_standard,caption_extended,hashtags,product_link,call_to_action,platform_copy_json,generation_context_json,?,NOW(),NOW() FROM design_content_schedule WHERE id=?'
        )->execute([$newId,$target,$actorUserId,(int)$item['id']]);
        $pdo->commit();
        mg_audit('merchant.design_calendar_duplicated','design_content_schedule',['source_schedule_id'=>$publicId,'schedule_id'=>$newId,'scheduled_date'=>$target],$actorUserId);
        mg_ok(['schedule_id'=>$newId,'scheduled_date'=>$target], 'Scheduled post duplicated.', 201);
    }

    if ($action === 'bulk_update' || $action === 'bulk_delete') {
        $ids = is_array($input['schedule_ids'] ?? null) ? $input['schedule_ids'] : [];
        $ids = array_values(array_unique(array_filter(array_map(static fn(mixed $v): string => strtolower(trim((string)$v)), $ids), static fn(string $v): bool => preg_match('/^[a-f0-9-]{36}$/',$v)===1)));
        if ($ids === [] || count($ids) > 100) mg_fail('Choose between 1 and 100 scheduled posts.',422);
        $placeholders = implode(',',array_fill(0,count($ids),'?'));
        $owned = $pdo->prepare("SELECT id,public_id FROM design_content_schedule WHERE merchant_user_id=? AND public_id IN ({$placeholders})");
        $owned->execute(array_merge([$merchantUserId],$ids));
        $rows = $owned->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)!==count($ids)) mg_fail('One or more scheduled posts are unavailable.',404);
        $dbIds = array_map(static fn(array $row): int => (int)$row['id'],$rows);
        $dbPlaceholders = implode(',',array_fill(0,count($dbIds),'?'));
        $pdo->beginTransaction();
        if ($action === 'bulk_delete') {
            $pdo->prepare("DELETE FROM design_content_schedule WHERE merchant_user_id=? AND id IN ({$dbPlaceholders})")->execute(array_merge([$merchantUserId],$dbIds));
        } else {
            $sets=[];$params=[];$auditChanges=[];
            if (array_key_exists('post_format',$input)) {$value=mg_design_calendar_format($input['post_format']);$sets[]='post_format=?';$params[]=$value;$auditChanges['post_format']=$value;}
            if (array_key_exists('layout_key',$input)) {$value=mg_design_calendar_layout($input['layout_key']);$sets[]='layout_key=?';$params[]=$value;$auditChanges['layout_key']=$value;}
            if (array_key_exists('status',$input)) {$value=mg_design_calendar_status($input['status']);$sets[]='status=?';$params[]=$value;$auditChanges['status']=$value;}
            if ($sets===[]) mg_fail('Choose a bulk change to apply.',422);
            $sql='UPDATE design_content_schedule SET '.implode(',',$sets).',updated_at=NOW() WHERE merchant_user_id=? AND id IN ('.$dbPlaceholders.')';
            $pdo->prepare($sql)->execute(array_merge($params,[$merchantUserId],$dbIds));
        }
        $pdo->commit();
        mg_audit($action==='bulk_delete'?'merchant.design_calendar_bulk_removed':'merchant.design_calendar_bulk_updated','design_content_schedule',['item_count'=>count($ids),'schedule_ids'=>$ids,'changes'=>$auditChanges??[]],$actorUserId);
        mg_ok(['schedule_ids'=>$ids,'item_count'=>count($ids)], $action==='bulk_delete'?'Scheduled posts removed.':'Scheduled posts updated.');
    }

    $publicId = mg_design_calendar_uuid($input['schedule_id'] ?? '');
    $pdo->beginTransaction();
    $item = mg_design_calendar_owned_item($pdo,$merchantUserId,$publicId,true);
    $pdo->prepare('DELETE FROM design_content_schedule WHERE id=?')->execute([(int)$item['id']]);
    $pdo->commit();
    mg_audit('merchant.design_calendar_removed','design_content_schedule',['schedule_id'=>$publicId,'scheduled_date'=>(string)$item['scheduled_date']],$actorUserId);
    mg_ok(['schedule_id'=>$publicId], 'Calendar item removed.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','merchant.design_calendar_failed','Design calendar action failed.',['action'=>$action,'exception_class'=>$error::class],$actorUserId);
    mg_fail('Unable to update the content calendar.',500);
}
