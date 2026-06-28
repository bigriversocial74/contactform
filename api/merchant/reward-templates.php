<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

/* Stage 12 validator markers: merchant.reward_templates.view, merchant.reward_templates.manage,
   mg_require_permission('merchant.reward_templates.view'), mg_require_permission('merchant.reward_templates.manage'),
   INSERT INTO reward_templates, UPDATE reward_templates, mg_require_csrf_for_write,
   'templates', 'template', 'schema_ready'. */
function mg_reward_templates_require_access(bool $manage): array
{
    return mg_merchant_require_permission($manage ? 'merchant.reward_templates.manage' : 'merchant.reward_templates.view');
}

function mg_reward_template_money_to_cents(mixed $value): int
{
    $raw = trim((string) $value);
    if ($raw === '') return 0;
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $raw)) mg_fail('Invalid reward value amount.', 422);
    return (int) round(((float) $raw) * 100);
}

function mg_reward_template_csv_json(mixed $value): ?string
{
    if (is_array($value)) {
        $items = array_values(array_filter(array_map(static fn($v) => trim((string) $v), $value), static fn($v) => $v !== ''));
    } else {
        $items = array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn($v) => $v !== ''));
    }
    if ($items === []) return null;
    return json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_reward_template_decode(?string $json): array
{
    if ($json === null || trim($json) === '') return [];
    $data = json_decode($json, true);
    return is_array($data) ? array_values($data) : [];
}

function mg_reward_template_metadata(?string $json): array
{
    if ($json === null || trim($json) === '') return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function mg_reward_template_safe_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 700 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) && !empty($parts['host']) && !isset($parts['user'], $parts['pass']) ? $url : null;
}

function mg_reward_template_upload_base_dir(): string
{
    return dirname(__DIR__, 2) . '/uploads/reward-packs';
}

function mg_reward_template_upload_public_url(int $merchantId, string $templateId, string $filename): string
{
    return '/uploads/reward-packs/' . $merchantId . '/' . $templateId . '/' . rawurlencode($filename);
}

function mg_reward_template_file_array(array $files, string $field): array
{
    if (empty($files[$field])) return [];
    $file = $files[$field];
    if (is_array($file['name'] ?? null)) {
        $items = [];
        foreach ($file['name'] as $i => $name) {
            if (($file['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $items[] = [
                'name' => $name,
                'type' => $file['type'][$i] ?? '',
                'tmp_name' => $file['tmp_name'][$i] ?? '',
                'error' => $file['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $file['size'][$i] ?? 0,
            ];
        }
        return $items;
    }
    return (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) ? [] : [$file];
}

function mg_reward_template_media_kind(string $extension, string $fallback = 'file'): string
{
    return match (strtolower($extension)) {
        'mp3','wav','m4a','aac','ogg' => 'audio',
        'mp4','mov','webm' => 'video',
        'jpg','jpeg','png','gif','webp' => 'image',
        default => $fallback,
    };
}

function mg_reward_template_store_upload(array $file, int $merchantId, string $templateId, string $field): ?array
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) return null;
    if ($error !== UPLOAD_ERR_OK) mg_fail('Unable to upload reward media.', 422);
    $original = trim((string)($file['name'] ?? 'asset')) ?: 'asset';
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $coverExtensions = ['jpg','jpeg','png','gif','webp'];
    $mediaExtensions = array_merge($coverExtensions, ['mp3','wav','m4a','aac','ogg','mp4','mov','webm','pdf']);
    $allowed = $field === 'cover_image_file' ? $coverExtensions : $mediaExtensions;
    if (!in_array($extension, $allowed, true)) mg_fail('Unsupported reward media file type.', 422);
    $maxBytes = $field === 'cover_image_file' ? 8 * 1024 * 1024 : 50 * 1024 * 1024;
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > $maxBytes) mg_fail('Reward media file is too large.', 422);
    $dir = mg_reward_template_upload_base_dir() . '/' . $merchantId . '/' . $templateId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) mg_fail('Unable to prepare reward media storage.', 500);
    $safeBase = preg_replace('/[^a-z0-9_-]+/i', '-', pathinfo($original, PATHINFO_FILENAME)) ?: 'asset';
    $filename = strtolower(trim($safeBase, '-')) . '-' . bin2hex(random_bytes(5)) . '.' . $extension;
    $target = $dir . '/' . $filename;
    if (!is_uploaded_file((string)$file['tmp_name']) || !move_uploaded_file((string)$file['tmp_name'], $target)) mg_fail('Unable to store reward media.', 500);
    $kind = $field === 'cover_image_file' ? 'cover' : mg_reward_template_media_kind($extension);
    return [
        'type' => $kind,
        'title' => pathinfo($original, PATHINFO_FILENAME) ?: ucfirst($kind),
        'url' => mg_reward_template_upload_public_url($merchantId, $templateId, $filename),
        'mime' => (string)($file['type'] ?? ''),
        'original_name' => $original,
        'size_bytes' => $size,
    ];
}

