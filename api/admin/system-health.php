<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health_read.php';

mg_require_method('GET');
$user=mg_admin_system_health_require_user(false);
header('Cache-Control: private, no-store, max-age=0');
mg_ok(mg_admin_system_health_read(mg_db(),$user),'System health loaded.');
