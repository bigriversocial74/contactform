<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/operations/_orchestration_attempts.php';

$limit=max(1,min((int)($argv[1]??50),500));
$pdo=mg_db();
$summary=['dispatched'=>0,'reconciled'=>0,'review_required'=>0,'completed'=>0,'failed'=>0,'incidents'=>['opened'=>0,'updated'=>0,'resolved'=>0],'idle'=>false];

for($i=0;$i<$limit;$i++){
    $didWork=false;
    $pdo->beginTransaction();
    try{
        $result=mg_operations_reconcile_next_demand_orchestration($pdo);
        if(!empty($result['processed'])){
            $didWork=true;$summary['reconciled']++;
            if(($result['status']??'')==='completed')$summary['completed']++;
            if(($result['status']??'')==='failed')$summary['failed']++;
        }
        $pdo->commit();
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        $summary['failed']++;
        fwrite(STDERR,'Demand orchestration reconciliation failed: '.$error->getMessage().PHP_EOL);
    }

    $pdo->beginTransaction();
    try{
        $result=mg_demand_orchestrate_next_signal($pdo);
        if(!empty($result['processed'])){
            $didWork=true;$summary['dispatched']++;
            if(($result['status']??'')==='review_required')$summary['review_required']++;
        }
        $pdo->commit();
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        $summary['failed']++;
        fwrite(STDERR,'Demand signal dispatch failed: '.$error->getMessage().PHP_EOL);
    }

    if(!$didWork){$summary['idle']=true;break;}
}

try{
    $pdo->beginTransaction();
    $summary['incidents']=mg_operations_reconcile_demand_incidents($pdo,mg_operations_system_actor_user_id($pdo));
    $pdo->commit();
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['failed']++;
    fwrite(STDERR,'Demand orchestration incident reconciliation failed: '.$error->getMessage().PHP_EOL);
}

fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
