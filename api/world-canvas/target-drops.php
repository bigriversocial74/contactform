<?php
/**
 * World Canvas Campaign Drops / Target Zones API.
 */
declare(strict_types=1);

require_once __DIR__ . '/_locations.php';

function mg_world_drop_public_id(): string
{
    try {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    } catch (Throwable) {
        return substr(str_replace('.', '', uniqid('', true)) . str_repeat('0', 36), 0, 36);
    }
}

function mg_world_drop_table_ready(PDO $pdo): bool
{
    return mg_world_canvas_table($pdo, 'merchant_target_drops');
}

function mg_world_drop_first_location(PDO $pdo, int $merchantUserId): ?array
{
    $rows = mg_world_location_merchant_rows($pdo, $merchantUserId, true);
    return $rows[0] ?? null;
}

function mg_world_drop_find_location(PDO $pdo, int $merchantUserId, mixed $id): ?array
{
    $id = trim((string)$id);
    if ($id === '') return mg_world_drop_first_location($pdo, $merchantUserId);
    foreach (mg_world_location_merchant_rows($pdo, $merchantUserId, false) as $row) {
        if ((string)($row['id'] ?? '') === $id || (string)($row['public_id'] ?? '') === $id) return $row;
    }
    return mg_world_drop_first_location($pdo, $merchantUserId);
}

function mg_world_drop_status(array $row): string
{
    $status = (string)($row['status'] ?? 'draft');
    $now = time();
    $launch = !empty($row['launch_at']) ? strtotime((string)$row['launch_at']) : null;
    $expires = !empty($row['expires_at']) ? strtotime((string)$row['expires_at']) : null;
    if ($status === 'scheduled' && $launch !== false && $launch !== null && $launch <= $now) return 'active';
    if (in_array($status, ['active','launching'], true) && $expires !== false && $expires !== null && $expires <= $now) return 'expired';
    return $status;
}

function mg_world_drop_project(float $lat, float $lng, string $seed, string $type = 'drop'): array
{
    $geo = mg_world_canvas_valid_geo($lat, $lng, null, 'target_drop');
    if ($geo === null) return ['x' => 50, 'y' => 50];
    return mg_world_canvas_geo_project($geo, $seed, 0, $type);
}

function mg_world_drop_normalize(array $row): array
{
    $meta = mg_world_canvas_json_array($row['metadata_json'] ?? null);
    $status = mg_world_drop_status($row);
    $target = mg_world_drop_project((float)$row['target_latitude'], (float)$row['target_longitude'], (string)$row['public_id'], 'target_drop');
    $launch = null;
    if (isset($row['launch_latitude'], $row['launch_longitude']) && $row['launch_latitude'] !== null && $row['launch_longitude'] !== null) {
        $launch = mg_world_drop_project((float)$row['launch_latitude'], (float)$row['launch_longitude'], (string)$row['public_id'] . ':launch', 'merchant');
        $launch['latitude'] = (float)$row['launch_latitude'];
        $launch['longitude'] = (float)$row['launch_longitude'];
    }
    return [
        'public_id' => (string)$row['public_id'],
        'merchant_user_id' => (int)$row['merchant_user_id'],
        'merchant_location_id' => $row['merchant_location_id'] === null ? null : (int)$row['merchant_location_id'],
        'campaign_id' => $row['campaign_id'] === null ? null : (int)$row['campaign_id'],
        'campaign_label' => (string)($meta['campaign_label'] ?? $meta['reward_label'] ?? ''),
        'drop_name' => (string)($row['drop_name'] ?? 'Campaign Drop'),
        'drop_type' => (string)($row['drop_type'] ?? 'campaign'),
        'target_latitude' => (float)$row['target_latitude'],
        'target_longitude' => (float)$row['target_longitude'],
        'radius_meters' => (int)$row['radius_meters'],
        'status' => $status,
        'stored_status' => (string)($row['status'] ?? 'draft'),
        'visibility' => (string)($row['visibility'] ?? 'private'),
        'launch_at' => $row['launch_at'] ?? null,
        'expires_at' => $row['expires_at'] ?? null,
        'teaser_enabled' => (int)($row['teaser_enabled'] ?? 1) === 1,
        'quantity_limit' => $row['quantity_limit'] === null ? null : (int)$row['quantity_limit'],
        'claim_limit_per_user' => (int)($row['claim_limit_per_user'] ?? 1),
        'signup_required' => (int)($row['signup_required'] ?? 1) === 1,
        'animation_type' => (string)($row['animation_type'] ?? 'gift_arc'),
        'target' => ['x' => (float)$target['x'], 'y' => (float)$target['y']],
        'launch' => $launch,
        'owned' => !empty($row['_owned']),
        'metadata' => $meta,
    ];
}

