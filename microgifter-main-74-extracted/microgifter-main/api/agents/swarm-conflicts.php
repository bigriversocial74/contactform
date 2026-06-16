<?php
declare(strict_types=1);

require_once __DIR__ . '/_swarm.php';
$user=mg_require_permission('agent.swarms.resolve');
$pdo=mg_db();

if($_SERVER['REQUEST_METHOD']==='GET'){
    $stmt=$pdo->prepare("SELECT c.public_id,sr.public_id run_public_id,st.public_id task_public_id,c.conflict_type,c.status,c.summary,c.candidates_json,c.resolution_json,c.resolution_method,c.created_at,c.resolved_at FROM agent_swarm_conflicts c INNER JOIN agent_swarm_runs sr ON sr.id=c.swarm_run_id LEFT JOIN agent_swarm_tasks st ON st.id=c.task_id WHERE sr.owner_user_id=? ORDER BY c.id DESC LIMIT 100");
    $stmt->execute([(int)$user['id']]);mg_ok(['conflicts'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

mg_require_method('POST');
$input=mg_input();mg_require_csrf_for_write($input);$public=trim((string)($input['conflict_id']??''));$decision=trim((string)($input['decision']??''));
if($public===''||!in_array($decision,['resolve','dismiss'],true))mg_fail('Conflict and valid decision are required.',422);
$pdo->beginTransaction();
try{
    $stmt=$pdo->prepare('SELECT c.* FROM agent_swarm_conflicts c INNER JOIN agent_swarm_runs sr ON sr.id=c.swarm_run_id WHERE c.public_id=? AND sr.owner_user_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$public,(int)$user['id']]);$conflict=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$conflict)throw new RuntimeException('Swarm conflict not found.');
    mg_swarm_resolve_conflict($pdo,$conflict,$decision,(int)$user['id'],(array)($input['resolution']??[]));$pdo->commit();
    mg_audit('agent.swarm_conflict_'.$decision,'agent_swarm_conflict',['conflict_id'=>$public],(int)$user['id']);mg_ok(['conflict_id'=>$public,'status'=>$decision==='resolve'?'resolved':'dismissed'],'Swarm conflict updated.');
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to resolve swarm conflict.',500);}
