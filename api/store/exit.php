<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

try {
    mg_rate_limit('store.exit', 'user:' . (int)$user['id'], 60, 60);
    $closed = mg_store_exit_for_customer($pdo, (int)$user['id'], 'manual');
    mg_ok(['session' => mg_store_project_session($closed), 'active_session' => null], $closed ? 'Exited merchant store.' : 'No active store session.');
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'store_canvas.exit_failed', 'Customer exit store failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to exit merchant store.', 500);
}
