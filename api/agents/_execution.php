<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/communications/_communications.php';

const MG_AGENT_ACTIONS = [
    'acknowledge_demand_signal',
    'resolve_demand_signal',
    'pause_distribution_program',
    'resume_distribution_program',
    'create_operational_alert',
];
const MG_AGENT_STRATEGY_TRIGGERS = ['manual','demand_signal','schedule','event'];
const MG_AGENT_STRATEGY_STATUSES = ['draft','active','paused','retired'];

function mg_agent_execution_event(PDO $pdo,int $runId,?int $actionId,string $type,?int $actor,array $payload=[]): void
{
    $pdo->prepare('INSERT INTO agent_execution_events (public_id,run_id,action_id,event_type,actor_user_id,payload_json,created_at) VALUES (?,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(),$runId,$actionId,$type,$actor,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
}

function mg_agent_json_array(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value)==='') return [];
    $decoded=json_decode($value,true);
    return is_array($decoded)?$decoded:[];
}

function mg_agent_owned(PDO $pdo,string $publicId,int $userId): array
{
    $stmt=$pdo->prepare("SELECT * FROM agents WHERE public_id=? AND user_id=? AND lifecycle_status='active' LIMIT 1");
    $stmt->execute([$publicId,$userId]);$agent=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$agent)throw new RuntimeException('Agent not found.');
    return $agent;
}

function mg_agent_strategy_owned(PDO $pdo,string $publicId,int $userId,bool $forUpdate=false): array
{
    if(preg_match('/^[a-f0-9-]{36}$/i',$publicId)!==1)throw new InvalidArgumentException('Strategy is required.');
    $sql='SELECT s.*,a.public_id agent_public_id,a.name agent_name,a.runtime_status agent_runtime_status,a.lifecycle_status agent_lifecycle_status FROM agent_strategies s INNER JOIN agents a ON a.id=s.agent_id WHERE s.public_id=? AND s.owner_user_id=? AND a.user_id=s.owner_user_id LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute([$publicId,$userId]);$strategy=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$strategy)throw new RuntimeException('Agent strategy not found.');
    return $strategy;
}

function mg_agent_strategy_projection(array $row): array
{
    return [
        'id'=>(string)$row['public_id'],
        'agent'=>[
            'id'=>(string)($row['agent_public_id']??''),
            'name'=>(string)($row['agent_name']??'Agent'),
            'runtime_status'=>(string)($row['agent_runtime_status']??'paused'),
        ],
        'name'=>(string)$row['name'],
        'objective'=>(string)$row['objective'],
        'status'=>(string)$row['status'],
        'trigger'=>[
            'type'=>(string)$row['trigger_type'],
            'config'=>mg_agent_json_array($row['trigger_config_json']??null),
        ],
        'policy'=>mg_agent_json_array($row['policy_json']??null),
        'action_catalog'=>array_values(array_filter(mg_agent_json_array($row['action_catalog_json']??null),'is_string')),
        'max_actions_per_run'=>(int)$row['max_actions_per_run'],
        'requires_approval'=>(bool)$row['requires_approval'],
        'version'=>(int)$row['version_no'],
        'created_at'=>(string)$row['created_at'],
        'updated_at'=>(string)$row['updated_at'],
        'permissions'=>[
            'can_edit'=>in_array((string)$row['status'],['draft','paused'],true),
            'can_activate'=>in_array((string)$row['status'],['draft','paused'],true),
            'can_pause'=>(string)$row['status']==='active',
            'can_retire'=>(string)$row['status']!=='retired',
        ],
    ];
}

