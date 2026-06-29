<?php
/**
 * Save current user/avatar World Canvas position.
 */
declare(strict_types=1);

require_once __DIR__ . '/_locations.php';

mg_require_method('POST');
$user = mg_require_api_user();
$pdo = mg_db();

try {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    mg_rate_limit('world_canvas.user_position', 'user:' . (int)$user['id'], 30, 60);

    $geo = mg_world_location_validate(
        $input['latitude'] ?? $input['lat'] ?? null,
        $input['longitude'] ?? $input['lng'] ?? null,
        $input['accuracy_meters'] ?? $input['accuracy'] ?? null,
        trim((string)($input['geo_source'] ?? 'browser')) ?: 'browser'
    );
    $context = trim((string)($input['position_context'] ?? 'browser')) ?: 'browser';
    $position = mg_world_location_save_user($pdo, (int)$user['id'], $geo, $context);
    mg_ok(['position' => $position], 'World Canvas user position saved.');
} catch (InvalidArgumentException|RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'world_canvas.user_position_failed', 'World Canvas user position save failed.', ['exception_class' => $error::class], (int)($user['id'] ?? 0));
    mg_fail('Unable to save World Canvas user position.', 500);
}
