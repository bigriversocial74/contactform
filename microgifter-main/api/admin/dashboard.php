<?php
declare(strict_types=1);

require_once __DIR__ . '/_dashboard.php';

mg_require_method('GET');
$user=mg_admin_dashboard_require_user();

try{
    $data=mg_admin_dashboard_read(mg_db(),$user,[
        'window_days'=>$_GET['window_days']??MG_ADMIN_DASHBOARD_DEFAULT_WINDOW_DAYS,
    ]);
}catch(Throwable $error){
    error_log('Admin dashboard read failed: '.$error::class);
    mg_fail('Unable to load admin dashboard.',500);
}

mg_ok($data,'Admin dashboard loaded.');
