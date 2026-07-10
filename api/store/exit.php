<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_runtime.php';
require_once __DIR__ . '/_world_transition.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

try {
    mg_rate_limit('store.exit', 'user:' . (int)$user['id'], 60, 60);
    $closed = mg_store_runtime_exit_for_customer($pdo, (int)$user['id'], 'manual');
    $worldTransition = null;

    if ($closed) {
        try {
            $worldTransition = mg_store_world_transition_from_session($pdo, $closed, (int)$user['id'], 'manual');
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