function mg_world_drop_rows(PDO $pdo, int $viewerId): array
{
    if (!mg_world_drop_table_ready($pdo)) return [];
    $join = '';
    $launchSelect = 'NULL AS launch_latitude, NULL AS launch_longitude';
    if (mg_world_canvas_table($pdo, 'merchant_locations') && mg_world_canvas_column($pdo, 'merchant_locations', 'latitude')) {
        $join = ' LEFT JOIN merchant_locations ml ON ml.id=d.merchant_location_id';
        $launchSelect = 'ml.latitude AS launch_latitude, ml.longitude AS launch_longitude';
    }
    $sql = "SELECT d.*, {$launchSelect}, CASE WHEN d.merchant_user_id=? THEN 1 ELSE 0 END AS _owned FROM merchant_target_drops d{$join} WHERE d.merchant_user_id=? OR d.visibility='public' OR (d.visibility='teaser' AND d.teaser_enabled=1 AND d.status IN ('scheduled','active','launching')) ORDER BY d.updated_at DESC, d.id DESC LIMIT 150";
    return mg_world_canvas_rows($pdo, $sql, [$viewerId, $viewerId]);
}

function mg_world_drop_owned_row(PDO $pdo, int $merchantUserId, string $publicId): ?array
{
    $rows = mg_world_canvas_rows($pdo, 'SELECT * FROM merchant_target_drops WHERE public_id=? AND merchant_user_id=? LIMIT 1', [$publicId, $merchantUserId]);
    return $rows[0] ?? null;
}

function mg_world_drop_datetime(mixed $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $time = strtotime($value);
    return $time ? date('Y-m-d H:i:s', $time) : null;
}

