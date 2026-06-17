<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/agents/_swarm_workflow.php';

$limit=max(1,min((int)($argv[1]??50),500));
$pdo=mg_db();$summary=['routed'=>0,'completed'=>0,'failed'=>0,'blocked'=>0];

$sync=$pdo->query("SELECT st.public_id FROM agent_swarm_tasks st INNER JOIN agent_workflow_runs wr ON wr.id=st.workflow_run_id WHERE st.status IN ('routed','running') AND wr.status IN ('completed','partially_completed','failed','canceled') ORDER BY st.id ASC LIMIT 500")->fetchAll(PDO::FETCH_COLUMN);
foreach($sync as $taskPublicId){
    $pdo->beginTransaction();
    try{
        $result=mg_swarm_sync_workflow($pdo,(string)$taskPublicId);
        $pdo->commit();
        if($result['status']==='failed')$summary['failed']++;else $summary['completed']++;
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        $summary['failed']++;
    }
}

for($i=0;$i<$limit;$i++){
    $pdo->beginTransaction();
    try{
        $result=mg_swarm_route_next_task($pdo);
        if(!$result['processed']){$pdo->commit();break;}
        $pdo->commit();$summary['routed']++;
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        $summary['blocked']++;
        break;
    }
}

fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
