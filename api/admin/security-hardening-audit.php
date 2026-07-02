<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';
require_once __DIR__ . '/_security_hardening_audit.php';

mg_require_method('GET');
$user = mg_admin_system_health_require_user();
mg_admin_system_health_require_security_auditor($user);

try {
    mg_rate_limit('admin.security_hardening_audit.read', 'user:' . (int)$user['id'], 12, 60);
    $data = mg_security_hardening_audit(mg_db());
    $data['access'] = ['restricted_to_super_admin' => true];
    mg_security_log('info', 'admin.security_hardening_audit.viewed', 'Security hardening audit viewed.', [
        'status' => $data['status'],
        'critical' => $data['counts']['critical'] ?? 0,
        'warning' => $data['counts']['warning'] ?? 0,
    ], (int)$user['id']);
} catch (Throwable $error) {
    mg_security_log('error', 'admin.security_hardening_audit.failed', 'Security hardening audit request failed.', [
        'exception_class' => $error::class,
        'message' => mb_substr($error->getMessage(), 0, 240),
    ], (int)$user['id']);
    mg_fail('Unable to run security hardening audit.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
mg_ok($data, 'Security hardening audit loaded.');
