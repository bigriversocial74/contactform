<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_runtime.php';
require_once dirname(__DIR__) . '/world-canvas/_locations.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

function mg_store_exit_world_transition(PDO $pdo, array $session, int $userId): ?array
{
    if ($userId <= 0 || !mg_world_canvas_table($pdo, 'user_world_positions')) return null;

    $metadata = mg_world_canvas_json_array($session['metadata_json'] ?? '');
    $storedGeo = isset($metadata['avatar_geo']) && is_array($metadata['avatar_geo']) ? $metadata['avatar_geo'] : [];
    $source = (string)($session['avatar_geo_source'] ?? ($storedGeo['source'] ?? ''));
    if ($source !== 'merchant_location_opt_in') return null;

    $lat = $session['avatar_latitude'] ?? ($storedGeo['latitude'] ?? null);
    $lng = $session['avatar_longitude'] ?? ($storedGeo['longitude'] ?? null);
    $accuracy = $session['avatar_geo_accuracy_meters'] ?? ($storedGeo['accuracy_meters'] ?? null);
    $geo = mg_world_canvas_valid_geo($lat, $lng, $accuracy, 'store_exit_merchant_location');
    if ($geo === null) return null;

    $position = mg_world_location_save_user($pdo, $userId, $geo, 'store_session');
    mg_store_log_event($pdo, $session, 'world_canvas_entered', 'Avatar entered World Canvas at merchant location', [
        'position_id' => $position['id'] ?? null,
        'geo_source' => 'store_exit_merchant_location',
    ]);
    return $position;
}

try {
    mg_rate_limit('store.exit', 'user:' . (int)$user['id'], 60, 60);
    $closed = mg_store_runtime_exit_for_customer($pdo, (int)$user['id'], 'manual');
    $worldTransition = null;

    if ($closed) {
        try {
            $worldTransition = mg_store_exit_world_transition($pdo, $closed, (int)$user['id']);
        } catch (Throwable $transitionError) {
            mg_security_log('warning', 'store_canvas.world_transition_failed', 'Store exit succeeded but World Canvas transition failed.', ['exception_class'=>$transitionError::class], (int)$user['id']);
        }
    }

    mg_ok([
        'session' => mg_store_project_session($closed),
        'active_session' => null,
        'world_transition' => $worldTransition,
    ], $closed ? 'Exited merchant store.' : 'No active store session.');
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'store_canvas.exit_failed', 'Store Canvas exit failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to exit store.', 500);
}
