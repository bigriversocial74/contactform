<?php
declare(strict_types=1);

require_once __DIR__ . '/_swarm_workflow.php';

mg_require_method('POST');
$user=mg_require_permission('agent.swarms.resolve');
$input=mg_input();
mg_require_csrf_for_write($input);

$pdo=mg_db();
$pdo->beginTransaction();
try{
    $result=mg_swarm_review_task($pdo,(int)$user['id'],$input);
    $pdo->commit();
    mg_audit('agent.swarm_task_review_'.$result['status'],'agent_swarm_task',['task_id'=>$result['task_id'],'status'=>$result['status'],'duplicate'=>$result['duplicate']],(int)$user['id']);
    mg_ok($result,'Swarm task review recorded.');
}catch(MgSwarmWorkflowException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),$e->httpStatus);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail('Unable to review swarm task.',500);
}
