<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
foreach([
    dirname(__DIR__) . '/database/stage_18_production_hardening_launch_readiness.sql',
    dirname(__DIR__) . '/database/stage_18b_demand_orchestration_operations.sql',
    dirname(__DIR__) . '/database/stage_18c_demand_orchestration_recovery.sql',
    dirname(__DIR__) . '/database/stage_18c2_demand_orchestration_retention.sql',
] as $path){
    $sql=file_get_contents($path);
    if(!is_string($sql)||trim($sql)==='')throw new RuntimeException('Stage 18 migration is missing or empty: '.basename($path));
    $pdo->exec($sql);
}
fwrite(STDOUT,"Stage 18 production hardening, monitoring, retry, incident, and retention migrations applied.\n");
