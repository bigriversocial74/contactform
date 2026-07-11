<?php
/**
 * World Canvas Runtime v2 canonical geo/persona normalizer.
 *
 * Rules:
 * - Every account may have one user persona anchored to user_world_positions.
 * - Merchant accounts additionally have one business persona per active registered
 *   merchant_locations row.
 * - Merchant personas never inherit the operator's browser/user position.
 * - Active customer avatars use their shared/current user position first, their
 *   session geo second, and the entered merchant location as a final in-store
 *   fallback. Unresolved avatars are omitted instead of being randomly placed.
 */
declare(strict_types=1);

function mg_world_v2_parse_merchant_id(array $node): int
{
    $value = (string)($node['location_key'] ?? '');
    if (preg_match('/^merchant:(\d+)$/', $value, $match) === 1) return (int)$match[1];
    if (isset($node['merchant_user_id'])) return (int)$node['merchant_user_id'];
    return 0;
}

function mg_world_v2_location_rows(PDO $pdo, int $merchantUserId): array
{
    if ($merchantUserId <= 0 || !mg_world_canvas_table($pdo, 'merchant_locations')) return [];
    if (!mg_world_canvas_column($pdo, 'merchant_locations', 'latitude') || !mg_world_canvas_column($pdo, 'merchant_locations', 'longitude')) return [];

    $accuracy = mg_world_canvas_column($pdo, 'merchant_locations', 'geo_accuracy_meters') ? 'ml.geo_accuracy_meters' : 'NULL AS geo_accuracy_meters';
    $source = mg_world_canvas_column($pdo, 'merchant_locations', 'geo_source') ? 'ml.geo_source' : "'merchant_locations' AS geo_source";
    $radius = mg_world_canvas_column($pdo, 'merchant_locations', 'world_zone_radius_meters') ? 'ml.world_zone_radius_meters' : '250 AS world_zone_radius_meters';
    $fields = "ml.id, ml.public_id, ml.name, ml.location_code, ml.address_line1, ml.city, ml.region, ml.postal_code, ml.country_code, ml.is_primary, ml.latitude, ml.longitude, {$accuracy}, {$source}, {$radius}";

    if (mg_world_canvas_column($pdo, 'merchant_locations', 'merchant_user_id')) {
        $rows = mg_world_canvas_rows($pdo, "SELECT {$fields} FROM merchant_locations ml WHERE ml.merchant_user_id=? AND ml.status='active' ORDER BY ml.is_primary DESC, ml.name ASC, ml.id ASC", [$merchantUserId]);
    } elseif (mg_world_canvas_column($pdo, 'merchant_locations', 'workspace_id') && mg_world_canvas_table($pdo, 'merchant_workspaces')) {
        $rows = mg_world_canvas_rows($pdo, "SELECT {$fields} FROM merchant_locations ml INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id WHERE mw.merchant_user_id=? AND ml.status='active' ORDER BY ml.is_primary DESC, ml.name ASC, ml.id ASC", [$merchantUserId]);
    } else {
        return [];
    }

    return array_values(array_filter($rows, static function (array $row): bool {
        return mg_world_canvas_valid_geo($row['latitude'] ?? null, $row['longitude'] ?? null, null, 'merchant_locations') !== null;
    }));
}

function mg_world_v2_primary_location(PDO $pdo, int $merchantUserId, array &$cache): ?array
{
    if (!array_key_exists($merchantUserId, $cache)) $cache[$merchantUserId] = mg_world_v2_location_rows($pdo, $merchantUserId);
    return $cache[$merchantUserId][0] ?? null;
}

function mg_world_v2_user_geo(PDO $pdo, int $userId, array &$cache): ?array
{
    if ($userId <= 0 || !mg_world_canvas_table($pdo, 'user_world_positions')) return null;
    if (array_key_exists($userId, $cache)) return $cache[$userId];
    $rows = mg_world_canvas_rows($pdo, "SELECT latitude, longitude, accuracy_meters, geo_source, position_context, updated_at FROM user_world_positions WHERE user_id=? AND is_current=1 AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY updated_at DESC, id DESC LIMIT 1", [$userId]);
    if (!$rows) return $cache[$userId] = null;
    $row = $rows[0];
    $geo = mg_world_canvas_valid_geo($row['latitude'] ?? null, $row['longitude'] ?? null, $row['accuracy_meters'] ?? null, (string)($row['geo_source'] ?? 'user_world_positions'));
    if ($geo !== null) {
        $geo['position_context'] = (string)($row['position_context'] ?? 'manual');
        $geo['updated_at'] = (string)($row['updated_at'] ?? '');
    }
    return $cache[$userId] = $geo;
}

