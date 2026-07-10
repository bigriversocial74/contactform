<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_loyalty_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_loyalty_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_loyalty_safe_url(mixed $value): string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 700 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return '';
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return '';
    $parts = parse_url($url);
    return is_array($parts)
        && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
        && !empty($parts['host'])
        && !isset($parts['user'], $parts['pass'])
        ? $url
        : '';
}

function mg_loyalty_campaign_image(array $row): string
{
    $rules = mg_loyalty_json($row['rules_json'] ?? null);
    foreach (['stamp_card_image_url', 'media_image_url', 'campaign_image_url', 'cover_image_url', 'image_url'] as $key) {
        $url = mg_loyalty_safe_url($rules[$key] ?? '');
        if ($url !== '') return $url;
    }

    $cover = mg_loyalty_safe_url($row['merchant_profile_cover_url'] ?? '');
    if ($cover !== '') return $cover;

    $rewardMeta = mg_loyalty_json($row['reward_metadata_json'] ?? null);
    $pack = is_array($rewardMeta['media_pack'] ?? null) ? $rewardMeta['media_pack'] : [];
    foreach (['campaign_image_url', 'cover_image_url', 'image_url'] as $key) {
        $url = mg_loyalty_safe_url($pack[$key] ?? $rewardMeta[$key] ?? '');
        if ($url !== '') return $url;
    }
    return '';
}

function mg_loyalty_card_public(array $row): array
{
    $rules = mg_loyalty_json($row['rules_json'] ?? null);
    $required = max(1, min(100, (int)($rules['required_count'] ?? $rules['stamp_required_count'] ?? 5)));
    $stampCount = max(0, (int)($row['stamp_count'] ?? 0));
    $slug = trim((string)($row['public_slug'] ?? ''));
    $publicId = (string)$row['campaign_public_id'];
    $merchantName = trim((string)($row['merchant_profile_display_name'] ?? ''))
        ?: (trim((string)($row['merchant_user_display_name'] ?? '')) ?: 'Microgifter merchant');
    $title = trim((string)($row['form_headline'] ?? '')) ?: (string)$row['title'];

    return [
        'id' => (string)$row['saved_public_id'],
        'campaign_id' => $publicId,
        'campaign_slug' => $slug,
        'campaign_status' => (string)($row['campaign_status'] ?? 'active'),
        'title' => $title,
        'campaign_title' => (string)$row['title'],
        'description' => (string)($row['form_description'] ?? $row['description'] ?? ''),
        'merchant_name' => $merchantName,
        'merchant_headline' => (string)($row['merchant_profile_headline'] ?? ''),
        'image_url' => mg_loyalty_campaign_image($row),
        'reward_title' => trim((string)($row['reward_template_title'] ?? '')) ?: 'Microgifter reward',
        'reward_value' => trim((string)($row['reward_template_description'] ?? '')),
        'required_count' => $required,
        'stamp_count' => min($stampCount, $required),
        'stamps_remaining' => max(0, $required - $stampCount),
        'progress_percent' => min(100, (int)round(($stampCount / $required) * 100)),
        'saved_at' => $row['saved_at'] ?? null,
        'last_stamp_at' => $row['last_stamp_at'] ?? null,
        'public_url' => '/stamp-card.php?campaign=' . rawurlencode($slug !== '' ? $slug : $publicId),
    ];
}

