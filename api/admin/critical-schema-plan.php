<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';
require_once __DIR__ . '/_critical_schema_plan.php';

mg_require_method('GET');
mg_admin_system_health_require_user();
mg_rate_limit('admin.system_health.critical_schema_plan', 'ip:' . mg_client_ip(), 30, 300);

try {
    $result=mg_admin_system_health_critical_schema_plan(mg_db());
    mg_ok(['plan'=>$result],$result['ready']?'Critical schema dependencies are satisfied.':'Critical schema remediation is required.');
} catch(Throwable $error) {
    mg_security_log('error','admin.system_health.critical_schema_plan_failed','Unable to prepare the critical schema plan.',['exception_class'=>$error::class],null);
    mg_fail('Unable to prepare the critical schema plan.',500);
}
