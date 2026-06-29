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
    return mg_world_location_columns_ready($pdo);
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
    if (mg_world_canvas_table($pdo, 'merchant_workspaces') && mg_world_canvas_count($pdo, "SELECT COUNT(*) FROM merchant_workspaces WHERE merchant_user_id=? AND status <> 'archived' LIMIT 1", [$userId]) > 0) return true;
    if (mg_world_canvas_table($pdo, 'merchant_storefronts') && mg_world_canvas_count($pdo, 'SELECT COUNT(*) FROM merchant_storefronts WHERE merchant_user_id=? LIMIT 1', [$userId]) > 0) return true;
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

function mg_world_location_required_missing(PDO $pdo): array
{
    $missing = [];
    if (!mg_world_canvas_table($pdo, 'merchant_locations')) return ['merchant_locations table'];
    foreach (['latitude','longitude'] as $column) {
        if (!mg_world_canvas_column($pdo, 'merchant_locations', $column)) $missing[] = 'merchant_locations.' . $column;
    }
    $hasOwner = mg_world_canvas_column($pdo, 'merchant_locations', 'merchant_user_id')
        || (mg_world_canvas_column($pdo, 'merchant_locations', 'workspace_id') && mg_world_canvas_table($pdo, 'merchant_workspaces'));
    if (!$hasOwner) $missing[] = 'merchant_locations owner link';
    return $missing;
}

function mg_world_location_optional_missing(PDO $pdo): array
{
    $missing = [];
    if (!mg_world_canvas_table($pdo, 'merchant_locations')) return $missing;
    foreach (['geo_accuracy_meters','geo_source','world_zone_radius_meters'] as $column) {
        if (!mg_world_canvas_column($pdo, 'merchant_locations', $column)) $missing[] = 'merchant_locations.' . $column;
    }
    if (!mg_world_canvas_table($pdo, 'user_world_positions')) $missing[] = 'user_world_positions table';
    return $missing;
}

function mg_world_location_missing_columns(PDO $pdo): array
{
    return array_values(array_unique(array_merge(mg_world_location_required_missing($pdo), mg_world_location_optional_missing($pdo))));
}

function mg_world_location_columns_ready(PDO $pdo): bool
{
    return mg_world_location_required_missing($pdo) === [];
}

function mg_world_location_select_expr(PDO $pdo, string $column, string $fallbackSql): string
{
    return mg_world_canvas_column($pdo, 'merchant_locations', $column) ? 'ml.' . $column : $fallbackSql . ' AS ' . $column;
}

function mg_world_location_guess_geo_from_address(array $row): ?array
{
    $direct = mg_world_canvas_valid_geo($row['latitude'] ?? null, $row['longitude'] ?? null, $row['geo_accuracy_meters'] ?? null, (string)($row['geo_source'] ?? 'merchant_locations'));
    if ($direct !== null) return $direct;

    $city = strtolower(trim((string)($row['city'] ?? '')));
    $region = strtoupper(trim((string)($row['region'] ?? '')));
    $country = strtoupper(trim((string)($row['country_code'] ?? 'US')));
    $postal = preg_replace('/[^0-9]/', '', (string)($row['postal_code'] ?? '')) ?? '';
    if ($country !== '' && $country !== 'US') return null;

    $known = [
        'az|85020' => [33.5679, -112.0555, 4200],
        'az|phoenix' => [33.4484, -112.0740, 12000],
        'az|scottsdale' => [33.4942, -111.9261, 12000],
        'az|tempe' => [33.4255, -111.9400, 10000],
        'az|mesa' => [33.4152, -111.8315, 14000],
        'az|glendale' => [33.5387, -112.1860, 14000],
        'az|chandler' => [33.3062, -111.8413, 13000],
        'az|gilbert' => [33.3528, -111.7890, 13000],
        'az|peoria' => [33.5806, -112.2374, 13000],
    ];

    $match = null;
    if ($postal !== '') {
        $zipKey = strtolower($region . '|' . substr($postal, 0, 5));
        if (isset($known[$zipKey])) $match = $known[$zipKey];
        elseif ($region === 'AZ' && str_starts_with($postal, '850')) $match = [33.4484, -112.0740, 16000];
    }
    if ($match === null && $city !== '') {
        $cityKey = strtolower($region . '|' . $city);
        if (isset($known[$cityKey])) $match = $known[$cityKey];
    }
    if ($match === null) return null;

    return mg_world_canvas_valid_geo($match[0], $match[1], $match[2], 'merchant_address_fallback');
}

