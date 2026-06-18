<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/db.php';
$pdo=mg_db();
foreach([
    dirname(__DIR__) . '/database/stage_12d_tip_recovery.sql',
    dirname(__DIR__) . '/database/stage_13_subscriptions_monetization.sql',
    dirname(__DIR__) . '/database/stage_13b_generated_initial_subscription_funding.sql',
    dirname(__DIR__) . '/database/stage_13c_generated_subscription_recovery_reconciliation.sql',
] as $path){
    $sql=file_get_contents($path);
    if(!is_string($sql)||trim($sql)==='')throw new RuntimeException('Stage 13 migration is missing or empty: '.basename($path));
    $pdo->exec($sql);
}
fwrite(STDOUT,"Stage 13 subscriptions, monetization, funding, and payment recovery migrations applied.\n");
