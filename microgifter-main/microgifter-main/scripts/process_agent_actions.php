<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/agents/_workflow.php';

$limit=max(1,min((int)($argv[1]??50),500));
$pdo=mg_db();
$processed=0;$completed=0;$failed=0;

for($i=0;$i<$limit;$i++){
    $pdo->beginTransaction();
    try{
        $result=mg_agent_process_next_action($pdo);
        if(!$result['processed']){$pdo->commit();break;}
        $processed++;
        if($result['status']==='completed')$completed++;else $failed++;
        $pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        $failed++;
    }
}

fwrite(STDOUT,json_encode(['processed'=>$processed,'completed'=>$completed,'failed'=>$failed],JSON_PRETTY_PRINT).PHP_EOL);
