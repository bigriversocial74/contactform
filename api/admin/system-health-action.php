<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';
require_once __DIR__ . '/_system_health_security.php';
require_once __DIR__ . '/_system_health_actions.php';
require_once __DIR__ . '/_migration_plan.php';

mg_require_method('POST');
$user = mg_admin_system_health_require_user();
mg_admin_system_health_require_manager($user);
$input = mg_input();
mg_require_csrf_for_write($input);
mg_rate_limit('admin.system_health.action', 'user:' . (int)$user['id'], 12, 300);

$action = strtolower(trim((string)($input['action'] ?? '')));
if (!in_array($action, ['verify_storage', 'retry_notifications', 'clean_uploads', 'migration_plan', 'admin_ops_sql_plan', 'test_pwa_notification'], true)) {
    mg_fail('Invalid system health action.', 422);
}
mg_admin_system_health_require_sensitive_action($user, $input, $action);

try {
    $pdo = mg_db();
    $result = match ($action) {
        'verify_storage' => mg_admin_system_health_verify_storage(),
        'retry_notifications' => mg_admin_system_health_retry_notifications($pdo, 100),
        'clean_uploads' => mg_admin_system_health_cleanup_uploads($pdo, 24, 100),
        'migration_plan' => mg_admin_system_health_migration_plan_v2($pdo),
        'admin_ops_sql_plan' => mg_admin_ops_installer_plan($pdo),
        'test_pwa_notification' => mg_admin_system_health_test_pwa_notification($pdo, $user),
    };

    $auditResult = $result;
    if ($action === 'admin_ops_sql_plan') {
        unset($auditResult['sql']);
    }
    if ($action === 'migration_plan') {
        unset($auditResult['items']);
        $auditResult['physical_missing_files'] = array_slice($auditResult['physical_missing_files'] ?? [], 0, 25);
        $auditResult['unapplied_files'] = array_slice($auditResult['unapplied_files'] ?? [], 0, 25);
        $auditResult['checksum_mismatches'] = array_slice($auditResult['checksum_mismatches'] ?? [], 0, 25);
    }
    mg_audit(
        'admin.system_health.' . $action,
        'system_health',
        ['result' => $auditResult, 'sensitive_confirmed' => true],
        (int)$user['id']
    );
    mg_event(
        'admin.system_health.' . $action,
        ['result' => $auditResult, 'sensitive_confirmed' => true],
        (int)$user['id']
    );
} catch (Throwable $error) {
    mg_security_log(
        'error',
        'admin.system_health.action_failed',
        'Administrative system health action failed.',
        ['action' => $action, 'exception_class' => $error::class],
        (int)$user['id']
    );
    mg_fail('Unable to complete the system health action.', 500);
}

$message = match ($action) {
    'verify_storage' => 'Persistent storage verified.',
    'retry_notifications' => 'Eligible notification deliveries were queued for retry.',
    'clean_uploads' => 'Abandoned uploads cleanup completed.',
    'migration_plan' => 'Detailed migration recovery plan prepared.',
    'admin_ops_sql_plan' => 'Admin ops SQL plan prepared.',
    'test_pwa_notification' => 'PWA test notification queued.',
};
mg_ok(['action' => $action, 'result' => $result], $message);
