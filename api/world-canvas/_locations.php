<?php
/**
 * Canonical World Canvas location helpers.
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
    if ($geo === null) {
        throw new InvalidArgumentException('Valid latitude and longitude are required.');
    }
    return $geo;
}

function mg_world_location_public_id(string $prefix = 'wloc'): string
{
    try {
        return $prefix . '_' . bin2hex(random_bytes(16));
    } catch (Throwable) {
        return $prefix . '_' . str_replace('.', '', uniqid('', true));
    }
}

function mg_world_location_is_merchant(PDO $pdo, array $user): bool
{
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) return false;
    $profileType = strtolower((string)($user['profile_type'] ?? ''));
    if (in_array($profileType, ['merchant','business','store','vendor','admin','super_admin'], true)) return true;
    if (mg_world_canvas_table($pdo, 'merchant_storefronts')) {
        return mg_world_canvas_count($pdo, 'SELECT COUNT(*) FROM merchant_storefronts WHERE merchant_user_id=? LIMIT 1', [$userId]) > 0;
    }
    if (mg_world_canvas_table($pdo, 'public_profiles')) {
        return mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM public_profiles WHERE user_id=? AND profile_type IN ('merchant','business','store','vendor') LIMIT 1", [$userId]) > 0;
    }
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

function mg_world_location_main_merchant(PDO $pdo, int $merchantUserId): ?array
{
    if ($merchantUserId <= 0 || !mg_world_canvas_table($pdo, 'merchant_locations')) return null;
    $rows = mg_world_canvas_rows($pdo, "SELECT * FROM merchant_locations WHERE merchant_user_id=? AND status='active' AND location_type='main' ORDER BY is_primary DESC, updated_at DESC, id DESC LIMIT 1", [$merchantUserId]);
    if (!$rows) return null;
    $row = $rows[0];
    return mg_world_canvas_valid_geo($row['main_latitude'] ?? null, $row['main_longitude'] ?? null, $row['geo_accuracy_meters'] ?? null, (string)($row['geo_source'] ?? 'merchant_locations'));
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
    if (!mg_world_canvas_table($pdo, 'merchant_locations')) throw new RuntimeException('Merchant location table is not installed.');
    $name = trim((string)($input['location_name'] ?? 'Main Location')) ?: 'Main Location';
    $address1 = trim((string)($input['address_line1'] ?? '')) ?: null;
    $address2 = trim((string)($input['address_line2'] ?? '')) ?: null;
    $city = trim((string)($input['city'] ?? '')) ?: null;
    $region = trim((string)($input['region'] ?? '')) ?: null;
    $postal = trim((string)($input['postal_code'] ?? '')) ?: null;
    $country = strtoupper(trim((string)($input['country_code'] ?? '')));
    $country = preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null;

    $pdo->beginTransaction();
    try {
        $existing = mg_world_canvas_rows($pdo, "SELECT id, public_id FROM merchant_locations WHERE merchant_user_id=? AND location_type='main' ORDER BY is_primary DESC, id DESC LIMIT 1", [$merchantUserId]);
        if ($existing) {
            $row = $existing[0];
            $stmt = $pdo->prepare('UPDATE merchant_locations SET location_name=?, address_line1=?, address_line2=?, city=?, region=?, postal_code=?, country_code=?, main_latitude=?, main_longitude=?, geo_accuracy_meters=?, geo_source=?, is_primary=1, status=\'active\', updated_at=NOW() WHERE id=?');
            $stmt->execute([$name, $address1, $address2, $city, $region, $postal, $country, $geo['latitude'], $geo['longitude'], $geo['accuracy_meters'], $geo['source'], (int)$row['id']]);
            $publicId = (string)$row['public_id'];
        } else {
            $publicId = mg_world_location_public_id('mloc');
            $stmt = $pdo->prepare('INSERT INTO merchant_locations (public_id,merchant_user_id,location_name,location_type,address_line1,address_line2,city,region,postal_code,country_code,main_latitude,main_longitude,geo_accuracy_meters,geo_source,is_primary,status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
            $stmt->execute([$publicId, $merchantUserId, $name, 'main', $address1, $address2, $city, $region, $postal, $country, $geo['latitude'], $geo['longitude'], $geo['accuracy_meters'], $geo['source'], 1, 'active', json_encode(['saved_at' => gmdate('c')], JSON_UNESCAPED_SLASHES)]);
        }
        $pdo->commit();
        return ['id' => $publicId, 'latitude' => $geo['latitude'], 'longitude' => $geo['longitude'], 'accuracy_meters' => $geo['accuracy_meters'], 'source' => $geo['source'], 'location_name' => $name];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