function mg_world_v2_active_sessions(PDO $pdo): array
{
    if (!mg_world_canvas_table($pdo, 'mg_store_sessions')) return [];
    $lat = mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_latitude') ? 's.avatar_latitude' : 'NULL AS avatar_latitude';
    $lng = mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_longitude') ? 's.avatar_longitude' : 'NULL AS avatar_longitude';
    $accuracy = mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_geo_accuracy_meters') ? 's.avatar_geo_accuracy_meters' : 'NULL AS avatar_geo_accuracy_meters';
    $source = mg_world_canvas_column($pdo, 'mg_store_sessions', 'avatar_geo_source') ? 's.avatar_geo_source' : 'NULL AS avatar_geo_source';
    $rows = mg_world_canvas_rows($pdo, "SELECT s.public_id, s.customer_user_id, s.merchant_user_id, s.last_active_at, s.metadata_json, {$lat}, {$lng}, {$accuracy}, {$source}, cp.public_id customer_profile_public_id, cp.display_name customer_display_name, cp.avatar_url customer_avatar_url FROM mg_store_sessions s LEFT JOIN public_profiles cp ON cp.user_id=s.customer_user_id WHERE s.active_key IS NOT NULL AND s.status IN ('entered','active','idle') AND s.exited_at IS NULL ORDER BY s.last_active_at DESC, s.id DESC LIMIT 100");
    $bySession = [];
    foreach ($rows as $row) {
        $sessionId = (string)($row['public_id'] ?? '');
        if ($sessionId !== '') $bySession[$sessionId] = $row;
    }
    return $bySession;
}

function mg_world_v2_offset_geo(array $geo, string $seed, float $meters = 45.0): array
{
    $hash = (int)sprintf('%u', crc32($seed));
    $angle = (($hash % 360) / 180) * M_PI;
    $distance = 12.0 + (($hash % 100) / 100) * max(12.0, $meters);
    $lat = (float)$geo['latitude'];
    $lng = (float)$geo['longitude'];
    $latDelta = ($distance * cos($angle)) / 111320.0;
    $lngScale = max(0.2, cos(deg2rad($lat)));
    $lngDelta = ($distance * sin($angle)) / (111320.0 * $lngScale);
    return [
        'latitude' => round(max(-85.0, min(85.0, $lat + $latDelta)), 7),
        'longitude' => round(max(-180.0, min(180.0, $lng + $lngDelta)), 7),
        'accuracy_meters' => $geo['accuracy_meters'] ?? null,
        'source' => (string)($geo['source'] ?? 'merchant_locations') . ':nearby',
    ];
}

function mg_world_v2_apply_geo(array $node, array $geo, string $reason): array
{
    $node['geo'] = $geo;
    $node['has_geo'] = true;
    $node['geo_locked'] = true;
    $node['placement_reason'] = $reason;
    $node['latitude'] = (float)$geo['latitude'];
    $node['longitude'] = (float)$geo['longitude'];
    $point = mg_world_canvas_geo_project($geo, (string)($node['id'] ?? ''), 0, (string)($node['type'] ?? 'node'));
    $node['x'] = $point['x'];
    $node['y'] = $point['y'];
    return $node;
}

function mg_world_v2_location_address(array $location): string
{
    return trim(implode(', ', array_filter([
        trim((string)($location['address_line1'] ?? '')),
        trim((string)($location['city'] ?? '')),
        trim((string)($location['region'] ?? '')),
        trim((string)($location['postal_code'] ?? '')),
    ])));
}

