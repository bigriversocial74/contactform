<?php
/**
 * Save merchant MAIN location for World Canvas placement.
 */
declare(strict_types=1);

require_once __DIR__ . '/_locations.php';

mg_require_method('POST');
$user = mg_require_api_user();
$pdo = mg_db();

try {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_rate_limit('world_canvas.merchant_main_location', 'user:' . (int)$user['id'], 20, 60);

    if (!mg_world_location_is_merchant($pdo, $user)) {
        throw new RuntimeException('Only merchant accounts can save a merchant MAIN location.');
    }

    $geo = mg_world_location_validate(
        $input['main_latitude'] ?? $input['latitude'] ?? $input['lat'] ?? null,
        $input['main_longitude'] ?? $input['longitude'] ?? $input['lng'] ?? null,
        $input['geo_accuracy_meters'] ?? $input['accuracy_meters'] ?? $input['accuracy'] ?? null,
        trim((string)($input['geo_source'] ?? 'merchant_main_location')) ?: 'merchant_main_location'
    );

    $location = mg_world_location_save_merchant_main($pdo, (int)$user['id'], $geo, $input);
    mg_ok(['location' => $location], 'Merchant MAIN location saved.');
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.merchant_main_location_failed', 'World Canvas merchant MAIN location save failed.', ['exception_class' => $error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to save merchant MAIN location.', 500);
}
