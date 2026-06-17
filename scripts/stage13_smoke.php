<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
foreach(['subscription_plans','subscriptions','subscription_attempts','subscription_events','subscription_payment_recoveries'] as $table){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    if((int)$stmt->fetchColumn()!==1)throw new RuntimeException("Missing Stage 13 table: {$table}");
}
foreach([
    'subscriptions'=>['recovery_status','recovery_attempt_id','recovery_reference','pre_recovery_status','pre_recovery_next_billing_at','recovery_started_at','recovery_resolved_at','access_suspended_at'],
    'subscription_attempts'=>['recovery_status','recovered_amount_cents','recovery_reference','recovery_started_at','recovery_resolved_at'],
] as $table=>$columns){
    foreach($columns as $column){
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table,$column]);
        if((int)$stmt->fetchColumn()!==1)throw new RuntimeException("Missing Stage 13 recovery column: {$table}.{$column}");
    }
}
foreach(['subscriptions.create','subscriptions.manage_own','subscription_plans.manage','subscriptions.admin'] as $permission){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=?');$stmt->execute([$permission]);
    if((int)$stmt->fetchColumn()!==1)throw new RuntimeException("Missing Stage 13 permission: {$permission}");
}
fwrite(STDOUT,"Stage 13 subscriptions and payment recovery smoke validation passed.\n");