function mg_world_v2_expand_merchant_node(PDO $pdo, array $node, int $merchantUserId, int $viewerUserId, array &$locationCache): array
{
    if ($merchantUserId <= 0) return [];
    if (!array_key_exists($merchantUserId, $locationCache)) $locationCache[$merchantUserId] = mg_world_v2_location_rows($pdo, $merchantUserId);
    $locations = $locationCache[$merchantUserId];
    if (!$locations) return [];

    $expanded = [];
    foreach ($locations as $index => $location) {
        $geo = mg_world_canvas_valid_geo($location['latitude'] ?? null, $location['longitude'] ?? null, $location['geo_accuracy_meters'] ?? null, (string)($location['geo_source'] ?? 'merchant_locations'));
        if ($geo === null) continue;
        $copy = $node;
        $locationId = (string)($location['public_id'] ?? ('location-' . $index));
        $locationName = trim((string)($location['name'] ?? '')) ?: (string)($node['title'] ?? 'Merchant location');
        $copy['id'] = 'merchant:' . $locationId;
        $copy['entity_key'] = 'merchant-location:' . $locationId;
        $copy['persona_key'] = 'merchant:' . $locationId;
        $copy['persona_kind'] = 'merchant';
        $copy['merchant_user_id'] = $merchantUserId;
        $copy['location_public_id'] = $locationId;
        $copy['location_key'] = 'merchant_location:' . $locationId;
        $copy['title'] = $locationName;
        $copy['subtitle'] = trim((string)($node['title'] ?? '')) ?: 'Merchant business avatar';
        $copy['meta'] = mg_world_v2_location_address($location) ?: 'Registered merchant location';
        $copy['owned'] = $merchantUserId === $viewerUserId;
        $copy['is_primary_location'] = (int)($location['is_primary'] ?? 0) === 1;
        $copy['zone_radius_meters'] = max(50, min(5000, (int)($location['world_zone_radius_meters'] ?? 250)));
        $copy = mg_world_v2_apply_geo($copy, $geo, 'registered_merchant_location');
        $expanded[] = $copy;
    }
    return $expanded;
}