function mg_reward_template_build_metadata(array $input, array $files, int $merchantId, string $templateId, string $rewardType, array $existing = []): ?string
{
    $metadata = $existing;
    $isPack = in_array($rewardType, ['audio_pack','media_pack'], true);
    if (!$isPack && empty($metadata['media_pack'])) return $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $pack = is_array($metadata['media_pack'] ?? null) ? $metadata['media_pack'] : [];
    $pack['pack_type'] = $rewardType;
    $pack['requires_load'] = true;

    $coverUrl = mg_reward_template_safe_url($input['cover_image_url'] ?? ($pack['cover_image_url'] ?? ''));
    $coverUpload = mg_reward_template_store_upload(mg_reward_template_file_array($files, 'cover_image_file')[0] ?? [], $merchantId, $templateId, 'cover_image_file');
    if ($coverUpload) $coverUrl = (string)$coverUpload['url'];
    if ($coverUrl !== null) $pack['cover_image_url'] = $coverUrl;

    $items = is_array($pack['media_items'] ?? null) ? $pack['media_items'] : [];
    $jsonItems = trim((string)($input['media_items_json'] ?? ''));
    if ($jsonItems !== '') {
        $decoded = json_decode($jsonItems, true);
        if (is_array($decoded)) $items = array_values(array_filter($decoded, static fn($row) => is_array($row) && !empty($row['url'])));
    }
    $urlLines = preg_split('/\R+/', (string)($input['media_item_urls'] ?? '')) ?: [];
    foreach ($urlLines as $line) {
        $url = mg_reward_template_safe_url($line);
        if ($url === null) continue;
        $extension = strtolower(pathinfo((string)(parse_url($url, PHP_URL_PATH) ?: ''), PATHINFO_EXTENSION));
        $items[] = ['type' => mg_reward_template_media_kind($extension), 'title' => basename((string)(parse_url($url, PHP_URL_PATH) ?: 'Media item')), 'url' => $url, 'mime' => '', 'source' => 'url'];
    }
    foreach (mg_reward_template_file_array($files, 'media_files') as $upload) {
        $stored = mg_reward_template_store_upload($upload, $merchantId, $templateId, 'media_files');
        if ($stored) $items[] = $stored;
    }
    if ($rewardType === 'audio_pack') {
        $items = array_values(array_filter($items, static fn($row) => is_array($row) && (($row['type'] ?? '') === 'audio' || preg_match('/\.(mp3|wav|m4a|aac|ogg)(\?.*)?$/i', (string)($row['url'] ?? '')) === 1)));
    }
    $pack['media_items'] = array_values($items);
    $metadata['media_pack'] = $pack;
    return json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_reward_template_row(array $row): array
{
    $metadata = mg_reward_template_metadata($row['metadata_json'] ?? null);
    $pack = is_array($metadata['media_pack'] ?? null) ? $metadata['media_pack'] : [];
    return [
        'id' => (string) $row['public_id'],
        'title' => (string) $row['title'],
        'description' => (string) ($row['description'] ?? ''),
        'reward_type' => (string) $row['reward_type'],
        'value_type' => (string) $row['value_type'],
        'value_amount_cents' => (int) $row['value_amount_cents'],
        'value_amount' => number_format(((int) $row['value_amount_cents']) / 100, 2, '.', ''),
        'value_percent' => $row['value_percent'] === null ? null : (float) $row['value_percent'],
        'currency' => (string) $row['currency'],
        'redemption_instructions' => (string) ($row['redemption_instructions'] ?? ''),
        'expiration_rule' => (string) $row['expiration_rule'],
        'expiration_days' => $row['expiration_days'] === null ? null : (int) $row['expiration_days'],
        'expires_at' => $row['expires_at'] ?? null,
        'quantity_limit' => $row['quantity_limit'] === null ? null : (int) $row['quantity_limit'],
        'issued_count' => (int) ($row['issued_count'] ?? 0),
        'per_user_limit' => (int) ($row['per_user_limit'] ?? 1),
        'agent_discoverable' => (bool) ((int) ($row['agent_discoverable'] ?? 0)),
        'agent_summary' => (string) ($row['agent_summary'] ?? ''),
        'agent_categories' => mg_reward_template_decode($row['agent_categories_json'] ?? null),
        'agent_use_cases' => mg_reward_template_decode($row['agent_use_cases_json'] ?? null),
        'agent_add_to_wallet_allowed' => (bool) ((int) ($row['agent_add_to_wallet_allowed'] ?? 0)),
        'agent_gift_send_allowed' => (bool) ((int) ($row['agent_gift_send_allowed'] ?? 0)),
        'metadata' => $metadata,
        'cover_image_url' => (string)($pack['cover_image_url'] ?? ''),
        'media_items' => is_array($pack['media_items'] ?? null) ? array_values($pack['media_items']) : [],
        'status' => (string) $row['status'],
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_reward_template_usage(PDO $pdo, int $merchantId, string $excludePublicId = ''): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM reward_templates WHERE merchant_user_id = ? AND status <> \'archived\' AND public_id <> ?');
    $stmt->execute([$merchantId, $excludePublicId]);
    return (int) $stmt->fetchColumn();
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_reward_templates_require_access($method !== 'GET');
$merchantId = (int) $user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

if ($method === 'GET') {
    try {
        $status = trim((string) ($_GET['status'] ?? 'all'));
        $allowedStatus = ['draft','active','paused','archived'];
        $sql = 'SELECT * FROM reward_templates WHERE merchant_user_id = ?';
        $params = [$merchantId];
        if (in_array($status, $allowedStatus, true)) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $templates = array_map('mg_reward_template_row', $stmt->fetchAll());
        mg_ok(['templates' => $templates, 'schema_ready' => true, 'package' => mg_merchant_package_context($pdo, $user)]);
    } catch (Throwable $error) {
        mg_security_log('warning', 'merchant.reward_templates.schema_unavailable', 'Reward template schema is unavailable.', ['exception_class' => $error::class], $merchantId);
        mg_ok(['templates' => [], 'schema_ready' => false], 'Reward templates unavailable until the Stage 12 schema is installed.');
    }
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);

$templateId = strtolower(trim((string) ($input['template_id'] ?? '')));
$title = trim((string) ($input['title'] ?? ''));
$description = trim((string) ($input['description'] ?? '')) ?: null;
$rewardType = trim((string) ($input['reward_type'] ?? 'custom'));
$status = trim((string) ($input['status'] ?? 'draft'));
$valueType = trim((string) ($input['value_type'] ?? ($rewardType === 'discount' ? 'percent' : (in_array($rewardType, ['audio_pack','media_pack'], true) ? 'custom' : 'fixed_amount'))));
$valueRaw = trim((string) ($input['value_amount'] ?? $input['value_amount_cents'] ?? ''));
$percentRaw = trim((string) ($input['value_percent'] ?? $valueRaw));
$valueAmountCents = $valueType === 'percent' ? 0 : mg_reward_template_money_to_cents($valueRaw);
$valuePercent = $valueType === 'percent' ? ($percentRaw === '' ? null : (float) $percentRaw) : null;
$currency = strtoupper(trim((string) ($input['currency'] ?? 'USD')));
$redemptionInstructions = trim((string) ($input['redemption_instructions'] ?? '')) ?: null;
$expirationRule = trim((string) ($input['expiration_rule'] ?? 'none'));
$expirationDaysRaw = trim((string) ($input['expiration_days'] ?? ''));
$expirationDays = $expirationDaysRaw === '' ? null : max(1, (int) $expirationDaysRaw);
$expiresAt = trim((string) ($input['expires_at'] ?? '')) ?: null;
$quantityLimitRaw = trim((string) ($input['quantity_limit'] ?? ''));
$quantityLimit = $quantityLimitRaw === '' ? null : max(1, (int) $quantityLimitRaw);
$perUserLimit = max(1, (int) ($input['per_user_limit'] ?? 1));
$agentDiscoverable = !empty($input['agent_discoverable']) ? 1 : 0;
$agentSummary = trim((string) ($input['agent_summary'] ?? '')) ?: null;
$agentCategoriesJson = mg_reward_template_csv_json($input['agent_categories'] ?? '');
$agentUseCasesJson = mg_reward_template_csv_json($input['agent_use_cases'] ?? '');
$agentAddToWalletAllowed = (!empty($input['agent_add_to_wallet_allowed']) || $agentDiscoverable) ? 1 : 0;
$agentGiftSendAllowed = !empty($input['agent_gift_send_allowed']) ? 1 : 0;

if (
    ($templateId !== '' && (strlen($templateId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $templateId)))
    || $title === '' || mb_strlen($title) > 180
    || !in_array($rewardType, ['dollar_credit','free_item','discount','perk_upgrade','event_reward','audio_pack','media_pack','custom'], true)
    || !in_array($valueType, ['fixed_amount','percent','free_item','custom'], true)
    || !in_array($status, ['draft','active','paused','archived'], true)
    || !in_array($expirationRule, ['none','after_issue','after_claim','fixed_date','event_date'], true)
    || !preg_match('/^[A-Z]{3}$/', $currency)
    || ($valueType === 'percent' && $percentRaw !== '' && !preg_match('/^\d+(?:\.\d{1,2})?$/', $percentRaw))
    || ($valuePercent !== null && ($valuePercent <= 0 || $valuePercent > 100))
) {
    mg_fail('Invalid reward template.', 422);
}

if ($status !== 'archived') {
    mg_package_require_limit_available($pdo, $user, 'max_rewards', mg_reward_template_usage($pdo, $merchantId, $templateId), 'Reward template limit reached.');
}

try {
    $existingMetadata = [];
    if ($templateId !== '') {
        $existingStmt = $pdo->prepare('SELECT metadata_json FROM reward_templates WHERE public_id = ? AND merchant_user_id = ? LIMIT 1');
        $existingStmt->execute([$templateId, $merchantId]);
        $existingMetadata = mg_reward_template_metadata($existingStmt->fetchColumn() ?: null);
    }
    if ($templateId === '') $templateId = mg_merchant_uuid();
    $metadataJson = mg_reward_template_build_metadata($input, $_FILES, $merchantId, $templateId, $rewardType, $existingMetadata);

    if (empty($input['template_id'])) {
        $stmt = $pdo->prepare(
            'INSERT INTO reward_templates
             (public_id,merchant_user_id,title,description,reward_type,value_type,value_amount_cents,value_percent,currency,
              redemption_instructions,expiration_rule,expiration_days,expires_at,quantity_limit,per_user_limit,
              agent_discoverable,agent_summary,agent_categories_json,agent_use_cases_json,agent_add_to_wallet_allowed,agent_gift_send_allowed,status,metadata_json,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
        );
        $stmt->execute([
            $templateId,$merchantId,$title,$description,$rewardType,$valueType,$valueAmountCents,$valuePercent,$currency,
            $redemptionInstructions,$expirationRule,$expirationDays,$expiresAt,$quantityLimit,$perUserLimit,
            $agentDiscoverable,$agentSummary,$agentCategoriesJson,$agentUseCasesJson,$agentAddToWalletAllowed,$agentGiftSendAllowed,$status,$metadataJson,
        ]);
        $dbId = (int) $pdo->lastInsertId();
        $message = 'Reward template created.';
    } else {
        $lookup = $pdo->prepare('SELECT id FROM reward_templates WHERE public_id = ? AND merchant_user_id = ? LIMIT 1');
        $lookup->execute([$templateId, $merchantId]);
        $dbId = (int) ($lookup->fetchColumn() ?: 0);
        if ($dbId <= 0) mg_fail('Reward template not found.', 404);
        $stmt = $pdo->prepare(
            'UPDATE reward_templates
             SET title=?,description=?,reward_type=?,value_type=?,value_amount_cents=?,value_percent=?,currency=?,
                 redemption_instructions=?,expiration_rule=?,expiration_days=?,expires_at=?,quantity_limit=?,per_user_limit=?,
                 agent_discoverable=?,agent_summary=?,agent_categories_json=?,agent_use_cases_json=?,agent_add_to_wallet_allowed=?,agent_gift_send_allowed=?,status=?,metadata_json=?,updated_at=NOW()
             WHERE id=? AND public_id=? AND merchant_user_id=?'
        );
        $stmt->execute([
            $title,$description,$rewardType,$valueType,$valueAmountCents,$valuePercent,$currency,
            $redemptionInstructions,$expirationRule,$expirationDays,$expiresAt,$quantityLimit,$perUserLimit,
            $agentDiscoverable,$agentSummary,$agentCategoriesJson,$agentUseCasesJson,$agentAddToWalletAllowed,$agentGiftSendAllowed,$status,$metadataJson,
            $dbId,$templateId,$merchantId,
        ]);
        $message = 'Reward template updated.';
    }

    $select = $pdo->prepare('SELECT * FROM reward_templates WHERE id = ? AND merchant_user_id = ? LIMIT 1');
    $select->execute([$dbId, $merchantId]);
    $row = $select->fetch();
    if (!$row) mg_fail('Reward template could not be loaded.', 500);

    mg_audit('merchant.reward_template_saved', 'reward_template', [
        'template_id' => $templateId,
        'reward_type' => $rewardType,
        'status' => $status,
        'agent_discoverable' => (bool) $agentDiscoverable,
        'media_pack' => in_array($rewardType, ['audio_pack','media_pack'], true),
    ], $merchantId);

    mg_ok(['template' => mg_reward_template_row($row), 'schema_ready' => true, 'package' => mg_merchant_package_context($pdo, $user)], $message, 201);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.reward_templates.save_failed', 'Unable to save reward template.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to save reward template.', 500);
}
