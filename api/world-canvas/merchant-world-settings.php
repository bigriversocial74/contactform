<?php
/**
 * Merchant World Canvas settings endpoint.
 * Reads and updates World Canvas geo fields on the existing merchant_locations table.
 */
declare(strict_types=1);

require_once __DIR__ . '/_locations.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = $method === 'GET' ? mg_require_api_user() : mg_require_permission('merchant.locations.manage');
$pdo = mg_db();
$merchantId = (int)($user['id'] ?? 0);

try {
    if (!mg_world_location_is_merchant($pdo, $user)) {
        throw new RuntimeException('Merchant account required.');
    }

    if ($method === 'GET') {
        $requiredMissing = mg_world_location_required_missing($pdo);
        $optionalMissing = mg_world_location_optional_missing($pdo);
        $schemaReady = $requiredMissing === [];
        $locations = mg_world_location_merchant_rows($pdo, $merchantId, false);
        mg_ok([
            'schema_ready' => $schemaReady,
            'missing_columns' => $requiredMissing,
            'optional_missing_columns' => $optionalMissing,
            'locations' => array_map(static function (array $row): array {
                return [
                    'public_id' => (string)($row['public_id'] ?? ''),
                    'name' => (string)($row['name'] ?? 'Merchant location'),
                    'location_code' => (string)($row['location_code'] ?? ''),
                    'address_line1' => (string)($row['address_line1'] ?? ''),
                    'city' => (string)($row['city'] ?? ''),
                    'region' => (string)($row['region'] ?? ''),
                    'postal_code' => (string)($row['postal_code'] ?? ''),
                    'country_code' => (string)($row['country_code'] ?? ''),
                    'status' => (string)($row['status'] ?? ''),
                    'is_primary' => (int)($row['is_primary'] ?? 0),
                    'latitude' => $row['latitude'] === null ? null : (float)$row['latitude'],
                    'longitude' => $row['longitude'] === null ? null : (float)$row['longitude'],
                    'geo_accuracy_meters' => $row['geo_accuracy_meters'] === null ? null : (int)$row['geo_accuracy_meters'],
                    'geo_source' => (string)($row['geo_source'] ?? ''),
                    'world_zone_radius_meters' => (int)($row['world_zone_radius_meters'] ?? 250),
                ];
            }, $locations),
        ]);
    }

    if ($method !== 'POST') mg_fail('Method not allowed.', 405);

    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_rate_limit('world_canvas.merchant_world_settings', 'user:' . $merchantId, 40, 60);

    $geo = mg_world_location_validate(
        $input['latitude'] ?? $input['lat'] ?? null,
        $input['longitude'] ?? $input['lng'] ?? null,
        $input['accuracy_meters'] ?? $input['geo_accuracy_meters'] ?? null,
        trim((string)($input['geo_source'] ?? 'merchant_world_settings')) ?: 'merchant_world_settings'
    );
    $location = mg_world_location_save_merchant_main($pdo, $merchantId, $geo, $input);
    mg_ok(['location' => $location], 'Merchant World Canvas settings saved.');
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.merchant_world_settings_failed', 'Merchant World Canvas settings failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to save merchant World Canvas settings.', 500);
}
