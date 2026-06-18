<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/db.php';

$pdo=mg_db();
$table=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
$column=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
$index=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
$constraint=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');

$table->execute(['tip_payment_recoveries']);
if((int)$table->fetchColumn()!==1)throw new RuntimeException('Missing Stage 12D table: tip_payment_recoveries');

foreach([
    'source_type','source_reference','tip_id','ledger_group_id','provider_event_id'
] as $name){
    $column->execute(['payment_refunds',$name]);
    if((int)$column->fetchColumn()!==1)throw new RuntimeException('Missing Stage 12D payment_refunds column: '.$name);
}
foreach([
    'source_type','source_reference','tip_id','payout_hold_id','recovery_group_id','provider_event_id','metadata_json'
] as $name){
    $column->execute(['payment_disputes',$name]);
    if((int)$column->fetchColumn()!==1)throw new RuntimeException('Missing Stage 12D payment_disputes column: '.$name);
}
foreach(['source_type','source_reference','idempotency_key'] as $name){
    $column->execute(['payout_holds',$name]);
    if((int)$column->fetchColumn()!==1)throw new RuntimeException('Missing Stage 12D payout_holds column: '.$name);
}
foreach([
    ['payment_refunds','idx_payment_refunds_source'],
    ['payment_refunds','idx_payment_refunds_tip'],
    ['payment_disputes','idx_payment_disputes_source'],
    ['payment_disputes','idx_payment_disputes_tip'],
    ['payout_holds','uq_payout_holds_idempotency'],
    ['tip_payment_recoveries','uq_tip_recoveries_tip_idempotency'],
    ['tip_payment_recoveries','uq_tip_recoveries_provider_event'],
] as [$tableName,$indexName]){
    $index->execute([$tableName,$indexName]);
    if((int)$index->fetchColumn()<1)throw new RuntimeException('Missing Stage 12D index: '.$indexName);
}
foreach([
    ['payment_refunds','fk_payment_refunds_tip'],
    ['payment_disputes','fk_payment_disputes_tip'],
    ['tip_payment_recoveries','fk_tip_recoveries_tip'],
    ['tip_payment_recoveries','fk_tip_recoveries_intent'],
] as [$tableName,$constraintName]){
    $constraint->execute([$tableName,$constraintName]);
    if((int)$constraint->fetchColumn()!==1)throw new RuntimeException('Missing Stage 12D constraint: '.$constraintName);
}
foreach([
    ['payment_refunds','order_id'],['payment_refunds','merchant_user_id'],['payment_refunds','requested_by_user_id'],
    ['payment_disputes','order_id'],['payment_disputes','merchant_user_id'],
] as [$tableName,$columnName]){
    $stmt=$pdo->prepare('SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$tableName,$columnName]);
    if((string)$stmt->fetchColumn()!=='YES')throw new RuntimeException("Stage 12D requires {$tableName}.{$columnName} to allow non-order recovery sources.");
}
$migration=$pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration_key=?');
$migration->execute(['stage_12d_tip_recovery']);
if((int)$migration->fetchColumn()!==1)throw new RuntimeException('Stage 12D migration record is missing.');

fwrite(STDOUT,"Stage 12D tip recovery smoke validation passed.\n");