function mg_agent_strategy_values(array $input,?array $existing=null): array
{
    $name=mb_substr(trim((string)($input['name']??($existing['name']??''))),0,190);
    $objective=mb_substr(trim((string)($input['objective']??($existing['objective']??''))),0,500);
    $triggerType=trim((string)($input['trigger_type']??($existing['trigger_type']??'manual')));
    $triggerConfig=array_key_exists('trigger_config',$input)?(array)$input['trigger_config']:mg_agent_json_array($existing['trigger_config_json']??null);
    $policy=array_key_exists('policy',$input)?(array)$input['policy']:mg_agent_json_array($existing['policy_json']??null);
    $actions=array_key_exists('action_catalog',$input)?(array)$input['action_catalog']:mg_agent_json_array($existing['action_catalog_json']??null);
    $actions=array_values(array_unique(array_filter(array_map(static fn($value):string=>trim((string)$value),$actions))));
    $maximum=max(1,min((int)($input['max_actions_per_run']??($existing['max_actions_per_run']??10)),50));
    $requires=array_key_exists('requires_approval',$input)?(bool)$input['requires_approval']:(bool)($existing['requires_approval']??true);
    if($name===''||$objective==='')throw new InvalidArgumentException('Strategy name and objective are required.');
    if(!in_array($triggerType,MG_AGENT_STRATEGY_TRIGGERS,true))throw new InvalidArgumentException('Invalid strategy trigger.');
    if($actions===[]||array_diff($actions,MG_AGENT_ACTIONS)!==[])throw new InvalidArgumentException('Strategy contains unsupported actions.');
    if(count($triggerConfig)>50||count($policy)>50)throw new InvalidArgumentException('Strategy configuration is too large.');
    return compact('name','objective','triggerType','triggerConfig','policy','actions','maximum','requires');
}

