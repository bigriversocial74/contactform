<?php
declare(strict_types=1);

require_once __DIR__ . '/_swarm.php';
$user=mg_require_permission('agent.swarms.run');
$pdo=mg_db();

if($_SERVER['REQUEST_METHOD']==='GET'){
    $status=trim((string)($_GET['status']??'all'));
    $sql='SELECT sr.public_id,t.public_id team_public_id,sr.objective,sr.status,sr.budget_units,sr.reserved_units,sr.consumed_units,sr.input_json,sr.result_json,sr.failure_message,sr.started_at,sr.completed_at,sr.created_at,sr.updated_at FROM agent_swarm_runs sr INNER JOIN agent_teams t ON t.id=sr.team_id WHERE sr.owner_user_id=?';
    $params=[(int)$user['id']];if($status!=='all'){$sql.=' AND sr.status=?';$params[]=$status;}$sql.=' ORDER BY sr.id DESC LIMIT 100';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);mg_ok(['runs'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

mg_require_method('POST');
$input=mg_input();mg_require_csrf_for_write($input);$pdo->beginTransaction();
try{
    $run=mg_swarm_create_run($pdo,(int)$user['id'],$input);$pdo->commit();
    mg_audit('agent.swarm_requested','agent_swarm_run',['run_id'=>$run['public_id'],'team_id'=>$input['team_id']??null,'duplicate'=>(bool)$run['duplicate']],(int)$user['id']);
    mg_ok(['run'=>$run],$run['duplicate']?'Existing swarm run returned.':'Swarm run created.',$run['duplicate']?200:201);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to create swarm run.',500);}
