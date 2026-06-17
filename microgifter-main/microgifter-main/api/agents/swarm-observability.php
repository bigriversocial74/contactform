<?php
declare(strict_types=1);

require_once __DIR__ . '/_swarm.php';
mg_require_method('GET');
$user=mg_require_permission('agent.swarms.observe');
$runPublic=trim((string)($_GET['run_id']??''));
if($runPublic==='')mg_fail('Swarm run is required.',422);
$pdo=mg_db();
$stmt=$pdo->prepare('SELECT sr.*,t.public_id team_public_id,t.name team_name FROM agent_swarm_runs sr INNER JOIN agent_teams t ON t.id=sr.team_id WHERE sr.public_id=? AND sr.owner_user_id=? LIMIT 1');
$stmt->execute([$runPublic,(int)$user['id']]);$run=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$run)mg_fail('Swarm run not found.',404);
$tasks=$pdo->prepare('SELECT st.public_id,st.task_key,st.task_type,st.capability_key,st.objective,st.status,st.priority,st.requires_review,st.estimated_units,st.reserved_units,st.consumed_units,st.route_json,st.output_json,st.failure_message,st.started_at,st.completed_at,a.public_id agent_public_id,wr.public_id workflow_run_public_id FROM agent_swarm_tasks st LEFT JOIN agent_team_members tm ON tm.id=st.team_member_id LEFT JOIN agents a ON a.id=tm.agent_id LEFT JOIN agent_workflow_runs wr ON wr.id=st.workflow_run_id WHERE st.swarm_run_id=? ORDER BY st.priority ASC,st.id ASC');
$tasks->execute([(int)$run['id']]);
$events=$pdo->prepare('SELECT e.public_id,st.public_id task_public_id,e.event_type,e.payload_json,e.created_at FROM agent_swarm_events e LEFT JOIN agent_swarm_tasks st ON st.id=e.task_id WHERE e.swarm_run_id=? ORDER BY e.id ASC LIMIT 500');
$events->execute([(int)$run['id']]);
$conflicts=$pdo->prepare('SELECT public_id,conflict_type,status,summary,candidates_json,resolution_json,resolution_method,created_at,resolved_at FROM agent_swarm_conflicts WHERE swarm_run_id=? ORDER BY id ASC');
$conflicts->execute([(int)$run['id']]);
mg_ok(['run'=>$run,'tasks'=>$tasks->fetchAll(PDO::FETCH_ASSOC),'events'=>$events->fetchAll(PDO::FETCH_ASSOC),'conflicts'=>$conflicts->fetchAll(PDO::FETCH_ASSOC),'budget'=>['limit'=>(int)$run['budget_units'],'reserved'=>(int)$run['reserved_units'],'consumed'=>(int)$run['consumed_units'],'remaining'=>max(0,(int)$run['budget_units']-(int)$run['consumed_units'])]]);
