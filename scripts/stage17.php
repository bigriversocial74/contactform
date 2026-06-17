<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
foreach([
    dirname(__DIR__) . '/database/stage_17_multi_agent_swarms.sql',
    dirname(__DIR__) . '/database/stage_17b_demand_signal_agent_orchestration.sql',
] as $path){
    $sql=file_get_contents($path);
    if(!is_string($sql)||trim($sql)==='')throw new RuntimeException('Stage 17 migration is missing or empty: '.basename($path));
    $pdo->exec($sql);
}
fwrite(STDOUT,"Stage 17 swarm and demand orchestration migrations applied.\n");
