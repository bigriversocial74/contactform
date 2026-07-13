<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$required=[
    'api/admin/_critical_schema_plan.php'=>['loyalty_quest_integrity_controls_v1.sql','stage_5a_merchant_workspace.sql','stage_3_merchant_claim_codes.sql','system_health_critical_schema_phase2.sql','queue_overload_predicted'],
    'api/admin/critical-schema-plan.php'=>['mg_admin_system_health_critical_schema_plan','mg_admin_system_health_require_user'],
    'api/admin/system-health-action.php'=>["'critical_schema_plan'",'mg_admin_system_health_critical_schema_plan'],
    'database/system_health_critical_schema_phase2.sql'=>['admin_queue_notifications','queue_overload_predicted','system_health_critical_schema_phase2'],
];
$errors=[];$checks=0;
foreach($required as $path=>$markers){
    $file=$root.'/'.$path;
    if(!is_file($file)){$errors[]="Missing {$path}";continue;}
    $content=(string)file_get_contents($file);
    foreach($markers as $marker){$checks++;if(!str_contains($content,$marker))$errors[]="{$path} missing marker: {$marker}";}
}
if($errors){fwrite(STDERR,"System Health Critical Schema Phase 2 validation failed:\n- ".implode("\n- ",$errors)."\n");exit(1);}
echo "System Health Critical Schema Phase 2: {$checks} contract checks passed.\n";
