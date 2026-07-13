<?php
declare(strict_types=1);

function mg_phase2_table_exists(PDO $pdo, string $table): bool
{
    $stmt=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function mg_phase2_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
    $stmt->execute([$table,$column]);
    return (bool)$stmt->fetchColumn();
}

function mg_phase2_enum_contains(PDO $pdo, string $table, string $column, string $value): bool
{
    $stmt=$pdo->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');
    $stmt->execute([$table,$column]);
    $type=(string)($stmt->fetchColumn()?:'');
    return $type!=='' && str_contains($type,"'".$value."'");
}

function mg_admin_system_health_critical_schema_plan(PDO $pdo): array
{
    $loyaltyChecks=[
        'loyalty_quest_evidence'=>mg_phase2_table_exists($pdo,'loyalty_quest_evidence'),
        'loyalty_quest_integrity_attempts'=>mg_phase2_table_exists($pdo,'loyalty_quest_integrity_attempts'),
        'loyalty_quest_integrity_signals'=>mg_phase2_table_exists($pdo,'loyalty_quest_integrity_signals'),
        'evidence_fingerprint'=>mg_phase2_column_exists($pdo,'loyalty_quest_evidence','evidence_fingerprint'),
        'ip_hash'=>mg_phase2_column_exists($pdo,'loyalty_quest_evidence','ip_hash'),
        'device_hash'=>mg_phase2_column_exists($pdo,'loyalty_quest_evidence','device_hash'),
        'integrity_score'=>mg_phase2_column_exists($pdo,'loyalty_quest_evidence','integrity_score'),
        'integrity_status'=>mg_phase2_column_exists($pdo,'loyalty_quest_evidence','integrity_status'),
    ];
    $loyaltyReady=!in_array(false,$loyaltyChecks,true);

    $merchantChecks=[
        'merchant_locations'=>mg_phase2_table_exists($pdo,'merchant_locations'),
        'merchant_claim_codes'=>mg_phase2_table_exists($pdo,'merchant_claim_codes'),
        'merchant_claim_code_events'=>mg_phase2_table_exists($pdo,'merchant_claim_code_events'),
    ];
    $merchantReady=!in_array(false,$merchantChecks,true);

    $adminChecks=[
        'admin_queue_notifications'=>mg_phase2_table_exists($pdo,'admin_queue_notifications'),
        'notification_type'=>mg_phase2_column_exists($pdo,'admin_queue_notifications','notification_type'),
        'queue_overload_predicted'=>mg_phase2_enum_contains($pdo,'admin_queue_notifications','notification_type','queue_overload_predicted'),
        'forecasted_sla_breach'=>mg_phase2_enum_contains($pdo,'admin_queue_notifications','notification_type','forecasted_sla_breach'),
    ];
    $adminReady=!in_array(false,$adminChecks,true);

    $files=[];
    if(!$loyaltyReady)$files[]='loyalty_quest_integrity_controls_v1.sql';
    if(!$merchantChecks['merchant_locations'])$files[]='stage_5a_merchant_workspace.sql';
    if(!$merchantChecks['merchant_claim_codes']||!$merchantChecks['merchant_claim_code_events'])$files[]='stage_3_merchant_claim_codes.sql';
    if(!$adminReady)$files[]='system_health_critical_schema_phase2.sql';
    $files=array_values(array_unique($files));

    $modules=[
        ['key'=>'loyalty_quest_integrity','label'=>'Loyalty Quest integrity','ready'=>$loyaltyReady,'migration'=>'loyalty_quest_integrity_controls_v1.sql','checks'=>$loyaltyChecks],
        ['key'=>'merchant_locations','label'=>'Merchant locations','ready'=>$merchantReady,'migration'=>'stage_5a_merchant_workspace.sql + stage_3_merchant_claim_codes.sql','checks'=>$merchantChecks],
        ['key'=>'admin_ops_notifications','label'=>'Admin Ops notifications','ready'=>$adminReady,'migration'=>'system_health_critical_schema_phase2.sql','checks'=>$adminChecks],
    ];

    return [
        'ready'=>$loyaltyReady&&$merchantReady&&$adminReady,
        'generated_at'=>gmdate('c'),
        'modules'=>$modules,
        'required_files'=>$files,
        'required_count'=>count($files),
        'import_order'=>$files,
        'note'=>'Import only the listed files, in order, through the approved database deployment process. Re-run this probe after import.',
    ];
}
