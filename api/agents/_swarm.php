<?php
declare(strict_types=1);

require_once __DIR__ . '/_execution.php';

function mg_swarm_event(PDO $pdo,int $runId,?int $taskId,string $type,?int $actor,array $payload=[]): void
{
    $pdo->prepare('INSERT INTO agent_swarm_events (public_id,swarm_run_id,task_id,event_type,actor_user_id,payload_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(),$runId,$taskId,$type,$actor,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
}

function mg_swarm_team_owned(PDO $pdo,string $publicId,int $userId,bool $forUpdate=false): array
{
    $sql='SELECT * FROM agent_teams WHERE public_id=? AND owner_user_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute([$publicId,$userId]);$team=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$team)throw new RuntimeException('Agent team not found.');
    return $team;
}

function mg_swarm_create_team(PDO $pdo,int $userId,array $input): array
{
    $name=mb_substr(trim((string)($input['name']??'')),0,190);
    $objective=mb_substr(trim((string)($input['objective']??'')),0,500);
    $mode=trim((string)($input['coordination_mode']??'manager_worker'));
    $policy=trim((string)($input['conflict_policy']??'owner_decides'));
    if($name===''||$objective==='')throw new InvalidArgumentException('Team name and objective are required.');
    if(!in_array($mode,['manager_worker','peer_review','pipeline','consensus'],true))throw new InvalidArgumentException('Invalid coordination mode.');
    if(!in_array($policy,['owner_decides','lead_agent','majority','highest_confidence'],true))throw new InvalidArgumentException('Invalid conflict policy.');
    $public=mg_public_uuid();
    $pdo->prepare("INSERT INTO agent_teams (public_id,owner_user_id,name,objective,status,coordination_mode,conflict_policy,max_parallel_tasks,default_budget_units,metadata_json,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,'draft',?,?,?,?,?,?,NOW(),NOW())")
        ->execute([$public,$userId,$name,$objective,$mode,$policy,max(1,min((int)($input['max_parallel_tasks']??5),50)),max(1,(int)($input['default_budget_units']??100000)),json_encode($input['metadata']??[],JSON_THROW_ON_ERROR),$userId]);
    $stmt=$pdo->prepare('SELECT * FROM agent_teams WHERE public_id=?');$stmt->execute([$public]);return $stmt->fetch(PDO::FETCH_ASSOC);
}

function mg_swarm_add_member(PDO $pdo,array $team,int $userId,array $input): array
{
    $agent=mg_agent_owned($pdo,trim((string)($input['agent_id']??'')),$userId);
    $roleKey=mb_substr(trim((string)($input['role_key']??'')),0,100);
    $roleType=trim((string)($input['role_type']??'specialist'));
    $caps=array_values(array_unique(array_filter((array)($input['capabilities']??[]),'is_string')));
    if($roleKey===''||$caps===[])throw new InvalidArgumentException('Role key and capabilities are required.');
    if(!in_array($roleType,['lead','planner','researcher','analyst','executor','reviewer','specialist'],true))throw new InvalidArgumentException('Invalid member role.');
    $pdo->prepare("INSERT INTO agent_team_members (team_id,agent_id,role_key,role_type,priority,capabilities_json,routing_profile_json,max_concurrent_tasks,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?, 'active',NOW(),NOW()) ON DUPLICATE KEY UPDATE role_key=VALUES(role_key),role_type=VALUES(role_type),priority=VALUES(priority),capabilities_json=VALUES(capabilities_json),routing_profile_json=VALUES(routing_profile_json),max_concurrent_tasks=VALUES(max_concurrent_tasks),status='active',updated_at=NOW()")
        ->execute([(int)$team['id'],(int)$agent['id'],$roleKey,$roleType,max(1,min((int)($input['priority']??100),1000)),json_encode($caps,JSON_THROW_ON_ERROR),json_encode($input['routing_profile']??[],JSON_THROW_ON_ERROR),max(1,min((int)($input['max_concurrent_tasks']??1),20))]);
    $stmt=$pdo->prepare('SELECT tm.*,a.public_id agent_public_id FROM agent_team_members tm INNER JOIN agents a ON a.id=tm.agent_id WHERE tm.team_id=? AND tm.agent_id=?');$stmt->execute([(int)$team['id'],(int)$agent['id']]);return $stmt->fetch(PDO::FETCH_ASSOC);
}

function mg_swarm_validate_tasks(array $tasks): void
{
    $keys=[];$deps=[];
    foreach($tasks as $task){
        $key=trim((string)($task['task_key']??''));
        if($key===''||isset($keys[$key]))throw new InvalidArgumentException('Task keys must be unique and non-empty.');
        $keys[$key]=true;$deps[$key]=array_values(array_filter((array)($task['depends_on']??[]),'is_string'));
    }
    foreach($deps as $key=>$parents){foreach($parents as $parent){if(!isset($keys[$parent])||$parent===$key)throw new InvalidArgumentException('Task dependency graph is invalid.');}}
    $visiting=[];$visited=[];
    $visit=function(string $key)use(&$visit,&$visiting,&$visited,$deps):void{
        if(isset($visiting[$key]))throw new InvalidArgumentException('Task dependency graph contains a cycle.');
        if(isset($visited[$key]))return;
        $visiting[$key]=true;foreach($deps[$key] as $parent)$visit($parent);unset($visiting[$key]);$visited[$key]=true;
    };
    foreach(array_keys($keys) as $key)$visit($key);
}

function mg_swarm_create_run(PDO $pdo,int $userId,array $input): array
{
    $team=mg_swarm_team_owned($pdo,trim((string)($input['team_id']??'')),$userId,true);
    if((string)$team['status']!=='active')throw new RuntimeException('Agent team is not active.');
    $idempotencyKey=trim((string)($input['idempotency_key']??''));$objective=mb_substr(trim((string)($input['objective']??$team['objective'])),0,1000);
    $tasks=(array)($input['tasks']??[]);
    if($idempotencyKey===''||$objective===''||$tasks===[])throw new InvalidArgumentException('Run idempotency key, objective, and tasks are required.');
    mg_swarm_validate_tasks($tasks);
    $existing=$pdo->prepare('SELECT * FROM agent_swarm_runs WHERE owner_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');$existing->execute([$userId,$idempotencyKey]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC))return $row+['duplicate'=>true];
    $budget=max(1,(int)($input['budget_units']??$team['default_budget_units']));$public=mg_public_uuid();
    $pdo->prepare("INSERT INTO agent_swarm_runs (public_id,team_id,owner_user_id,idempotency_key,objective,status,budget_units,input_json,created_at,updated_at) VALUES (?,?,?,?,?,'planning',?,?,NOW(),NOW())")
        ->execute([$public,(int)$team['id'],$userId,$idempotencyKey,$objective,$budget,json_encode($input['input']??[],JSON_THROW_ON_ERROR)]);
    $runId=(int)$pdo->lastInsertId();$ids=[];$totalEstimate=0;
    foreach($tasks as $task){
        $taskPublic=mg_public_uuid();$estimate=max(0,(int)($task['estimated_units']??0));$totalEstimate+=$estimate;
        $strategyId=null;if(!empty($task['strategy_id'])){$strategy=mg_agent_strategy_owned($pdo,(string)$task['strategy_id'],$userId);$strategyId=(int)$strategy['id'];}
        $pdo->prepare("INSERT INTO agent_swarm_tasks (public_id,swarm_run_id,strategy_id,task_key,task_type,capability_key,objective,status,priority,requires_review,estimated_units,input_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'pending',?,?,?,?,NOW(),NOW())")
            ->execute([$taskPublic,$runId,$strategyId,(string)$task['task_key'],mb_substr((string)($task['task_type']??'general'),0,100),mb_substr((string)($task['capability_key']??'general'),0,100),mb_substr((string)($task['objective']??''),0,1000),max(1,min((int)($task['priority']??100),1000)),(bool)($task['requires_review']??false)?1:0,$estimate,json_encode($task['input']??[],JSON_THROW_ON_ERROR)]);
        $ids[(string)$task['task_key']]=(int)$pdo->lastInsertId();
    }
    if($totalEstimate>$budget)throw new RuntimeException('Swarm task estimates exceed the run budget.');
    foreach($tasks as $task){foreach((array)($task['depends_on']??[]) as $parent){$pdo->prepare("INSERT INTO agent_swarm_task_dependencies (swarm_run_id,task_id,depends_on_task_id,dependency_type,created_at) VALUES (?,?,?,?,NOW())")->execute([$runId,$ids[(string)$task['task_key']],$ids[(string)$parent],'success']);}}
    $pdo->prepare("UPDATE agent_swarm_tasks t SET status='ready' WHERE t.swarm_run_id=? AND NOT EXISTS (SELECT 1 FROM agent_swarm_task_dependencies d WHERE d.task_id=t.id)")->execute([$runId]);
    $pdo->prepare("UPDATE agent_swarm_runs SET status='queued',reserved_units=?,updated_at=NOW() WHERE id=?")->execute([$totalEstimate,$runId]);
    mg_swarm_event($pdo,$runId,null,'swarm_queued',$userId,['task_count'=>count($tasks),'reserved_units'=>$totalEstimate]);
    $stmt=$pdo->prepare('SELECT * FROM agent_swarm_runs WHERE id=?');$stmt->execute([$runId]);return $stmt->fetch(PDO::FETCH_ASSOC)+['duplicate'=>false];
}

