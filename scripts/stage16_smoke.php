<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
foreach(['agent_strategies','agent_workflow_runs','agent_workflow_actions','agent_approval_requests','agent_execution_events'] as $table){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Missing Stage 16 table: '.$table);
}
fwrite(STDOUT,"Stage 16 agent execution smoke validation passed.\n");
