<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';
require_once __DIR__ . '/_system_health_actions.php';
require_once __DIR__ . '/_golden_path_health.php';

mg_require_method('GET');
$user=mg_admin_system_health_require_user();
mg_rate_limit('admin.lifecycle_health.read','user:'.(int)$user['id'],30,300);

try{
    $data=mg_admin_golden_path_scan(mg_db(),max(1,min(100,(int)($_GET['limit']??25))));
    $data['can_manage']=mg_admin_system_health_can_manage($user);
}catch(Throwable $error){
    mg_security_log('error','admin.lifecycle_health.read_failed','Lifecycle health read failed.',['exception_class'=>$error::class],(int)$user['id']);
    mg_fail('Unable to load lifecycle health.',500);
}

header('Cache-Control: private, no-store, max-age=0');
mg_ok($data,'Lifecycle health loaded.');
