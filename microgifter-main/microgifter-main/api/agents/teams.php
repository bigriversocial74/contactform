<?php
declare(strict_types=1);

require_once __DIR__ . '/_swarm.php';
$user=mg_require_permission('agent.teams.manage');
$pdo=mg_db();

if($_SERVER['REQUEST_METHOD']==='GET'){
    $stmt=$pdo->prepare("SELECT t.public_id,t.name,t.objective,t.status,t.coordination_mode,t.conflict_policy,t.max_parallel_tasks,t.default_budget_units,t.created_at,t.updated_at,(SELECT COUNT(*) FROM agent_team_members tm WHERE tm.team_id=t.id AND tm.status='active') active_members FROM agent_teams t WHERE t.owner_user_id=? ORDER BY t.updated_at DESC LIMIT 100");
    $stmt->execute([(int)$user['id']]);mg_ok(['teams'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

mg_require_method('POST');
$input=mg_input();mg_require_csrf_for_write($input);$action=trim((string)($input['action']??'create'));
$pdo->beginTransaction();
try{
    if($action==='create'){
        $team=mg_swarm_create_team($pdo,(int)$user['id'],$input);$result=['team'=>$team];$event='created';
    }else{
        $team=mg_swarm_team_owned($pdo,trim((string)($input['team_id']??'')),(int)$user['id'],true);
        if($action==='add_member'){$result=['member'=>mg_swarm_add_member($pdo,$team,(int)$user['id'],$input)];$event='member_added';}
        elseif($action==='add_route'){
            $routeKey=mb_substr(trim((string)($input['route_key']??'')),0,100);$provider=mb_substr(trim((string)($input['provider_key']??'')),0,100);$model=mb_substr(trim((string)($input['model_key']??'')),0,190);$capability=mb_substr(trim((string)($input['capability_key']??'')),0,100);
            if($routeKey===''||$provider===''||$model===''||$capability==='')throw new InvalidArgumentException('Route, provider, model, and capability are required.');
            $pdo->prepare("INSERT INTO agent_provider_routes (team_id,route_key,provider_key,model_key,capability_key,priority,max_input_units,max_output_units,estimated_unit_cost_micros,status,config_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'active',?,NOW(),NOW()) ON DUPLICATE KEY UPDATE provider_key=VALUES(provider_key),model_key=VALUES(model_key),capability_key=VALUES(capability_key),priority=VALUES(priority),max_input_units=VALUES(max_input_units),max_output_units=VALUES(max_output_units),estimated_unit_cost_micros=VALUES(estimated_unit_cost_micros),status='active',config_json=VALUES(config_json),updated_at=NOW()")
                ->execute([(int)$team['id'],$routeKey,$provider,$model,$capability,max(1,min((int)($input['priority']??100),1000)),isset($input['max_input_units'])?(int)$input['max_input_units']:null,isset($input['max_output_units'])?(int)$input['max_output_units']:null,max(0,(int)($input['estimated_unit_cost_micros']??0)),json_encode($input['config']??[],JSON_THROW_ON_ERROR)]);
            $result=['route_key'=>$routeKey];$event='route_added';
        }elseif(in_array($action,['activate','pause','retire','restore'],true)){
            $from=(string)$team['status'];$allowed=['activate'=>['draft','paused'],'pause'=>['active'],'retire'=>['draft','active','paused'],'restore'=>['retired']];
            if(!in_array($from,$allowed[$action],true))throw new RuntimeException('Team cannot perform this transition.');
            $to=match($action){'activate'=>'active','pause'=>'paused','retire'=>'retired','restore'=>'draft'};
            if($to==='active'){$count=$pdo->prepare("SELECT COUNT(*) FROM agent_team_members WHERE team_id=? AND status='active'");$count->execute([(int)$team['id']]);if((int)$count->fetchColumn()<1)throw new RuntimeException('An active team requires at least one active member.');}
            $pdo->prepare('UPDATE agent_teams SET status=?,updated_at=NOW() WHERE id=?')->execute([$to,(int)$team['id']]);$result=['team_id'=>$team['public_id'],'status'=>$to];$event=$action;
        }else throw new InvalidArgumentException('Invalid team action.');
    }
    $pdo->commit();mg_audit('agent.team_'.$event,'agent_team',$result,(int)$user['id']);mg_ok($result,'Agent team updated.',$action==='create'?201:200);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to update agent team.',500);}
