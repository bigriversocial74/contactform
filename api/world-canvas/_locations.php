<?php
/**
 * World Canvas location helpers.
 *
 * Merchant locations come from the existing merchant_locations system used by
 * merchant-locations.php. User/avatar positions remain dynamic and live in
 * user_world_positions.
 */
declare(strict_types=1);

require_once __DIR__ . '/_world.php';

function mg_world_locations_ready(PDO $pdo): bool
{
    return mg_world_canvas_table($pdo, 'merchant_locations') && mg_world_canvas_table($pdo, 'user_world_positions');
}

function mg_world_location_validate(mixed $lat, mixed $lng, mixed $accuracy = null, string $source = 'saved'): array
{
    $geo = mg_world_canvas_valid_geo($lat, $lng, $accuracy, $source);
    if ($geo === null) throw new InvalidArgumentException('Valid latitude and longitude are required.');
    return $geo;
}

function mg_world_location_public_id(string $prefix = 'wloc'): string
{
    try { return $prefix . '_' . bin2hex(random_bytes(16)); }
    catch (Throwable) { return $prefix . '_' . str_replace('.', '', uniqid('', true)); }
}

function mg_world_location_is_merchant(PDO $pdo, array $user): bool
{
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) return false;
    $profileType = strtolower((string)($user['profile_type'] ?? ''));
    if (in_array($profileType, ['merchant','business','store','vendor','admin','super_admin'], true)) return true;
    if (mg_world_canvas_table($pdo, 'merchant_storefronts') && mg_world_canvas_count($pdo, 'SELECT COUNT(*) FROM merchant_storefronts WHERE merchant_user_id=? LIMIT 1', [$userId]) > 0) return true;
    if (mg_world_canvas_table($pdo, 'merchant_workspaces') && mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM merchant_workspaces WHERE merchant_user_id=? AND status <> 'archived' LIMIT 1", [$userId]) > 0) return true;
    if (mg_world_canvas_table($pdo, 'public_profiles')) return mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM public_profiles WHERE user_id=? AND profile_type IN ('merchant','business','store','vendor') LIMIT 1", [$userId]) > 0;
    return false;
}

function mg_world_location_current_user(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0 || !mg_world_canvas_table($pdo, 'user_world_positions')) return null;
    $rows = mg_world_canvas_rows($pdo, "SELECT * FROM user_world_positions WHERE user_id=? AND is_current=1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY updated_at DESC, id DESC LIMIT 1", [$userId]);
    if (!$rows) return null;
    $row = $rows[0];
    return mg_world_canvas_valid_geo($row['latitude'] ?? null, $row['longitude'] ?? null, $row['accuracy_meters'] ?? null, (string)($row['geo_source'] ?? 'user_world_positions'));
}

function mg_world_location_columns_ready(PDO $pdo): bool
{
    return mg_world_canvas_table($pdo, 'merchant_locations')
        && mg_world_canvas_column($pdo, 'merchant_locations', 'merchant_user_id')
        && mg_world_canvas_column($pdo, 'merchant_locations', 'latitude')
        && mg_world_canvas_column($pdo, 'merchant_locations', 'longitude')
        && mg_world_canvas_column($pdo, 'merchant_locations', 'geo_accuracy_meters')
        && mg_world_canvas_column($pdo, 'merchant_locations', 'geo_source')
        && mg_world_canvas_column($pdo, 'merchant_locations', 'world_zone_radius_meters');
}

function mg_world_location_merchant_rows(PDO $pdo, int $merchantUserId, bool $onlyGeo = true): array
{
    if ($merchantUserId <= 0 || !mg_world_location_columns_ready($pdo)) return [];
    $where = "merchant_user_id=? AND status='active'" . ($onlyGeo ? ' AND latitude IS NOT NULL AND longitude IS NOT NULL' : '');
    return mg_world_canvas_rows($pdo, "SELECT id, public_id, merchant_user_id, name, location_code, address_line1, address_line2, city, region, postal_code, country_code, timezone, phone, status, is_primary, latitude, longitude, geo_accuracy_meters, geo_source, world_zone_radius_meters, updated_at FROM merchant_locations WHERE {$where} ORDER BY is_primary DESC, name ASC, id ASC", [$merchantUserId]);
}