function mg_world_drop_payload_from_input(PDO $pdo, int $merchantId, array $input, ?array $existing = null): array
{
    $lat = $input['target_latitude'] ?? $input['latitude'] ?? $input['lat'] ?? ($existing['target_latitude'] ?? null);
    $lng = $input['target_longitude'] ?? $input['longitude'] ?? $input['lng'] ?? ($existing['target_longitude'] ?? null);
    $geo = mg_world_location_validate($lat, $lng, null, 'target_drop');
    $location = mg_world_drop_find_location($pdo, $merchantId, $input['merchant_location_id'] ?? ($existing['merchant_location_id'] ?? ''));
    $meta = mg_world_canvas_json_array($existing['metadata_json'] ?? null);
    if (isset($input['campaign_label'])) $meta['campaign_label'] = trim((string)$input['campaign_label']);
    if (isset($input['reward_label'])) $meta['reward_label'] = trim((string)$input['reward_label']);
    if (isset($input['notes'])) $meta['notes'] = trim((string)$input['notes']);
    return [
        'merchant_location_id' => $location ? (int)$location['id'] : null,
        'campaign_id' => isset($input['campaign_id']) && $input['campaign_id'] !== '' ? (int)$input['campaign_id'] : ($existing['campaign_id'] ?? null),
        'drop_name' => trim((string)($input['drop_name'] ?? $existing['drop_name'] ?? 'Campaign Drop')) ?: 'Campaign Drop',
        'drop_type' => in_array(($input['drop_type'] ?? $existing['drop_type'] ?? 'campaign'), ['reward','gift','audio_pack','contest','offer','campaign'], true) ? (string)($input['drop_type'] ?? $existing['drop_type'] ?? 'campaign') : 'campaign',
        'target_latitude' => $geo['latitude'],
        'target_longitude' => $geo['longitude'],
        'radius_meters' => max(100, min(2500000, (int)($input['radius_meters'] ?? $existing['radius_meters'] ?? 1000))),
        'visibility' => in_array(($input['visibility'] ?? $existing['visibility'] ?? 'private'), ['private','teaser','public','invite_only'], true) ? (string)($input['visibility'] ?? $existing['visibility'] ?? 'private') : 'private',
        'launch_at' => array_key_exists('launch_at', $input) ? mg_world_drop_datetime($input['launch_at']) : ($existing['launch_at'] ?? null),
        'expires_at' => array_key_exists('expires_at', $input) ? mg_world_drop_datetime($input['expires_at']) : ($existing['expires_at'] ?? null),
        'teaser_enabled' => isset($input['teaser_enabled']) ? ((int)$input['teaser_enabled'] ? 1 : 0) : (int)($existing['teaser_enabled'] ?? 1),
        'quantity_limit' => isset($input['quantity_limit']) && $input['quantity_limit'] !== '' ? max(1, (int)$input['quantity_limit']) : ($existing['quantity_limit'] ?? null),
        'claim_limit_per_user' => max(1, min(100, (int)($input['claim_limit_per_user'] ?? $existing['claim_limit_per_user'] ?? 1))),
        'signup_required' => isset($input['signup_required']) ? ((int)$input['signup_required'] ? 1 : 0) : (int)($existing['signup_required'] ?? 1),
        'animation_type' => trim((string)($input['animation_type'] ?? $existing['animation_type'] ?? 'gift_arc')) ?: 'gift_arc',
        'metadata_json' => json_encode($meta, JSON_UNESCAPED_SLASHES),
    ];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$pdo = mg_db();
$user = mg_require_api_user();
$userId = (int)($user['id'] ?? 0);

try {
    if (!mg_world_drop_table_ready($pdo)) {
        mg_ok(['schema_ready' => false, 'drops' => [], 'message' => 'Import database/stage_28_world_canvas_campaign_drops.sql.']);
    }

    if ($method === 'GET') {
        $drops = array_map('mg_world_drop_normalize', mg_world_drop_rows($pdo, $userId));
        mg_ok(['schema_ready' => true, 'drops' => $drops]);
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);

    $merchant = mg_require_permission('merchant.locations.manage');
    $merchantId = (int)($merchant['id'] ?? $userId);
    if (!mg_world_location_is_merchant($pdo, $merchant)) throw new RuntimeException('Merchant account required.');
    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_rate_limit('world_canvas.target_drops', 'user:' . $merchantId, 80, 60);
    $action = trim((string)($input['action'] ?? 'create'));

    if ($action === 'create') {
        $payload = mg_world_drop_payload_from_input($pdo, $merchantId, $input);
        $publicId = mg_world_drop_public_id();
        $stmt = $pdo->prepare('INSERT INTO merchant_target_drops (public_id, merchant_user_id, merchant_location_id, campaign_id, drop_name, drop_type, target_latitude, target_longitude, radius_meters, status, visibility, launch_at, expires_at, teaser_enabled, quantity_limit, claim_limit_per_user, signup_required, animation_type, metadata_json, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([$publicId, $merchantId, $payload['merchant_location_id'], $payload['campaign_id'], $payload['drop_name'], $payload['drop_type'], $payload['target_latitude'], $payload['target_longitude'], $payload['radius_meters'], 'draft', $payload['visibility'], $payload['launch_at'], $payload['expires_at'], $payload['teaser_enabled'], $payload['quantity_limit'], $payload['claim_limit_per_user'], $payload['signup_required'], $payload['animation_type'], $payload['metadata_json']]);
        $row = mg_world_drop_owned_row($pdo, $merchantId, $publicId);
        mg_ok(['drop' => $row ? mg_world_drop_normalize($row + ['_owned' => 1]) : ['public_id' => $publicId]], 'Target drop created.');
    }

    $publicId = trim((string)($input['public_id'] ?? ''));
    if ($publicId === '') throw new RuntimeException('Target drop public_id is required.');
    $existing = mg_world_drop_owned_row($pdo, $merchantId, $publicId);
    if (!$existing) throw new RuntimeException('Target drop not found.');

    if ($action === 'update' || $action === 'save') {
        $payload = mg_world_drop_payload_from_input($pdo, $merchantId, $input, $existing);
        $stmt = $pdo->prepare('UPDATE merchant_target_drops SET merchant_location_id=?, campaign_id=?, drop_name=?, drop_type=?, target_latitude=?, target_longitude=?, radius_meters=?, visibility=?, launch_at=?, expires_at=?, teaser_enabled=?, quantity_limit=?, claim_limit_per_user=?, signup_required=?, animation_type=?, metadata_json=?, updated_at=NOW() WHERE public_id=? AND merchant_user_id=?');
        $stmt->execute([$payload['merchant_location_id'], $payload['campaign_id'], $payload['drop_name'], $payload['drop_type'], $payload['target_latitude'], $payload['target_longitude'], $payload['radius_meters'], $payload['visibility'], $payload['launch_at'], $payload['expires_at'], $payload['teaser_enabled'], $payload['quantity_limit'], $payload['claim_limit_per_user'], $payload['signup_required'], $payload['animation_type'], $payload['metadata_json'], $publicId, $merchantId]);
    } elseif ($action === 'publish' || $action === 'schedule') {
        $payload = mg_world_drop_payload_from_input($pdo, $merchantId, $input, $existing);
        $launchTime = $payload['launch_at'] ? strtotime($payload['launch_at']) : time();
        $status = $launchTime > time() ? 'scheduled' : 'active';
        $visibility = $payload['visibility'] === 'private' ? 'public' : $payload['visibility'];
        $stmt = $pdo->prepare('UPDATE merchant_target_drops SET merchant_location_id=?, campaign_id=?, drop_name=?, drop_type=?, target_latitude=?, target_longitude=?, radius_meters=?, status=?, visibility=?, launch_at=?, expires_at=?, teaser_enabled=?, quantity_limit=?, claim_limit_per_user=?, signup_required=?, animation_type=?, metadata_json=?, published_at=COALESCE(published_at,NOW()), updated_at=NOW() WHERE public_id=? AND merchant_user_id=?');
        $stmt->execute([$payload['merchant_location_id'], $payload['campaign_id'], $payload['drop_name'], $payload['drop_type'], $payload['target_latitude'], $payload['target_longitude'], $payload['radius_meters'], $status, $visibility, $payload['launch_at'], $payload['expires_at'], $payload['teaser_enabled'], $payload['quantity_limit'], $payload['claim_limit_per_user'], $payload['signup_required'], $payload['animation_type'], $payload['metadata_json'], $publicId, $merchantId]);
    } elseif (in_array($action, ['pause','cancel','complete'], true)) {
        $status = $action === 'pause' ? 'paused' : ($action === 'cancel' ? 'cancelled' : 'completed');
        $pdo->prepare('UPDATE merchant_target_drops SET status=?, updated_at=NOW() WHERE public_id=? AND merchant_user_id=?')->execute([$status, $publicId, $merchantId]);
    } else {
        throw new RuntimeException('Unsupported target drop action.');
    }

    $row = mg_world_drop_owned_row($pdo, $merchantId, $publicId);
    mg_ok(['drop' => $row ? mg_world_drop_normalize($row + ['_owned' => 1]) : ['public_id' => $publicId]], 'Target drop saved.');
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.target_drops_failed', 'World Canvas target drops failed.', ['exception_class' => $error::class], $userId);
    mg_fail('Unable to save target drop.', 500);
}