function mg_world_location_backfill_merchant_geo(PDO $pdo, array $row): array
{
    if (!mg_world_location_columns_ready($pdo)) return $row;
    $geo = mg_world_location_guess_geo_from_address($row);
    if ($geo === null) return $row;
    if (mg_world_canvas_valid_geo($row['latitude'] ?? null, $row['longitude'] ?? null, null, 'existing') !== null) return $row;
    $sets = ['latitude=?', 'longitude=?'];
    $params = [$geo['latitude'], $geo['longitude']];
    if (mg_world_canvas_column($pdo, 'merchant_locations', 'geo_accuracy_meters')) { $sets[] = 'geo_accuracy_meters=?'; $params[] = $geo['accuracy_meters']; }
    if (mg_world_canvas_column($pdo, 'merchant_locations', 'geo_source')) { $sets[] = 'geo_source=?'; $params[] = $geo['source']; }
    $sets[] = 'updated_at=NOW()';
    $params[] = (int)($row['id'] ?? 0);
    if ((int)($row['id'] ?? 0) > 0) {
        try { $pdo->prepare('UPDATE merchant_locations SET ' . implode(', ', $sets) . ' WHERE id=?')->execute($params); } catch (Throwable) {}
    }
    $row['latitude'] = $geo['latitude'];
    $row['longitude'] = $geo['longitude'];
    $row['geo_accuracy_meters'] = $geo['accuracy_meters'];
    $row['geo_source'] = $geo['source'];
    return $row;
}