function mg_world_location_main_merchant(PDO $pdo, int $merchantUserId): ?array
{
    $rows = mg_world_location_merchant_rows($pdo, $merchantUserId, true);
    if (!$rows) return null;
    $row = $rows[0];
    return mg_world_canvas_valid_geo($row['latitude'] ?? null, $row['longitude'] ?? null, $row['geo_accuracy_meters'] ?? null, (string)($row['geo_source'] ?? 'merchant_locations'));
}

function mg_world_location_save_user(PDO $pdo, int $userId, array $geo, string $context = 'manual'): array
{
    if ($userId <= 0) throw new InvalidArgumentException('User is required.');
    if (!mg_world_canvas_table($pdo, 'user_world_positions')) throw new RuntimeException('World user position table is not installed.');
    $context = in_array($context, ['manual','browser','ip','store_session','admin'], true) ? $context : 'manual';
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE user_world_positions SET is_current=0, updated_at=NOW() WHERE user_id=? AND is_current=1')->execute([$userId]);
        $publicId = mg_world_location_public_id('uwp');
        $stmt = $pdo->prepare('INSERT INTO user_world_positions (public_id,user_id,latitude,longitude,accuracy_meters,geo_source,position_context,is_current,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([$publicId, $userId, $geo['latitude'], $geo['longitude'], $geo['accuracy_meters'], $geo['source'], $context, 1, json_encode(['saved_at' => gmdate('c')], JSON_UNESCAPED_SLASHES)]);
        $pdo->commit();
        return ['id' => $publicId, 'latitude' => $geo['latitude'], 'longitude' => $geo['longitude'], 'accuracy_meters' => $geo['accuracy_meters'], 'source' => $geo['source'], 'context' => $context];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_world_location_save_merchant_main(PDO $pdo, int $merchantUserId, array $geo, array $input = []): array
{
    if ($merchantUserId <= 0) throw new InvalidArgumentException('Merchant user is required.');
    if (!mg_world_location_columns_ready($pdo)) throw new RuntimeException('Merchant location geo columns are not installed.');
    $locationPublicId = strtolower(trim((string)($input['location_id'] ?? $input['public_id'] ?? '')));
    $zone = (int)($input['world_zone_radius_meters'] ?? $input['zone_radius_meters'] ?? 250);
    $zone = max(50, min(5000, $zone));

    $rows = [];
    if ($locationPublicId !== '') {
        $rows = mg_world_canvas_rows($pdo, "SELECT id, public_id, name FROM merchant_locations WHERE public_id=? AND merchant_user_id=? LIMIT 1", [$locationPublicId, $merchantUserId]);
    }
    if (!$rows) {
        $rows = mg_world_canvas_rows($pdo, "SELECT id, public_id, name FROM merchant_locations WHERE merchant_user_id=? AND status='active' ORDER BY is_primary DESC, updated_at DESC, id DESC LIMIT 1", [$merchantUserId]);
    }
    if (!$rows) throw new RuntimeException('Create a merchant location in merchant-locations.php before saving World Canvas coordinates.');

    $row = $rows[0];
    $stmt = $pdo->prepare('UPDATE merchant_locations SET latitude=?, longitude=?, geo_accuracy_meters=?, geo_source=?, world_zone_radius_meters=?, updated_at=NOW() WHERE id=? AND merchant_user_id=?');
    $stmt->execute([$geo['latitude'], $geo['longitude'], $geo['accuracy_meters'], $geo['source'], $zone, (int)$row['id'], $merchantUserId]);
    return ['id' => (string)$row['public_id'], 'location_name' => (string)($row['name'] ?? 'Merchant location'), 'latitude' => $geo['latitude'], 'longitude' => $geo['longitude'], 'accuracy_meters' => $geo['accuracy_meters'], 'source' => $geo['source'], 'world_zone_radius_meters' => $zone];
}