function mg_agent_strategy_load(PDO $pdo,int $strategyId): array
{
    $stmt=$pdo->prepare('SELECT s.*,a.public_id agent_public_id,a.name agent_name,a.runtime_status agent_runtime_status,a.lifecycle_status agent_lifecycle_status FROM agent_strategies s INNER JOIN agents a ON a.id=s.agent_id WHERE s.id=? LIMIT 1');
    $stmt->execute([$strategyId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Agent strategy could not be loaded.');
    return $row;
}

function mg_agent_create_strategy(PDO $pdo,int $userId,array $input): array
{
    $agent=mg_agent_owned($pdo,trim((string)($input['agent_id']??'')),$userId);
    $values=mg_agent_strategy_values($input);
    $publicId=mg_public_uuid();
    $pdo->prepare("INSERT INTO agent_strategies (public_id,agent_id,owner_user_id,name,objective,status,trigger_type,trigger_config_json,policy_json,action_catalog_json,max_actions_per_run,requires_approval,version_no,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,'draft',?,?,?,?,?,?,1,?,NOW(),NOW())")
        ->execute([$publicId,(int)$agent['id'],$userId,$values['name'],$values['objective'],$values['triggerType'],json_encode($values['triggerConfig'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($values['policy'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($values['actions'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$values['maximum'],$values['requires']?1:0,$userId]);
    return mg_agent_strategy_load($pdo,(int)$pdo->lastInsertId());
}

function mg_agent_update_strategy(PDO $pdo,int $userId,array $input): array
{
    $strategy=mg_agent_strategy_owned($pdo,trim((string)($input['strategy_id']??'')),$userId,true);
    if(!in_array((string)$strategy['status'],['draft','paused'],true))throw new RuntimeException('Active or retired strategies cannot be edited. Pause an active strategy first.');
    $expected=(int)($input['version']??0);
    if($expected<1||$expected!==(int)$strategy['version_no'])throw new RuntimeException('Strategy changed since it was loaded. Refresh and try again.');
    $agent=$strategy;
    if(isset($input['agent_id'])&&trim((string)$input['agent_id'])!=='')$agent=mg_agent_owned($pdo,trim((string)$input['agent_id']),$userId);
    $values=mg_agent_strategy_values($input,$strategy);
    $stmt=$pdo->prepare('UPDATE agent_strategies SET agent_id=?,name=?,objective=?,trigger_type=?,trigger_config_json=?,policy_json=?,action_catalog_json=?,max_actions_per_run=?,requires_approval=?,version_no=version_no+1,updated_at=NOW() WHERE id=? AND owner_user_id=? AND version_no=?');
    $stmt->execute([(int)$agent['id'],$values['name'],$values['objective'],$values['triggerType'],json_encode($values['triggerConfig'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($values['policy'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),json_encode($values['actions'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$values['maximum'],$values['requires']?1:0,(int)$strategy['id'],$userId,$expected]);
    if($stmt->rowCount()!==1)throw new RuntimeException('Strategy update conflicted with another change.');
    return mg_agent_strategy_load($pdo,(int)$strategy['id']);
}

function mg_agent_transition_strategy(PDO $pdo,int $userId,array $input,string $targetStatus): array
{
    if(!in_array($targetStatus,['active','paused','retired'],true))throw new InvalidArgumentException('Invalid strategy transition.');
    $strategy=mg_agent_strategy_owned($pdo,trim((string)($input['strategy_id']??'')),$userId,true);
    $expected=(int)($input['version']??0);
    if($expected<1||$expected!==(int)$strategy['version_no'])throw new RuntimeException('Strategy changed since it was loaded. Refresh and try again.');
    $current=(string)$strategy['status'];
    if($current===$targetStatus)return $strategy+['duplicate'=>true];
    $allowed=match($targetStatus){
        'active'=>in_array($current,['draft','paused'],true),
        'paused'=>$current==='active',
        'retired'=>$current!=='retired',
        default=>false,
    };
    if(!$allowed)throw new RuntimeException('Strategy cannot move from '.$current.' to '.$targetStatus.'.');
    if($targetStatus==='active'&&(string)$strategy['agent_lifecycle_status']!=='active')throw new RuntimeException('Archived agents cannot run active strategies.');
    $stmt=$pdo->prepare('UPDATE agent_strategies SET status=?,version_no=version_no+1,updated_at=NOW() WHERE id=? AND owner_user_id=? AND version_no=?');
    $stmt->execute([$targetStatus,(int)$strategy['id'],$userId,$expected]);
    if($stmt->rowCount()!==1)throw new RuntimeException('Strategy transition conflicted with another change.');
    return mg_agent_strategy_load($pdo,(int)$strategy['id'])+['duplicate'=>false];
}

function mg_agent_create_run(PDO $pdo,int $userId,array $input): array
{
    $strategy=mg_agent_strategy_owned($pdo,trim((string)($input['strategy_id']??'')),$userId,true);
    if((string)$strategy['status']!=='active')throw new RuntimeException('Agent strategy is not active.');
    $idempotencyKey=trim((string)($input['idempotency_key']??''));
    if($idempotencyKey==='')throw new InvalidArgumentException('Idempotency key is required.');
    $existing=$pdo->prepare('SELECT * FROM agent_workflow_runs WHERE owner_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([$userId,$idempotencyKey]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC))return $row+['duplicate'=>true];
    $publicId=mg_public_uuid();
    $pdo->prepare("INSERT INTO agent_workflow_runs (public_id,strategy_id,agent_id,owner_user_id,trigger_type,trigger_reference,idempotency_key,status,input_json,requested_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'queued',?,NOW(),NOW(),NOW())")
        ->execute([$publicId,(int)$strategy['id'],(int)$strategy['agent_id'],$userId,trim((string)($input['trigger_type']??$strategy['trigger_type'])),trim((string)($input['trigger_reference']??''))?:null,$idempotencyKey,json_encode($input['input']??[],JSON_THROW_ON_ERROR)]);
    $id=(int)$pdo->lastInsertId();mg_agent_execution_event($pdo,$id,null,'run_queued',$userId,['strategy_id'=>$strategy['public_id']]);
    $stmt=$pdo->prepare('SELECT * FROM agent_workflow_runs WHERE id=?');$stmt->execute([$id]);return $stmt->fetch(PDO::FETCH_ASSOC)+['duplicate'=>false];
}

function mg_agent_plan_run(PDO $pdo,array $run,array $actions,int $actorUserId): array
{
    if((string)$run['status']!=='queued')throw new RuntimeException('Run is not available for planning.');
    $stmt=$pdo->prepare('SELECT s.* FROM agent_strategies s WHERE s.id=? LIMIT 1 FOR UPDATE');$stmt->execute([(int)$run['strategy_id']]);$strategy=$stmt->fetch(PDO::FETCH_ASSOC);
    $catalog=json_decode((string)$strategy['action_catalog_json'],true,512,JSON_THROW_ON_ERROR);
    if(count($actions)>max(1,(int)$strategy['max_actions_per_run']))throw new InvalidArgumentException('Run exceeds strategy action limit.');
    $pdo->prepare("UPDATE agent_workflow_runs SET status='planning',started_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$run['id']]);
    $planned=[];$sequence=0;
    foreach($actions as $action){
        $sequence++;$type=trim((string)($action['action_type']??''));$targetType=trim((string)($action['target_type']??''));$targetRef=trim((string)($action['target_reference']??''));
        if(!in_array($type,$catalog,true)||!in_array($type,MG_AGENT_ACTIONS,true)||$targetRef==='')throw new InvalidArgumentException('Planned action is not allowed by strategy.');
        $risk=trim((string)($action['risk_level']??'medium'));if(!in_array($risk,['low','medium','high','critical'],true))throw new InvalidArgumentException('Invalid action risk level.');
        $requires=(bool)$strategy['requires_approval']||in_array($risk,['high','critical'],true);
        $status=$requires?'approval_pending':'approved';$public=mg_public_uuid();$idem=hash('sha256',$run['public_id'].':'.$sequence.':'.$type.':'.$targetRef);
        $pdo->prepare('INSERT INTO agent_workflow_actions (public_id,run_id,sequence_no,action_type,target_type,target_reference,status,risk_level,requires_approval,idempotency_key,request_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')
            ->execute([$public,(int)$run['id'],$sequence,$type,$targetType,$targetRef,$status,$risk,$requires?1:0,$idem,json_encode($action['request']??[],JSON_THROW_ON_ERROR)]);
        $actionId=(int)$pdo->lastInsertId();
        if($requires)$pdo->prepare("INSERT INTO agent_approval_requests (public_id,run_id,action_id,owner_user_id,status,requested_reason,requested_at,expires_at,created_at,updated_at) VALUES (?,?,?,?,'pending',?,NOW(),DATE_ADD(NOW(),INTERVAL 7 DAY),NOW(),NOW())")
            ->execute([mg_public_uuid(),(int)$run['id'],$actionId,(int)$run['owner_user_id'],mb_substr((string)($action['reason']??'Agent action requires approval.'),0,1000)]);
        $planned[]=$public;mg_agent_execution_event($pdo,(int)$run['id'],$actionId,'action_planned',$actorUserId,['action_type'=>$type,'requires_approval'=>$requires]);
    }
    $runStatus=$actions!==[]&&((bool)$strategy['requires_approval']||array_filter($actions,static fn(array $action):bool=>in_array((string)($action['risk_level']??'medium'),['high','critical'],true))!==[])?'approval_pending':'approved';
    $plan=['strategy_id'=>(string)$strategy['public_id'],'strategy_version'=>(int)$strategy['version_no'],'strategy_objective'=>(string)$strategy['objective'],'trigger_type'=>(string)$run['trigger_type'],'trigger_reference'=>$run['trigger_reference']??null,'actions'=>$planned,'planned_at'=>gmdate('c')];
    $pdo->prepare('UPDATE agent_workflow_runs SET status=?,plan_json=?,updated_at=NOW() WHERE id=?')->execute([$runStatus,json_encode($plan,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),(int)$run['id']]);
    return ['run_id'=>$run['public_id'],'status'=>$runStatus,'actions'=>$planned,'strategy_version'=>(int)$strategy['version_no']];
}

function mg_agent_execute_action(PDO $pdo,array $action,int $actorUserId): array
{
    $request=json_decode((string)$action['request_json'],true,512,JSON_THROW_ON_ERROR);
    $type=(string)$action['action_type'];$target=(string)$action['target_reference'];
    if($type==='acknowledge_demand_signal'||$type==='resolve_demand_signal'){
        $to=$type==='acknowledge_demand_signal'?'acknowledged':'resolved';
        $stmt=$pdo->prepare("UPDATE demand_agent_signals SET status=?,acknowledged_at=IF(?='acknowledged',NOW(),acknowledged_at),resolved_at=IF(?='resolved',NOW(),resolved_at),updated_at=NOW() WHERE public_id=? AND merchant_user_id=? AND status IN ('open','acknowledged')");
        $stmt->execute([$to,$to,$to,$target,(int)$action['owner_user_id']]);if($stmt->rowCount()<1)throw new RuntimeException('Demand signal is not executable.');
        return ['status'=>$to];
    }
    if($type==='pause_distribution_program'||$type==='resume_distribution_program'){
        $from=$type==='pause_distribution_program'?'active':'paused';$to=$type==='pause_distribution_program'?'paused':'active';
        $stmt=$pdo->prepare('UPDATE distribution_programs SET status=?,updated_at=NOW() WHERE public_id=? AND merchant_user_id=? AND status=?');
        $stmt->execute([$to,$target,(int)$action['owner_user_id'],$from]);if($stmt->rowCount()<1)throw new RuntimeException('Distribution program is not executable.');
        return ['status'=>$to];
    }
    if($type==='create_operational_alert'){
        mg_create_operational_alert($pdo,(int)$action['owner_user_id'],'agent_action','info',mb_substr((string)($request['title']??'Agent recommendation'),0,190),mb_substr((string)($request['message']??'An agent action requires attention.'),0,1000),(string)($request['action_url']??'/'),['agent_action_id'=>$action['public_id']]);
        return ['created'=>true];
    }
    throw new RuntimeException('Unsupported agent action.');
}
