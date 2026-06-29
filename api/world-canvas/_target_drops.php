<?php
/**
 * Campaign Drops / Target Zones helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/_locations.php';

function mg_world_target_drops_ready(PDO $pdo): bool
{
    return mg_world_canvas_table($pdo, 'merchant_target_drops');
}

function mg_world_target_drop_public_id(): string
{
    try { return 'tdrop_' . bin2hex(random_bytes(16)); }
    catch (Throwable) { return 'tdrop_' . str_replace('.', '', uniqid('', true)); }
}

function mg_world_target_drop_int_or_null(mixed $value, int $min = 1, int $max = 1000000000): ?int
{
    if ($value === null || $value === '') return null;
    if (is_string($value) && !preg_match('/^-?\d+$/', trim($value))) return null;
    $int = (int)$value;
    if ($int < $min || $int > $max) return null;
    return $int;
}

function mg_world_target_drop_datetime_or_null(mixed $value): ?string
{
    $raw = trim((string)($value ?? ''));
    if ($raw === '') return null;
    try { return (new DateTimeImmutable($raw))->format('Y-m-d H:i:s'); }
    catch (Throwable) { return null; }
}

function mg_world_target_drop_bool(mixed $value, bool $default = false): int
{
    if ($value === null || $value === '') return $default ? 1 : 0;
    if (is_bool($value)) return $value ? 1 : 0;
    return in_array(strtolower(trim((string)$value)), ['1','true','yes','on'], true) ? 1 : 0;
}

function mg_world_target_drop_status(array $row): string
{
    $status = (string)($row['status'] ?? 'draft');
    if ($status === 'scheduled' && !empty($row['launch_at']) && strtotime((string)$row['launch_at']) <= time()) return 'active';
    if (in_array($status, ['active','launching','scheduled'], true) && !empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) return 'expired';
    return $status;
}

function mg_world_target_drop_row_to_payload(array $row, bool $owned): array
{
    $targetGeo = mg_world_canvas_valid_geo($row['target_latitude'] ?? null, $row['target_longitude'] ?? null, null, 'target_drop');
    $target = $targetGeo ? mg_world_canvas_geo_project($targetGeo, (string)$row['public_id'], 0, 'campaign') : ['x' => 50, 'y' => 50];
    $launchGeo = mg_world_canvas_valid_geo($row['launch_latitude'] ?? null, $row['launch_longitude'] ?? null, null, 'merchant_launch');
    $launch = $launchGeo ? mg_world_canvas_geo_project($launchGeo, (string)$row['public_id'] . ':launch', 0, 'merchant') : null;
    return [
        'id' => (string)$row['public_id'],
        'owned' => $owned,
        'merchant_user_id' => (int)($row['merchant_user_id'] ?? 0),
        'merchant_location_id' => $row['merchant_location_id'] === null ? null : (int)$row['merchant_location_id'],
        'drop_name' => (string)($row['drop_name'] ?? 'Target Drop'),
        'campaign_title' => (string)($row['campaign_title'] ?? ''),
        'campaign_public_id' => (string)($row['campaign_public_id'] ?? ''),
        'payload_type' => (string)($row['payload_type'] ?? 'reward'),
        'status' => mg_world_target_drop_status($row),
        'raw_status' => (string)($row['status'] ?? 'draft'),
        'visibility' => (string)($row['visibility'] ?? 'public'),
        'target_latitude' => (float)$row['target_latitude'],
        'target_longitude' => (float)$row['target_longitude'],
        'target_x' => $target['x'],
        'target_y' => $target['y'],
        'launch_latitude' => $row['launch_latitude'] === null ? null : (float)$row['launch_latitude'],
        'launch_longitude' => $row['launch_longitude'] === null ? null : (float)$row['launch_longitude'],
        'launch_x' => $launch['x'] ?? null,
        'launch_y' => $launch['y'] ?? null,
        'radius_meters' => (int)($row['radius_meters'] ?? 2500),
        'launch_at' => $row['launch_at'] ?? null,
        'expires_at' => $row['expires_at'] ?? null,
        'timezone' => (string)($row['timezone'] ?? ''),
        'quantity_limit' => $row['quantity_limit'] === null ? null : (int)$row['quantity_limit'],
        'claim_limit_per_user' => (int)($row['claim_limit_per_user'] ?? 1),
        'teaser_enabled' => (int)($row['teaser_enabled'] ?? 1) === 1,
        'signup_required' => (int)($row['signup_required'] ?? 1) === 1,
        'animation_type' => (string)($row['animation_type'] ?? 'gift_arc'),
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_world_target_drop_launch_location(PDO $pdo, int $merchantUserId, ?int $locationId = null): array
{
    if ($locationId !== null && $locationId > 0) {
        if (mg_world_canvas_column($pdo, 'merchant_locations', 'merchant_user_id')) {
            $rows = mg_world_canvas_rows($pdo, 'SELECT id, latitude, longitude FROM merchant_locations WHERE id=? AND merchant_user_id=? LIMIT 1', [$locationId, $merchantUserId]);
        } else {
            $rows = mg_world_canvas_rows($pdo, 'SELECT ml.id, ml.latitude, ml.longitude FROM merchant_locations ml JOIN merchant_workspaces mw ON mw.id=ml.workspace_id WHERE ml.id=? AND mw.merchant_user_id=? LIMIT 1', [$locationId, $merchantUserId]);
        }
        if ($rows) return $rows[0];
    }
    $rows = mg_world_location_merchant_rows($pdo, $merchantUserId, true);
    return $rows[0] ?? ['id' => null, 'latitude' => null, 'longitude' => null];
}

function mg_world_target_drop_list(PDO $pdo, array $user): array
{
    if (!mg_world_target_drops_ready($pdo)) return [];
    $userId = (int)($user['id'] ?? 0);
    $rows = mg_world_canvas_rows($pdo, "SELECT * FROM merchant_target_drops WHERE (merchant_user_id=? OR (visibility='public' AND status IN ('scheduled','launching','active') AND (teaser_enabled=1 OR status IN ('launching','active')))) ORDER BY FIELD(status,'launching','active','scheduled','draft','paused','completed','expired','cancelled'), launch_at IS NULL, launch_at ASC, updated_at DESC LIMIT 200", [$userId]);
    return array_map(static fn(array $row): array => mg_world_target_drop_row_to_payload($row, (int)($row['merchant_user_id'] ?? 0) === $userId), $rows);
}

function mg_world_target_drop_get_owned(PDO $pdo, int $merchantUserId, string $publicId): ?array
{
    if (!mg_world_target_drops_ready($pdo) || $merchantUserId <= 0 || $publicId === '') return null;
    $rows = mg_world_canvas_rows($pdo, 'SELECT * FROM merchant_target_drops WHERE public_id=? AND merchant_user_id=? LIMIT 1', [$publicId, $merchantUserId]);
    return $rows[0] ?? null;
}

function mg_world_target_drop_create(PDO $pdo, array $user, array $input): array
{
    if (!mg_world_target_drops_ready($pdo)) throw new RuntimeException('Campaign Drops table is not installed.');
    $merchantId = (int)($user['id'] ?? 0);
    if ($merchantId <= 0 || !mg_world_location_is_merchant($pdo, $user)) throw new RuntimeException('Merchant account required.');
    $geo = mg_world_location_validate($input['target_latitude'] ?? $input['latitude'] ?? null, $input['target_longitude'] ?? $input['longitude'] ?? null, null, 'target_drop');
    $radius = max(250, min(5000000, (int)($input['radius_meters'] ?? 2500)));
    $locationId = mg_world_target_drop_int_or_null($input['merchant_location_id'] ?? null, 1);
    $launch = mg_world_target_drop_launch_location($pdo, $merchantId, $locationId);
    $publicId = mg_world_target_drop_public_id();
    $stmt = $pdo->prepare("INSERT INTO merchant_target_drops (public_id,merchant_user_id,merchant_location_id,drop_name,payload_type,status,visibility,launch_latitude,launch_longitude,target_latitude,target_longitude,radius_meters,teaser_enabled,signup_required,claim_limit_per_user,animation_type,created_by_user_id,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,'draft','public',?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $stmt->execute([$publicId, $merchantId, $launch['id'] ?? $locationId, trim((string)($input['drop_name'] ?? 'New Target Drop')) ?: 'New Target Drop', 'reward', $launch['latitude'] ?? null, $launch['longitude'] ?? null, $geo['latitude'], $geo['longitude'], $radius, 1, 1, 1, 'gift_arc', $merchantId, json_encode(['created_from' => 'world_canvas_click'], JSON_UNESCAPED_SLASHES)]);
    $row = mg_world_target_drop_get_owned($pdo, $merchantId, $publicId);
    if (!$row) throw new RuntimeException('Unable to create target drop.');
    return mg_world_target_drop_row_to_payload($row, true);
}

function mg_world_target_drop_update(PDO $pdo, array $user, array $input, bool $publish = false): array
{
    $merchantId = (int)($user['id'] ?? 0);
    $publicId = trim((string)($input['id'] ?? $input['public_id'] ?? ''));
    $row = mg_world_target_drop_get_owned($pdo, $merchantId, $publicId);
    if (!$row) throw new RuntimeException('Target Drop not found.');
    $geo = mg_world_location_validate($input['target_latitude'] ?? $input['latitude'] ?? $row['target_latitude'], $input['target_longitude'] ?? $input['longitude'] ?? $row['target_longitude'], null, 'target_drop');
    $locationId = mg_world_target_drop_int_or_null($input['merchant_location_id'] ?? $row['merchant_location_id'] ?? null, 1);
    $launch = mg_world_target_drop_launch_location($pdo, $merchantId, $locationId);
    $visibility = in_array((string)($input['visibility'] ?? $row['visibility']), ['public','private','invite_only','audience'], true) ? (string)($input['visibility'] ?? $row['visibility']) : 'public';
    $payload = in_array((string)($input['payload_type'] ?? $row['payload_type']), ['gift','reward','audio_pack','contest','offer','announcement'], true) ? (string)($input['payload_type'] ?? $row['payload_type']) : 'reward';
    $launchAt = mg_world_target_drop_datetime_or_null($input['launch_at'] ?? $row['launch_at'] ?? null);
    $expiresAt = mg_world_target_drop_datetime_or_null($input['expires_at'] ?? $row['expires_at'] ?? null);
    $status = $publish ? ($launchAt && strtotime($launchAt) > time() ? 'scheduled' : 'launching') : (string)($row['status'] ?? 'draft');
    if (!$publish && isset($input['status']) && in_array((string)$input['status'], ['draft','scheduled','active','paused','completed','expired','cancelled'], true)) $status = (string)$input['status'];
    $stmt = $pdo->prepare("UPDATE merchant_target_drops SET merchant_location_id=?, campaign_public_id=?, campaign_title=?, drop_name=?, payload_type=?, status=?, visibility=?, launch_latitude=?, launch_longitude=?, target_latitude=?, target_longitude=?, radius_meters=?, launch_at=?, expires_at=?, timezone=?, quantity_limit=?, claim_limit_per_user=?, teaser_enabled=?, signup_required=?, animation_type=?, published_at=IF(?=1 AND published_at IS NULL,NOW(),published_at), updated_at=NOW() WHERE id=? AND merchant_user_id=?");
    $stmt->execute([$launch['id'] ?? $locationId, trim((string)($input['campaign_public_id'] ?? $row['campaign_public_id'] ?? '')) ?: null, trim((string)($input['campaign_title'] ?? $row['campaign_title'] ?? '')) ?: null, trim((string)($input['drop_name'] ?? $row['drop_name'] ?? 'Target Drop')) ?: 'Target Drop', $payload, $status, $visibility, $launch['latitude'] ?? null, $launch['longitude'] ?? null, $geo['latitude'], $geo['longitude'], max(250, min(5000000, (int)($input['radius_meters'] ?? $row['radius_meters'] ?? 2500))), $launchAt, $expiresAt, trim((string)($input['timezone'] ?? $row['timezone'] ?? '')) ?: null, mg_world_target_drop_int_or_null($input['quantity_limit'] ?? $row['quantity_limit'] ?? null, 1), max(1, min(1000, (int)($input['claim_limit_per_user'] ?? $row['claim_limit_per_user'] ?? 1))), mg_world_target_drop_bool($input['teaser_enabled'] ?? $row['teaser_enabled'] ?? 1, true), mg_world_target_drop_bool($input['signup_required'] ?? $row['signup_required'] ?? 1, true), trim((string)($input['animation_type'] ?? $row['animation_type'] ?? 'gift_arc')) ?: 'gift_arc', $publish ? 1 : 0, (int)$row['id'], $merchantId]);
    $fresh = mg_world_target_drop_get_owned($pdo, $merchantId, $publicId);
    if (!$fresh) throw new RuntimeException('Unable to update target drop.');
    return mg_world_target_drop_row_to_payload($fresh, true);
}