function mg_world_location_merchant_rows(PDO $pdo, int $merchantUserId, bool $onlyGeo = true): array
{
    if ($merchantUserId <= 0 || !mg_world_location_columns_ready($pdo)) return [];
    $geoAccuracy = mg_world_location_select_expr($pdo, 'geo_accuracy_meters', 'NULL');
    $geoSource = mg_world_location_select_expr($pdo, 'geo_source', 'NULL');
    $zoneRadius = mg_world_location_select_expr($pdo, 'world_zone_radius_meters', '250');
    $select = "ml.id, ml.public_id, {$merchantUserId} AS merchant_user_id, ml.name, ml.location_code, ml.address_line1, ml.address_line2, ml.city, ml.region, ml.postal_code, ml.country_code, ml.timezone, ml.phone, ml.status, ml.is_primary, ml.latitude, ml.longitude, {$geoAccuracy}, {$geoSource}, {$zoneRadius}, ml.updated_at";
    if (mg_world_canvas_column($pdo, 'merchant_locations', 'merchant_user_id')) {
        $rows = mg_world_canvas_rows($pdo, "SELECT {$select} FROM merchant_locations ml WHERE ml.merchant_user_id=? AND ml.status='active' ORDER BY ml.is_primary DESC, ml.name ASC, ml.id ASC", [$merchantUserId]);
    } else {
        $rows = mg_world_canvas_rows($pdo, "SELECT {$select} FROM merchant_locations ml JOIN merchant_workspaces mw ON mw.id=ml.workspace_id WHERE mw.merchant_user_id=? AND ml.status='active' ORDER BY ml.is_primary DESC, ml.name ASC, ml.id ASC", [$merchantUserId]);
    }
    $rows = array_map(static fn(array $row): array => mg_world_location_backfill_merchant_geo($pdo, $row), $rows);
    if ($onlyGeo) {
        $rows = array_values(array_filter($rows, static fn(array $row): bool => mg_world_canvas_valid_geo($row['latitude'] ?? null, $row['longitude'] ?? null, null, 'merchant_locations') !== null));
    }
    return $rows;
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

function mg_world_location_find_merchant_location(PDO $pdo, int $merchantUserId, string $locationPublicId): ?array
{
    if (mg_world_canvas_column($pdo, 'merchant_locations', 'merchant_user_id')) {
        $rows = mg_world_canvas_rows($pdo, 'SELECT id, public_id, name FROM merchant_locations WHERE public_id=? AND merchant_user_id=? LIMIT 1', [$locationPublicId, $merchantUserId]);
    } else {
        $rows = mg_world_canvas_rows($pdo, 'SELECT ml.id, ml.public_id, ml.name FROM merchant_locations ml JOIN merchant_workspaces mw ON mw.id=ml.workspace_id WHERE ml.public_id=? AND mw.merchant_user_id=? LIMIT 1', [$locationPublicId, $merchantUserId]);
    }
    return $rows[0] ?? null;
}

function mg_world_location_default_merchant_location(PDO $pdo, int $merchantUserId): ?array
{
    if (mg_world_canvas_column($pdo, 'merchant_locations', 'merchant_user_id')) {
        $rows = mg_world_canvas_rows($pdo, "SELECT id, public_id, name FROM merchant_locations WHERE merchant_user_id=? AND status='active' ORDER BY is_primary DESC, updated_at DESC, id DESC LIMIT 1", [$merchantUserId]);
    } else {
        $rows = mg_world_canvas_rows($pdo, "SELECT ml.id, ml.public_id, ml.name FROM merchant_locations ml JOIN merchant_workspaces mw ON mw.id=ml.workspace_id WHERE mw.merchant_user_id=? AND ml.status='active' ORDER BY ml.is_primary DESC, ml.updated_at DESC, ml.id DESC LIMIT 1", [$merchantUserId]);
    }
    return $rows[0] ?? null;
}

function mg_world_location_save_merchant_main(PDO $pdo, int $merchantUserId, array $geo, array $input = []): array
{
    if ($merchantUserId <= 0) throw new InvalidArgumentException('Merchant user is required.');
    $requiredMissing = mg_world_location_required_missing($pdo);
    if ($requiredMissing !== []) throw new RuntimeException('Merchant location geo columns are not installed: ' . implode(', ', $requiredMissing));
    $locationPublicId = strtolower(trim((string)($input['location_id'] ?? $input['public_id'] ?? '')));
    $zone = max(50, min(5000, (int)($input['world_zone_radius_meters'] ?? $input['zone_radius_meters'] ?? 250)));
    $row = $locationPublicId !== '' ? mg_world_location_find_merchant_location($pdo, $merchantUserId, $locationPublicId) : null;
    if (!$row) $row = mg_world_location_default_merchant_location($pdo, $merchantUserId);
    if (!$row) throw new RuntimeException('Create a merchant location in merchant-locations.php before saving World Canvas coordinates.');

    $sets = ['latitude=?', 'longitude=?'];
    $params = [$geo['latitude'], $geo['longitude']];
    if (mg_world_canvas_column($pdo, 'merchant_locations', 'geo_accuracy_meters')) {
        $sets[] = 'geo_accuracy_meters=?';
        $params[] = $geo['accuracy_meters'];
    }
    if (mg_world_canvas_column($pdo, 'merchant_locations', 'geo_source')) {
        $sets[] = 'geo_source=?';
        $params[] = $geo['source'];
    }
    if (mg_world_canvas_column($pdo, 'merchant_locations', 'world_zone_radius_meters')) {
        $sets[] = 'world_zone_radius_meters=?';
        $params[] = $zone;
    }
    $sets[] = 'updated_at=NOW()';
    $params[] = (int)$row['id'];
    $stmt = $pdo->prepare('UPDATE merchant_locations SET ' . implode(', ', $sets) . ' WHERE id=?');
    $stmt->execute($params);
    return ['id' => (string)$row['public_id'], 'location_name' => (string)($row['name'] ?? 'Merchant location'), 'latitude' => $geo['latitude'], 'longitude' => $geo['longitude'], 'accuracy_meters' => $geo['accuracy_meters'], 'source' => $geo['source'], 'world_zone_radius_meters' => $zone, 'optional_missing_columns' => mg_world_location_optional_missing($pdo)];
}