function mg_world_canvas_runtime_v2(PDO $pdo, array $viewer, array $payload): array
{
    $viewerUserId = (int)($viewer['id'] ?? 0);
    $nodes = is_array($payload['nodes'] ?? null) ? $payload['nodes'] : [];
    $sessionMap = mg_world_v2_active_sessions($pdo);
    $locationCache = [];
    $userGeoCache = [];
    $normalized = [];
    $seen = [];

    foreach ($nodes as $node) {
        if (!is_array($node)) continue;
        $type = (string)($node['type'] ?? 'node');
        $merchantUserId = mg_world_v2_parse_merchant_id($node);

        if ($type === 'merchant') {
            $existingLocationId = trim((string)($node['location_public_id'] ?? ''));
            if ($existingLocationId === '' && str_starts_with((string)($node['location_key'] ?? ''), 'merchant_location:')) {
                $existingLocationId = substr((string)$node['location_key'], strlen('merchant_location:'));
            }
            if ($existingLocationId !== '' && !empty($node['geo'])) {
                $node['entity_key'] = 'merchant-location:' . $existingLocationId;
                $node['persona_key'] = 'merchant:' . $existingLocationId;
                $node['persona_kind'] = 'merchant';
                $node['location_public_id'] = $existingLocationId;
                $node['merchant_user_id'] = $merchantUserId ?: $viewerUserId;
                $key = (string)$node['entity_key'];
                if (!isset($seen[$key])) { $normalized[] = $node; $seen[$key] = true; }
                continue;
            }
            foreach (mg_world_v2_expand_merchant_node($pdo, $node, $merchantUserId, $viewerUserId, $locationCache) as $merchantNode) {
                $key = (string)$merchantNode['entity_key'];
                if (!isset($seen[$key])) { $normalized[] = $merchantNode; $seen[$key] = true; }
            }
            continue;
        }

        if ($type === 'avatar') {
            $detailId = (string)($node['detail_id'] ?? '');
            $session = $sessionMap[$detailId] ?? null;
            $customerUserId = (int)($session['customer_user_id'] ?? 0);

            if ($session !== null && $customerUserId > 0) {
                $profilePublicId = trim((string)($session['customer_profile_public_id'] ?? ''));
                $entityKey = 'user:' . $customerUserId;
                $node['id'] = 'avatar:' . ($profilePublicId !== '' ? $profilePublicId : 'user-' . $customerUserId);
                $node['entity_key'] = $entityKey;
                $node['persona_key'] = $entityKey;
                $node['persona_kind'] = 'user';
                $node['user_id'] = $customerUserId;
                $node['merchant_user_id'] = (int)($session['merchant_user_id'] ?? 0);
                $node['owned'] = $customerUserId === $viewerUserId;
                if (trim((string)($session['customer_display_name'] ?? '')) !== '' && !$node['owned']) $node['title'] = (string)$session['customer_display_name'];
                if (trim((string)($session['customer_avatar_url'] ?? '')) !== '') $node['avatar_url'] = function_exists('mg_store_avatar_url') ? mg_store_avatar_url($session['customer_avatar_url']) : $session['customer_avatar_url'];

                $geo = mg_world_v2_user_geo($pdo, $customerUserId, $userGeoCache);
                $reason = 'shared_user_position';
                if ($geo === null) {
                    $geo = mg_world_canvas_valid_geo($session['avatar_latitude'] ?? null, $session['avatar_longitude'] ?? null, $session['avatar_geo_accuracy_meters'] ?? null, (string)($session['avatar_geo_source'] ?? 'store_session'));
                    $reason = 'store_session_position';
                }
                if ($geo === null) {
                    $merchantLocation = mg_world_v2_primary_location($pdo, (int)($session['merchant_user_id'] ?? 0), $locationCache);
                    if ($merchantLocation !== null) {
                        $base = mg_world_canvas_valid_geo($merchantLocation['latitude'] ?? null, $merchantLocation['longitude'] ?? null, $merchantLocation['geo_accuracy_meters'] ?? null, (string)($merchantLocation['geo_source'] ?? 'merchant_locations'));
                        if ($base !== null) $geo = mg_world_v2_offset_geo($base, $detailId, 85.0);
                    }
                    $reason = 'entered_registered_merchant_location';
                }
                if ($geo === null) continue;
                $node = mg_world_v2_apply_geo($node, $geo, $reason);
                if (!isset($seen[$entityKey])) { $normalized[] = $node; $seen[$entityKey] = true; }
                continue;
            }

            if (!empty($node['geo'])) {
                $entityKey = !empty($node['owned']) ? 'user:' . $viewerUserId : 'avatar:' . $detailId;
                $node['entity_key'] = $entityKey;
                $node['persona_key'] = $entityKey;
                $node['persona_kind'] = 'user';
                if (!isset($seen[$entityKey])) { $normalized[] = $node; $seen[$entityKey] = true; }
            }
            continue;
        }

        if (in_array($type, ['campaign','reward','claim'], true)) {
            $location = mg_world_v2_primary_location($pdo, $merchantUserId, $locationCache);
            if ($location === null) continue;
            $baseGeo = mg_world_canvas_valid_geo($location['latitude'] ?? null, $location['longitude'] ?? null, $location['geo_accuracy_meters'] ?? null, (string)($location['geo_source'] ?? 'merchant_locations'));
            if ($baseGeo === null) continue;
            $geo = mg_world_v2_offset_geo($baseGeo, (string)($node['id'] ?? $node['detail_id'] ?? $type), $type === 'campaign' ? 120.0 : 70.0);
            $node['entity_key'] = $type . ':' . (string)($node['detail_id'] ?? $node['id'] ?? '');
            $node['merchant_user_id'] = $merchantUserId;
            $node['location_public_id'] = (string)($location['public_id'] ?? '');
            $node['location_key'] = 'merchant_location:' . (string)($location['public_id'] ?? '');
            $node = mg_world_v2_apply_geo($node, $geo, 'registered_merchant_location_activity');
            $key = (string)$node['entity_key'];
            if (!isset($seen[$key])) { $normalized[] = $node; $seen[$key] = true; }
            continue;
        }

        if (!empty($node['geo'])) {
            $key = (string)($node['entity_key'] ?? $node['id'] ?? uniqid('world-', true));
            if (!isset($seen[$key])) { $normalized[] = $node; $seen[$key] = true; }
        }
    }

    $payload['nodes'] = array_values($normalized);
    $payload['runtime'] = [
        'version' => 2,
        'geo_engine' => 'maplibre',
        'gameplay_layer' => 'threejs',
        'merchant_location_source' => 'merchant_locations',
        'user_location_source' => 'user_world_positions',
        'dual_persona' => true,
        'random_geo_fallback' => false,
    ];
    $payload['visibility']['dual_persona'] = true;
    $payload['visibility']['merchant_location_source_of_truth'] = true;
    return $payload;
}
