<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
foreach(['demand_signal_orchestration_attempts','demand_signal_orchestration_incidents'] as $table){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Missing Stage 18C table: '.$table);
}
$stmt=$pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=?');
$stmt->execute(['operations.orchestrations.retry']);
if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Missing Stage 18C retry permission.');
$stmt=$pdo->prepare('SELECT COUNT(*) FROM retention_policies WHERE policy_key=? AND status=?');
$stmt->execute(['demand_orchestration_events_365d','active']);
if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Missing Stage 18C event retention policy.');
fwrite(STDOUT,"Stage 18C orchestration retry, incident, and retention smoke validation passed.\n");