function mg_swarm_route_task(PDO $pdo,array $task): array
{
    $stmt=$pdo->prepare("SELECT tm.*,a.public_id agent_public_id FROM agent_team_members tm INNER JOIN agent_swarm_runs sr ON sr.team_id=tm.team_id INNER JOIN agents a ON a.id=tm.agent_id WHERE sr.id=? AND tm.status='active' AND JSON_CONTAINS(tm.capabilities_json,JSON_QUOTE(?)) AND (SELECT COUNT(*) FROM agent_swarm_tasks active_tasks WHERE active_tasks.team_member_id=tm.id AND active_tasks.status IN ('routed','running','review_pending'))<tm.max_concurrent_tasks ORDER BY tm.priority ASC,tm.id ASC LIMIT 2");
    $stmt->execute([(int)$task['swarm_run_id'],(string)$task['capability_key']]);$members=$stmt->fetchAll(PDO::FETCH_ASSOC);
    if($members===[])throw new RuntimeException('No eligible agent team member is available.');
    if(count($members)>1&&(int)$members[0]['priority']===(int)$members[1]['priority']){
        $pdo->prepare("INSERT INTO agent_swarm_conflicts (public_id,swarm_run_id,task_id,conflict_type,status,summary,candidates_json,created_at,updated_at) VALUES (?,?,?,'routing','open',?,?,NOW(),NOW())")
            ->execute([mg_public_uuid(),(int)$task['swarm_run_id'],(int)$task['id'],'Multiple equally ranked agents can execute this task.',json_encode(array_column($members,'agent_public_id'),JSON_THROW_ON_ERROR)]);
    }
    $member=$members[0];
    $route=$pdo->prepare("SELECT route_key,provider_key,model_key,capability_key,estimated_unit_cost_micros,config_json FROM agent_provider_routes WHERE team_id=? AND capability_key=? AND status='active' ORDER BY priority ASC,id ASC LIMIT 1");
    $route->execute([(int)$member['team_id'],(string)$task['capability_key']]);$provider=$route->fetch(PDO::FETCH_ASSOC)?:null;
    return ['member'=>$member,'provider_route'=>$provider];
}

function mg_swarm_resolve_conflict(PDO $pdo,array $conflict,string $decision,int $userId,array $resolution=[]): void
{
    if((string)$conflict['status']!=='open')throw new RuntimeException('Swarm conflict is not open.');
    if(!in_array($decision,['resolve','dismiss'],true))throw new InvalidArgumentException('Invalid conflict decision.');
    $status=$decision==='resolve'?'resolved':'dismissed';
    $pdo->prepare('UPDATE agent_swarm_conflicts SET status=?,resolution_json=?,resolution_method=?,resolved_by_user_id=?,resolved_at=NOW(),updated_at=NOW() WHERE id=?')
        ->execute([$status,json_encode($resolution,JSON_THROW_ON_ERROR),mb_substr((string)($resolution['method']??'owner_decides'),0,100),$userId,(int)$conflict['id']]);
    mg_swarm_event($pdo,(int)$conflict['swarm_run_id'],$conflict['task_id']!==null?(int)$conflict['task_id']:null,'conflict_'.$status,$userId,['conflict_id'=>$conflict['public_id'],'resolution'=>$resolution]);
}
