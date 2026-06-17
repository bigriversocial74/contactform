<?php
declare(strict_types=1);

require_once __DIR__ . '/_execution.php';
$user=mg_require_permission('agent.workflows.run');
$pdo=mg_db();

if($_SERVER['REQUEST_METHOD']==='GET'){
    $status=trim((string)($_GET['status']??'all'));
    $sql='SELECT r.public_id,s.public_id strategy_public_id,a.public_id agent_public_id,r.trigger_type,r.trigger_reference,r.status,r.input_json,r.plan_json,r.result_json,r.failure_message,r.requested_at,r.approved_at,r.started_at,r.completed_at,r.updated_at FROM agent_workflow_runs r INNER JOIN agent_strategies s ON s.id=r.strategy_id INNER JOIN agents a ON a.id=r.agent_id WHERE r.owner_user_id=?';
    $params=[(int)$user['id']];
    if($status!=='all'){$sql.=' AND r.status=?';$params[]=$status;}
    $sql.=' ORDER BY r.id DESC LIMIT 100';$stmt=$pdo->prepare($sql);$stmt->execute($params);mg_ok(['runs'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

mg_require_method('POST');
$input=mg_input();mg_require_csrf_for_write($input);$pdo->beginTransaction();
try{
    $run=mg_agent_create_run($pdo,(int)$user['id'],$input);
    if(!empty($input['actions'])&&!$run['duplicate']){
        $planned=mg_agent_plan_run($pdo,$run,(array)$input['actions'],(int)$user['id']);
    }else{$planned=['run_id'=>$run['public_id'],'status'=>$run['status'],'actions'=>[]];}
    $pdo->commit();
    mg_audit('agent.workflow_requested','agent_workflow_run',['run_id'=>$run['public_id'],'strategy_id'=>$input['strategy_id']??null,'duplicate'=>(bool)$run['duplicate']],(int)$user['id']);
    mg_ok(['run'=>$run,'plan'=>$planned],$run['duplicate']?'Existing workflow run returned.':'Agent workflow created.',$run['duplicate']?200:201);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to create agent workflow.',500);}
