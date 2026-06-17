<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
$tables=['agent_teams','agent_team_members','agent_provider_routes','agent_swarm_runs','agent_swarm_tasks','agent_swarm_task_dependencies','agent_swarm_conflicts','agent_swarm_events','demand_signal_orchestrations','demand_signal_orchestration_events'];
foreach($tables as $table){
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Missing Stage 17 table: '.$table);
}
fwrite(STDOUT,"Stage 17 swarm and demand orchestration smoke validation passed.\n");
