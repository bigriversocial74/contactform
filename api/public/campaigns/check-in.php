<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/campaign-types.php';

function mg_check_in_number(mixed $value): ?float
{
    if ($value === null || $value === '' || !is_numeric($value)) return null;
    return (float)$value;
}

function mg_check_in_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_check_in_distance_meters(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earth = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * (sin($dLon / 2) ** 2);
    return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
}

function mg_public_campaign_engage_preprocess_input(PDO $pdo, array $input): array
{
    $campaignRef = strtolower(trim((string)($input['campaign_id'] ?? $input['campaign'] ?? $input['slug'] ?? '')));
    $entry = $input['entry'] ?? [];
    if (!is_array($entry)) $entry = [];

    $lat = mg_check_in_number($entry['latitude'] ?? $input['latitude'] ?? null);
    $lng = mg_check_in_number($entry['longitude'] ?? $input['longitude'] ?? null);
    $accuracy = mg_check_in_number($entry['accuracy_meters'] ?? $input['accuracy_meters'] ?? null);

    if ($campaignRef === '') mg_fail('Check-in campaign is required.', 422);
    if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        mg_fail('Allow location access so Microgifter can match you to a registered merchant location.', 422);
    }
    if ($accuracy !== null && $accuracy > 2500) {
        mg_fail('Your location accuracy is too broad for check-in. Move closer to the location and try again.', 422);
    }

    $stmt = $pdo->prepare("SELECT id, public_id, public_slug, merchant_user_id, campaign_type, title FROM campaigns WHERE status='active' AND (public_id=? OR public_slug=?) LIMIT 1");
    $stmt->execute([$campaignRef, $campaignRef]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign || (string)$campaign['campaign_type'] !== 'check_in_reward') {
        mg_fail('Check-in reward campaign is not available.', 404);
    }

    $locations = $pdo->prepare("SELECT public_id,name,location_code,address_line1,city,region,postal_code,is_primary,metadata_json FROM merchant_locations WHERE merchant_user_id=? AND status='active' ORDER BY is_primary DESC,name ASC,id ASC");
    $locations->execute([(int)$campaign['merchant_user_id']]);
    $nearest = null;
    $geoEnabledCount = 0;
    foreach (($locations->fetchAll(PDO::FETCH_ASSOC) ?: []) as $location) {
        $metadata = mg_check_in_json($location['metadata_json'] ?? null);
        $locLat = mg_check_in_number($metadata['latitude'] ?? null);
        $locLng = mg_check_in_number($metadata['longitude'] ?? null);
        if ($locLat === null || $locLng === null) continue;
        $geoEnabledCount++;
        $radius = (int)($metadata['check_in_radius_meters'] ?? 150);
        $radius = max(25, min(5000, $radius));
        $distance = mg_check_in_distance_meters($lat, $lng, $locLat, $locLng);
        $candidate = [
            'id' => (string)$location['public_id'],
            'name' => (string)$location['name'],
            'location_code' => (string)$location['location_code'],
            'address' => trim(implode(', ', array_filter([(string)($location['address_line1'] ?? ''), (string)($location['city'] ?? ''), (string)($location['region'] ?? ''), (string)($location['postal_code'] ?? '')]))),
            'distance_meters' => round($distance, 2),
            'radius_meters' => $radius,
            'is_primary' => (bool)((int)($location['is_primary'] ?? 0)),
        ];
        if ($nearest === null || $candidate['distance_meters'] < $nearest['distance_meters']) $nearest = $candidate;
    }

    if ($geoEnabledCount === 0) {
        mg_fail('This merchant does not have a geo-enabled check-in location yet.', 409);
    }
    if (!$nearest || (float)$nearest['distance_meters'] > (float)$nearest['radius_meters']) {
        mg_fail($nearest ? ('You are not close enough to check in at ' . $nearest['name'] . '.') : 'No matching merchant location was found.', 409);
    }

    $entry['latitude'] = round($lat, 7);
    $entry['longitude'] = round($lng, 7);
    if ($accuracy !== null) $entry['accuracy_meters'] = round($accuracy, 2);
    $entry['matched_location'] = $nearest;
    $entry['check_in_verified'] = true;
    $entry['checked_in_at'] = gmdate('c');
    $entry['geo_match_method'] = 'browser_geolocation_haversine';
    $input['entry'] = $entry;
    $input['matched_location_id'] = $nearest['id'];
    $input['matched_location_name'] = $nearest['name'];
    return $input;
}

require __DIR__ . '/engage.php';
