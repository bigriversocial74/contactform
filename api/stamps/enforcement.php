<?php
declare(strict_types=1);

require_once __DIR__ . '/_stamps.php';

mg_require_method('GET');
$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.commerce.view') && !mg_api_user_has_permission($user, 'admin.stamps.view') && !mg_api_user_has_permission($user, 'admin.stamps.manage')) {
    mg_audit('permission_denied', 'security', ['permission' => 'admin.commerce.view'], (int)$user['id']);
    mg_security_log('warning', 'permission.denied', 'Permission denied.', ['permission' => 'admin.commerce.view'], (int)$user['id']);
    mg_fail('Permission denied.', 403);
}

mg_rate_limit('admin.stamp_enforcement.read', 'user:' . (int)$user['id'], 120, 60);
$report = mg_stamp_enforcement_report(mg_db(), dirname(__DIR__, 2));
mg_audit('stamps.enforcement_report_viewed', 'stamp_service_gate', ['summary' => $report['summary'] ?? []], (int)$user['id']);
header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok($report, 'Stamp enforcement audit loaded.');