function mg_loyalty_find_campaign(PDO $pdo, string $campaignRef): ?array
{
    $campaignRef = strtolower(trim($campaignRef));
    if ($campaignRef === '') return null;
    $stmt = $pdo->prepare("SELECT id, public_id, public_slug, merchant_user_id, campaign_type, status
        FROM campaigns
        WHERE campaign_type='stamp_card_reward'
          AND status IN ('active','paused','ended')
          AND (public_id=? OR public_slug=?)
        LIMIT 1");
    $stmt->execute([$campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    return $campaign ?: null;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_api_user();
$pdo = mg_db();
$userId = (int)$user['id'];
$email = strtolower(trim((string)($user['email'] ?? '')));

if ($method === 'GET') {
    $campaignRef = strtolower(trim((string)($_GET['campaign'] ?? $_GET['campaign_id'] ?? '')));
    try {
        if ($campaignRef !== '') {
            $campaign = mg_loyalty_find_campaign($pdo, $campaignRef);
            if (!$campaign) mg_ok(['saved' => false, 'schema_ready' => true]);
            $stmt = $pdo->prepare('SELECT status FROM customer_saved_campaign_cards WHERE user_id=? AND campaign_id=? LIMIT 1');
            $stmt->execute([$userId, (int)$campaign['id']]);
            $status = (string)($stmt->fetchColumn() ?: '');
            mg_ok([
                'saved' => $status === 'saved',
                'status' => $status ?: 'none',
                'campaign_id' => (string)$campaign['public_id'],
                'campaign_status' => (string)$campaign['status'],
                'schema_ready' => true,
            ]);
        }

        $sql = "SELECT sc.public_id saved_public_id, sc.saved_at,
                       c.public_id campaign_public_id, c.public_slug, c.title, c.description,
                       c.form_headline, c.form_description, c.rules_json, c.status campaign_status,
                       u.display_name merchant_user_display_name,
                       pp.display_name merchant_profile_display_name,
                       pp.headline merchant_profile_headline,
                       pp.cover_url merchant_profile_cover_url,
                       rt.title reward_template_title,
                       rt.description reward_template_description,
                       rt.metadata_json reward_metadata_json,
                       (SELECT COUNT(*)
                          FROM campaign_events ce
                          INNER JOIN campaign_contacts ccx ON ccx.id=ce.contact_id
                         WHERE ce.campaign_id=c.id
                           AND ce.event_type='stamp_card.stamped'
                           AND (ccx.user_id=? OR (ccx.user_id IS NULL AND LOWER(ccx.email)=?))) stamp_count,
                       (SELECT MAX(ce2.created_at)
                          FROM campaign_events ce2
                          INNER JOIN campaign_contacts ccx2 ON ccx2.id=ce2.contact_id
                         WHERE ce2.campaign_id=c.id
                           AND ce2.event_type='stamp_card.stamped'
                           AND (ccx2.user_id=? OR (ccx2.user_id IS NULL AND LOWER(ccx2.email)=?))) last_stamp_at
                  FROM customer_saved_campaign_cards sc
                  INNER JOIN campaigns c ON c.id=sc.campaign_id
                  LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
                  LEFT JOIN users u ON u.id=c.merchant_user_id
                  LEFT JOIN public_profiles pp
                    ON pp.user_id=c.merchant_user_id
                   AND pp.status='active'
                   AND pp.visibility IN ('public','unlisted')
                 WHERE sc.user_id=?
                   AND sc.status='saved'
                   AND c.campaign_type='stamp_card_reward'
                 ORDER BY sc.updated_at DESC, sc.id DESC
                 LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $email, $userId, $email, $userId]);
        $cards = array_map('mg_loyalty_card_public', $stmt->fetchAll(PDO::FETCH_ASSOC));
        mg_ok(['cards' => $cards, 'count' => count($cards), 'schema_ready' => true]);
    } catch (Throwable $error) {
        mg_security_log('warning', 'account.loyalty_cards.unavailable', 'Saved loyalty cards unavailable.', [
            'exception_class' => $error::class,
            'message' => $error->getMessage(),
        ], $userId);
        mg_ok([
            'cards' => [],
            'count' => 0,
            'schema_ready' => false,
        ], 'Saved loyalty cards unavailable until the saved-cards schema is installed.');
    }
}

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? $input['slug'] ?? '')));
    $action = strtolower(trim((string)($input['action'] ?? 'toggle')));
    if (!in_array($action, ['save', 'unsave', 'toggle'], true) || $campaignRef === '') {
        mg_fail('Invalid saved card request.', 422);
    }

    $campaign = mg_loyalty_find_campaign($pdo, $campaignRef);
    if (!$campaign) mg_fail('Stamp card campaign is not available.', 404);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT status FROM customer_saved_campaign_cards WHERE user_id=? AND campaign_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$userId, (int)$campaign['id']]);
        $current = (string)($stmt->fetchColumn() ?: '');
        $next = $action === 'toggle'
            ? ($current === 'saved' ? 'archived' : 'saved')
            : ($action === 'unsave' ? 'archived' : 'saved');

        if ($next === 'saved' && (string)$campaign['status'] !== 'active') {
            if ($pdo->inTransaction()) $pdo->rollBack();
            mg_fail('Only active Stamp Card campaigns can be saved.', 409);
        }

        $metadata = json_encode([
            'campaign_type' => 'stamp_card_reward',
            'source' => 'public_stamp_card',
            'updated_from' => 'loyalty_card_toggle',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $upsert = $pdo->prepare("INSERT INTO customer_saved_campaign_cards
            (public_id,user_id,campaign_id,merchant_user_id,status,metadata_json,saved_at,updated_at)
            VALUES (?,?,?,?,?,?,NOW(),NOW())
            ON DUPLICATE KEY UPDATE
              status=VALUES(status),
              metadata_json=VALUES(metadata_json),
              updated_at=NOW(),
              saved_at=IF(VALUES(status)='saved', NOW(), saved_at)");
        $upsert->execute([
            mg_loyalty_uuid(),
            $userId,
            (int)$campaign['id'],
            (int)$campaign['merchant_user_id'],
            $next,
            $metadata,
        ]);
        $pdo->commit();

        mg_ok([
            'saved' => $next === 'saved',
            'status' => $next,
            'campaign_id' => (string)$campaign['public_id'],
            'campaign_status' => (string)$campaign['status'],
            'schema_ready' => true,
        ], $next === 'saved' ? 'Loyalty card saved.' : 'Loyalty card removed.');
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        mg_security_log('error', 'account.loyalty_cards.toggle_failed', 'Unable to update saved loyalty card.', [
            'exception_class' => $error::class,
            'message' => $error->getMessage(),
        ], $userId);
        mg_fail('Unable to update saved loyalty card. Import the saved loyalty cards SQL if this is a fresh deploy.', 500);
    }
}

mg_fail('Method not allowed.', 405);