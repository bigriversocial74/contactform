<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';
require_once __DIR__ . '/_system_health_actions.php';
require_once dirname(__DIR__, 2) . '/includes/pwa-push.php';

mg_require_method('GET');
$user = mg_admin_system_health_require_user();

try {
    $pdo = mg_db();
    $data = mg_admin_system_health_read($pdo);
    $pwaHealth = mg_pwa_push_health($pdo);
    $pwaCritical = !$pwaHealth['subscription_tables_ready'] || !$pwaHealth['service_worker_file_present'];
    $pwaWarning = !$pwaHealth['enabled'] || !$pwaHealth['vapid_public_key_configured'] || !$pwaHealth['vapid_private_key_configured'] || !$pwaHealth['provider_available'] || $pwaHealth['failed_delivery_count'] > 0;
    $data['services']['pwa_notifications'] = mg_admin_system_health_service(
        $pwaCritical ? 'critical' : ($pwaWarning ? 'warning' : 'healthy'),
        $pwaCritical ? 'PWA notification schema or service worker is incomplete.' : ($pwaWarning ? 'PWA notification channel is installed but needs configuration or delivery review.' : 'PWA browser notification channel is ready.'),
        [
            'service_worker_active' => $pwaHealth['service_worker_file_present'],
            'notification_permission_supported' => 'client_checked',
            'push_subscription_endpoint_configured' => $pwaHealth['vapid_public_key_configured'] && $pwaHealth['vapid_private_key_configured'],
            'provider_available' => $pwaHealth['provider_available'],
            'active_subscriptions_count' => $pwaHealth['active_subscriptions_count'],
            'failed_delivery_count' => $pwaHealth['failed_delivery_count'],
            'last_test_notification_result' => $pwaHealth['last_test_notification_result']['status'] ?? null,
        ]
    );
    $data['pwa_notifications'] = $pwaHealth;
    $data['status'] = mg_admin_system_health_overall($data['services']);
    $data['summary'] = match ($data['status']) {
        'healthy' => 'All monitored systems are operating normally.',
        'warning' => 'One or more systems should be reviewed.',
        default => 'One or more systems require attention.',
    };

    $canManage = mg_admin_system_health_can_manage($user);
    $data['actions'] = [
        'verify_storage' => $canManage,
        'retry_notifications' => $canManage,
        'clean_uploads' => $canManage,
        'migration_plan' => $canManage && (($data['services']['migrations']['status'] ?? '') !== 'healthy'),
        'admin_ops_sql_plan' => $canManage,
        'test_pwa_notification' => $canManage,
    ];
} catch (Throwable $error) {
    mg_security_log(
        'error',
        'admin.system_health.read_failed',
        'Administrative system health read failed.',
        ['exception_class' => $error::class],
        (int)$user['id']
    );
    mg_fail('Unable to load system health.', 500);
}

header('Cache-Control: private, no-store, max-age=0');
mg_ok($data, 'System health loaded.');
