<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
foreach(['stage_15_psr_demand_intelligence.sql','stage_15c_prepaid_demand_commitments.sql'] as $file){
    $path=dirname(__DIR__) . '/database/' . $file;
    $sql=file_get_contents($path);
    if(!is_string($sql)||trim($sql)==='')throw new RuntimeException('Stage 15 migration is missing or empty: '.$file);
    $pdo->exec($sql);
}
fwrite(STDOUT,"Stage 15 demand intelligence and prepaid commitments migrations applied.\n");
