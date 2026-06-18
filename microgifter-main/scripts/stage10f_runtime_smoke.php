<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli')exit(1);

require_once dirname(__DIR__).'/api/microgifts/_claim_operations.php';

$pdo=mg_db();
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

$requiredTables=[
    'microgift_claim_attempts',
    'microgift_claim_attempt_security',
    'microgift_operational_outbox',
    'microgift_inbox_items',
    'microgift_claim_rate_policies',
];
foreach($requiredTables as $table){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Missing Stage 10F dependency table: '.$table);
}

$folderType=$pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='microgift_inbox_items' AND COLUMN_NAME='folder'")->fetchColumn();
foreach(['inbox','sent','claimed'] as $folder){
    if(!is_string($folderType)||!str_contains($folderType,"'{$folder}'"))throw new RuntimeException('Action Center folder enum is missing '.$folder);
}

$aggregate='stage10f-'.bin2hex(random_bytes(8));
$pdo->beginTransaction();
$outboxId=mg_claim_operational_outbox($pdo,'stage10f.transaction_probe','runtime_smoke',$aggregate,['probe'=>true]);
$pdo->rollBack();
$stmt=$pdo->prepare('SELECT COUNT(*) FROM microgift_operational_outbox WHERE public_id=?');
$stmt->execute([$outboxId]);
if((int)$stmt->fetchColumn()!==0)throw new RuntimeException('Outbox row survived transaction rollback.');

$pdo->beginTransaction();
$outboxId=mg_claim_operational_outbox($pdo,'stage10f.transaction_probe','runtime_smoke',$aggregate,['probe'=>true]);
$pdo->commit();
$stmt=$pdo->prepare('SELECT COUNT(*) FROM microgift_operational_outbox WHERE public_id=?');
$stmt->execute([$outboxId]);
if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Committed outbox row was not persisted.');
$pdo->prepare('DELETE FROM microgift_operational_outbox WHERE public_id=?')->execute([$outboxId]);

$correlation='stage10f-'.bin2hex(random_bytes(8));
$pdo->beginTransaction();
$pdo->prepare("INSERT INTO microgift_operational_outbox (public_id,topic,aggregate_type,aggregate_public_id,payload_json,status,available_at,created_at,updated_at) VALUES (?,?,?,?,?,'pending',NOW(),NOW(),NOW())")
    ->execute([mg_microgift_uuid(),'stage10f.rollback_probe','runtime_smoke',$correlation,'{}']);
$pdo->rollBack();
$attemptId=mg_location_claim_record_attempt($pdo,[
    'result'=>'internal_error',
    'reason_code'=>'runtime_rollback_probe',
    'correlation_id'=>$correlation,
    'ip_hash'=>hash('sha256','127.0.0.1'),
    'metadata'=>['probe'=>true],
]);
$stmt=$pdo->prepare('SELECT a.id,COUNT(s.id) security_rows FROM microgift_claim_attempts a LEFT JOIN microgift_claim_attempt_security s ON s.attempt_id=a.id WHERE a.public_id=? GROUP BY a.id');
$stmt->execute([$attemptId]);
$row=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$row||(int)$row['security_rows']!==1)throw new RuntimeException('Durable failed attempt or security envelope was not persisted.');
$pdo->prepare('DELETE FROM microgift_claim_attempts WHERE public_id=?')->execute([$attemptId]);

if(!function_exists('mg_claim_execute_operation'))throw new RuntimeException('Canonical claim operation is unavailable.');
if(function_exists('mg_claim_execute_operation_configured'))throw new RuntimeException('Duplicate configured claim operation still exists.');

echo "Stage 10F runtime smoke validation passed.\n";
