<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';
require_once __DIR__ . '/_system_sql_diagnostics.php';

mg_require_method('GET');
$user = mg_admin_system_health_require_user();

try {
    mg_rate_limit('admin.system_sql_diagnostics.read', 'user:' . (int)$user['id'], 60, 60);
    $pdo = mg_db();
    $data = mg_system_sql_diagnostics($pdo);
    mg_security_log('info', 'admin.system_sql_diagnostics.viewed', 'System SQL diagnostics viewed.', [
        'status' => $data['status'],
        'critical_findings' => $data['counts']['critical_findings'] ?? 0,
        'warning_findings' => $data['counts']['warning_findings'] ?? 0,
    ], (int)$user['id']);
} catch (Throwable $error) {
    mg_security_log('error', 'admin.system_sql_diagnostics.failed', 'System SQL diagnostics request failed.', ['exception_class' => $error::class], (int)$user['id']);
    mg_fail('Unable to run system SQL diagnostics.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
mg_ok($data, 'System SQL diagnostics loaded.');
