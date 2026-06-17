<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
foreach(['operational_incidents','operational_incident_events','deployment_releases','release_gate_results','retention_policies','retention_runs','operational_check_results','demand_signal_orchestrations','demand_signal_orchestration_events'] as $table){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Missing Stage 18 table: '.$table);
}
$stmt=$pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=?');
$stmt->execute(['operations.orchestrations.view']);
if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Missing Stage 18B orchestration view permission.');
fwrite(STDOUT,"Stage 18 production hardening and demand orchestration monitoring smoke validation passed.\n");
