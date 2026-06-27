<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

try {
    mg_rate_limit('store.heartbeat', 'user:' . (int)$user['id'], 180, 60);
    $session = mg_store_heartbeat($pdo, (int)$user['id']);
    mg_ok(['active_session' => mg_store_project_session($session)], $session ? 'Store session active.' : 'No active store session.');
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'store_canvas.heartbeat_failed', 'Store heartbeat failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to update store session.', 500);
}
