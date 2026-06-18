<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';
require_once __DIR__ . '/_system_health_actions.php';

mg_require_method('GET');
$user = mg_admin_system_health_require_user();

try {
    $data = mg_admin_system_health_read(mg_db());
    $canManage = mg_admin_system_health_can_manage($user);
    $data['actions'] = [
        'verify_storage' => $canManage,
        'retry_notifications' => $canManage,
        'clean_uploads' => $canManage,
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
