<?php
declare(strict_types=1);

require_once __DIR__ . '/_ops_readiness.php';

mg_require_method('GET');
$user = mg_admin_system_health_require_user();

try {
    mg_rate_limit('admin.ops_readiness.read', 'user:' . (int)$user['id'], 120, 60);
    $payload = mg_admin_ops_readiness_read(mg_db());
    mg_audit('admin_ops_readiness_viewed', 'user', ['ready'=>$payload['ready'], 'missing_total'=>$payload['missing_total']], (int)$user['id']);
    mg_event('admin.ops_readiness.viewed', ['admin_user_id'=>(int)$user['id'], 'ready'=>$payload['ready'], 'missing_total'=>$payload['missing_total']], (int)$user['id']);
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok($payload, 'Admin ops readiness loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'admin.ops_readiness.failed', 'Admin ops readiness request failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to load admin ops readiness.', 500);
}
