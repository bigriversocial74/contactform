<?php
declare(strict_types=1);

require_once __DIR__ . '/_workflow.php';
require_once __DIR__ . '/_swarm_workflow.php';
require_once dirname(__DIR__) . '/demand/_demand.php';
require_once dirname(__DIR__) . '/communications/_communications.php';

final class MgDemandOrchestrationException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus=409)
    {
        parent::__construct($message);
    }
}

function mg_demand_orchestration_event(PDO $pdo,int $orchestrationId,string $eventKey,string $eventType,array $payload=[]): void
{
    $pdo->prepare(
        'INSERT IGNORE INTO demand_signal_orchestration_events (public_id,orchestration_id,event_key,event_type,payload_json,created_at) VALUES (?,?,?,?,?,NOW())'
    )->execute([
        mg_public_uuid(),
        $orchestrationId,
        $eventKey,
        $eventType,
        json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
    ]);
}

function mg_demand_orchestration_level_rank(string $level): int
{
    return match($level){
        'critical'=>4,
        'warning'=>3,
        'opportunity'=>2,
        default=>1,
    };
}

function mg_demand_orchestration_config_matches(array $strategy,array $signal): ?array
{
    $config=json_decode((string)$strategy['trigger_config_json'],true,512,JSON_THROW_ON_ERROR);
    if(!is_array($config))$config=[];
    $keys=array_values(array_filter((array)($config['signal_keys']??[]),'is_string'));
    if($keys!==[]&&!in_array((string)$signal['signal_key'],$keys,true))return null;
    $minimumLevel=trim((string)($config['minimum_level']??'info'));
    if(mg_demand_orchestration_level_rank((string)$signal['signal_level'])<mg_demand_orchestration_level_rank($minimumLevel))return null;
    $minimumConfidence=max(0,min(1,(float)($config['minimum_confidence']??0)));
    if((float)$signal['confidence_score']<$minimumConfidence)return null;
    $mode=trim((string)($config['orchestration_mode']??'workflow'));
    if(!in_array($mode,['workflow','swarm'],true))return null;
    $config['orchestration_mode']=$mode;
    return $config;
}

function mg_demand_orchestration_match(PDO $pdo,array $signal): ?array
{
    $stmt=$pdo->prepare(
        "SELECT s.*,a.public_id agent_public_id
         FROM agent_strategies s
         INNER JOIN agents a ON a.id=s.agent_id
         WHERE s.owner_user_id=? AND s.status='active' AND s.trigger_type='demand_signal'
           AND a.user_id=s.owner_user_id AND a.lifecycle_status='active'
         ORDER BY s.id ASC
         FOR UPDATE"
    );
    $stmt->execute([(int)$signal['merchant_user_id']]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $strategy){
        $config=mg_demand_orchestration_config_matches($strategy,$signal);
        if($config===null)continue;
        $catalog=json_decode((string)$strategy['action_catalog_json'],true,512,JSON_THROW_ON_ERROR);
        if(!is_array($catalog)||!in_array('create_operational_alert',$catalog,true))continue;
        if(!in_array('acknowledge_demand_signal',$catalog,true))continue;
        $team=null;
        if($config['orchestration_mode']==='swarm'){
            $teamPublic=trim((string)($config['team_id']??''));
            $capability=trim((string)($config['capability_key']??'operations'))?:'operations';
            if($teamPublic==='')continue;
            $teamStmt=$pdo->prepare(
                "SELECT t.*
                 FROM agent_teams t
                 INNER JOIN agent_team_members tm ON tm.team_id=t.id
                 WHERE t.public_id=? AND t.owner_user_id=? AND t.status='active'
                   AND tm.agent_id=? AND tm.status='active'
                   AND JSON_CONTAINS(tm.capabilities_json,JSON_QUOTE(?))
                 LIMIT 1 FOR UPDATE"
            );
            $teamStmt->execute([$teamPublic,(int)$signal['merchant_user_id'],(int)$strategy['agent_id'],$capability]);
            $team=$teamStmt->fetch(PDO::FETCH_ASSOC)?:null;
            if($team===null)continue;
            $config['capability_key']=$capability;
        }
        return ['strategy'=>$strategy,'config'=>$config,'team'=>$team];
    }
    return null;
}

function mg_demand_orchestration_actions(array $signal,array $strategy): array
{
    $recommendation=json_decode((string)$signal['recommendation_json'],true,512,JSON_THROW_ON_ERROR);
    if(!is_array($recommendation))$recommendation=[];
    $recommendedAction=trim((string)($recommendation['action']??'review_demand_signal'))?:'review_demand_signal';
    $risk=match((string)$signal['signal_level']){
        'critical'=>'high',
        'warning'=>'medium',
        default=>'low',
    };
    $message=(string)$signal['summary'].' Recommendation: '.$recommendedAction.'. Review the demand dashboard before taking any business action.';
    return [
        [
            'action_type'=>'create_operational_alert',
            'target_type'=>'user',
            'target_reference'=>(string)$signal['merchant_user_id'],
            'risk_level'=>$risk,
            'reason'=>'A Stage 15 demand signal requires merchant review.',
            'request'=>[
                'title'=>'Demand signal: '.(string)$signal['signal_key'],
                'message'=>$message,
                'action_url'=>'/account.php?section=demand',
            ],
        ],
        [
            'action_type'=>'acknowledge_demand_signal',
            'target_type'=>'demand_signal',
            'target_reference'=>(string)$signal['public_id'],
            'risk_level'=>$risk,
            'reason'=>'Acknowledge only after the review alert is durably created.',
            'request'=>['recommendation_action'=>$recommendedAction],
        ],
    ];
}

function mg_demand_orchestration_alert_only(PDO $pdo,array $signal,string $reason): array
{
    $recommendation=json_decode((string)$signal['recommendation_json'],true,512,JSON_THROW_ON_ERROR);
    $action=trim((string)($recommendation['action']??'review_demand_signal'))?:'review_demand_signal';
    $public=mg_public_uuid();
    $dispatchKey=hash('sha256','demand-alert-only:'.(string)$signal['public_id']);
    $fingerprint=hash('sha256',json_encode([
        'signal_id'=>$signal['public_id'],'signal_key'=>$signal['signal_key'],'level'=>$signal['signal_level'],
        'recommendation'=>$recommendation,'reason'=>$reason,
    ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $pdo->prepare(
        "INSERT INTO demand_signal_orchestrations
         (public_id,demand_signal_id,strategy_id,strategy_version,team_id,orchestration_type,status,recommendation_action,dispatch_key,input_fingerprint,last_error,claimed_at,completed_at,created_at,updated_at)
         VALUES (?, ?, NULL, NULL, NULL, 'alert_only', 'review_required', ?, ?, ?, ?, NOW(), NOW(), NOW(), NOW())"
    )->execute([$public,(int)$signal['id'],$action,$dispatchKey,$fingerprint,mb_substr($reason,0,1000)]);
    $id=(int)$pdo->lastInsertId();
    mg_create_operational_alert(
        $pdo,
        (int)$signal['merchant_user_id'],
        'demand_signal_review',
        in_array((string)$signal['signal_level'],['warning','critical'],true)?'warning':'info',
        'Demand signal requires orchestration review',
        mb_substr((string)$signal['summary'].' Recommendation: '.$action.'. '.$reason,0,1000),
        '/account.php?section=demand',
        ['demand_signal_id'=>$signal['public_id'],'orchestration_id'=>$public,'recommendation_action'=>$action]
    );
    mg_demand_orchestration_event($pdo,$id,'review_required','review_required',['reason'=>$reason,'recommendation_action'=>$action]);
    return ['processed'=>true,'orchestration_id'=>$public,'type'=>'alert_only','status'=>'review_required','duplicate'=>false];
}

function mg_demand_orchestrate_signal(PDO $pdo,array $signal,?callable $failureHook=null): array
{
    $existing=$pdo->prepare('SELECT * FROM demand_signal_orchestrations WHERE demand_signal_id=? LIMIT 1 FOR UPDATE');
    $existing->execute([(int)$signal['id']]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC)){
        return ['processed'=>true,'orchestration_id'=>$row['public_id'],'type'=>$row['orchestration_type'],'status'=>$row['status'],'duplicate'=>true];
    }
    if((string)$signal['status']!=='open')throw new MgDemandOrchestrationException('Demand signal is not open.');
    if($signal['expires_at']!==null&&strtotime((string)$signal['expires_at'])<=time())throw new MgDemandOrchestrationException('Demand signal has expired.');

    $match=mg_demand_orchestration_match($pdo,$signal);
    if($match===null)return mg_demand_orchestration_alert_only($pdo,$signal,'No active merchant-owned demand strategy can safely translate this recommendation.');

    $strategy=$match['strategy'];$config=$match['config'];$team=$match['team'];
    $actions=mg_demand_orchestration_actions($signal,$strategy);
    $recommendation=json_decode((string)$signal['recommendation_json'],true,512,JSON_THROW_ON_ERROR);
    $recommendedAction=trim((string)($recommendation['action']??'review_demand_signal'))?:'review_demand_signal';
    $mode=(string)$config['orchestration_mode'];
    $dispatchKey=hash('sha256',implode(':',[
        'demand-signal',(string)$signal['public_id'],(string)$strategy['public_id'],'v'.(string)$strategy['version_no'],$mode,
    ]));
    $fingerprint=hash('sha256',json_encode([
        'signal_id'=>$signal['public_id'],'strategy_id'=>$strategy['public_id'],'strategy_version'=>(int)$strategy['version_no'],
        'mode'=>$mode,'config'=>$config,'actions'=>$actions,
    ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $public=mg_public_uuid();
    $pdo->prepare(
        "INSERT INTO demand_signal_orchestrations
         (public_id,demand_signal_id,strategy_id,strategy_version,team_id,orchestration_type,status,recommendation_action,dispatch_key,input_fingerprint,claimed_at,created_at,updated_at)
         VALUES (?,?,?,?,?,?, 'claimed', ?,?,?,NOW(),NOW(),NOW())"
    )->execute([
        $public,(int)$signal['id'],(int)$strategy['id'],(int)$strategy['version_no'],$team!==null?(int)$team['id']:null,
        $mode,$recommendedAction,$dispatchKey,$fingerprint,
    ]);
    $orchestrationId=(int)$pdo->lastInsertId();
    mg_demand_orchestration_event($pdo,$orchestrationId,'claimed','claimed',['signal_id'=>$signal['public_id'],'strategy_id'=>$strategy['public_id'],'mode'=>$mode]);

    if($mode==='swarm'){
        $estimated=max(1,min((int)($config['estimated_units']??100),100000));
        $requiresReview=(bool)($config['requires_review']??false);
        $run=mg_swarm_create_run($pdo,(int)$signal['merchant_user_id'],[
            'team_id'=>$team['public_id'],
            'idempotency_key'=>'demand:'.$dispatchKey,
            'objective'=>'Review and acknowledge demand signal '.(string)$signal['signal_key'],
            'budget_units'=>max($estimated,(int)($config['budget_units']??$estimated)),
            'input'=>['demand_signal_id'=>$signal['public_id'],'recommendation'=>$recommendation,'orchestration_id'=>$public],
            'tasks'=>[[
                'task_key'=>'demand-response','task_type'=>'demand_signal','capability_key'=>(string)$config['capability_key'],
                'objective'=>'Create a merchant review alert and acknowledge the demand signal.',
                'strategy_id'=>$strategy['public_id'],'requires_review'=>$requiresReview,'estimated_units'=>$estimated,
                'input'=>['actions'=>$actions,'demand_signal_id'=>$signal['public_id'],'recommendation'=>$recommendation],
            ]],
        ]);
        $pdo->prepare("UPDATE demand_signal_orchestrations SET swarm_run_id=?,status='running',started_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([(int)$run['id'],$orchestrationId]);
        mg_demand_orchestration_event($pdo,$orchestrationId,'swarm_created','swarm_created',['swarm_run_id'=>$run['public_id']]);
        if($failureHook)$failureHook('after_orchestration_created',['orchestration_id'=>$public,'swarm_run_id'=>$run['public_id']]);
        return ['processed'=>true,'orchestration_id'=>$public,'type'=>'swarm','status'=>'running','swarm_run_id'=>$run['public_id'],'duplicate'=>false];
    }

    $run=mg_agent_create_run($pdo,(int)$signal['merchant_user_id'],[
        'strategy_id'=>$strategy['public_id'],'idempotency_key'=>'demand:'.$dispatchKey,
        'trigger_type'=>'demand_signal','trigger_reference'=>$signal['public_id'],
        'input'=>['demand_signal_id'=>$signal['public_id'],'recommendation'=>$recommendation,'orchestration_id'=>$public],
    ]);
    $plan=mg_agent_plan_run($pdo,$run,$actions,(int)$signal['merchant_user_id']);
    $status=(string)$plan['status']==='approval_pending'?'awaiting_approval':'running';
    $pdo->prepare('UPDATE demand_signal_orchestrations SET workflow_run_id=?,status=?,started_at=NOW(),updated_at=NOW() WHERE id=?')
        ->execute([(int)$run['id'],$status,$orchestrationId]);
    mg_demand_orchestration_event($pdo,$orchestrationId,'workflow_created','workflow_created',['workflow_run_id'=>$run['public_id'],'status'=>$status]);
    if($failureHook)$failureHook('after_orchestration_created',['orchestration_id'=>$public,'workflow_run_id'=>$run['public_id']]);
    return ['processed'=>true,'orchestration_id'=>$public,'type'=>'workflow','status'=>$status,'workflow_run_id'=>$run['public_id'],'duplicate'=>false];
}

function mg_demand_orchestrate_next_signal(PDO $pdo,?callable $failureHook=null): array
{
    $stmt=$pdo->query(
        "SELECT s.*
         FROM demand_agent_signals s
         LEFT JOIN demand_signal_orchestrations o ON o.demand_signal_id=s.id
         WHERE s.status='open' AND (s.expires_at IS NULL OR s.expires_at>NOW()) AND o.id IS NULL
         ORDER BY FIELD(s.signal_level,'critical','warning','opportunity','info'),s.triggered_at,s.id
         LIMIT 1 FOR UPDATE SKIP LOCKED"
    );
    $signal=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$signal)return ['processed'=>false];
    return mg_demand_orchestrate_signal($pdo,$signal,$failureHook);
}

function mg_demand_reconcile_next_orchestration(PDO $pdo): array
{
    $stmt=$pdo->query(
        "SELECT o.*,s.status signal_status,s.public_id signal_public_id
         FROM demand_signal_orchestrations o
         INNER JOIN demand_agent_signals s ON s.id=o.demand_signal_id
         WHERE o.status IN ('claimed','awaiting_approval','running')
         ORDER BY o.id ASC LIMIT 1 FOR UPDATE SKIP LOCKED"
    );
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)return ['processed'=>false];
    $status=(string)$row['status'];$error=null;$sourceStatus=null;
    if((string)$row['orchestration_type']==='workflow'){
        $run=$pdo->prepare('SELECT status,failure_message FROM agent_workflow_runs WHERE id=? LIMIT 1 FOR UPDATE');
        $run->execute([(int)$row['workflow_run_id']]);$source=$run->fetch(PDO::FETCH_ASSOC);
        if(!$source)throw new MgDemandOrchestrationException('Demand workflow run is missing.');
        $sourceStatus=(string)$source['status'];
        $status=match($sourceStatus){
            'approval_pending'=>'awaiting_approval',
            'completed'=>(string)$row['signal_status']==='open'?'failed':'completed',
            'partially_completed','failed','canceled'=>'failed',
            default=>'running',
        };
        if($status==='failed')$error=(string)($source['failure_message']??'Demand workflow did not complete and acknowledge its signal.');
    }elseif((string)$row['orchestration_type']==='swarm'){
        $run=$pdo->prepare('SELECT status,failure_message FROM agent_swarm_runs WHERE id=? LIMIT 1 FOR UPDATE');
        $run->execute([(int)$row['swarm_run_id']]);$source=$run->fetch(PDO::FETCH_ASSOC);
        if(!$source)throw new MgDemandOrchestrationException('Demand swarm run is missing.');
        $sourceStatus=(string)$source['status'];
        $status=match($sourceStatus){
            'approval_pending','blocked'=>'awaiting_approval',
            'completed'=>(string)$row['signal_status']==='open'?'failed':'completed',
            'partially_completed','failed','canceled'=>'failed',
            default=>'running',
        };
        if($status==='failed')$error=(string)($source['failure_message']??'Demand swarm did not complete and acknowledge its signal.');
    }
    $terminal=in_array($status,['completed','failed'],true);
    $pdo->prepare('UPDATE demand_signal_orchestrations SET status=?,last_error=?,completed_at=IF(?,COALESCE(completed_at,NOW()),completed_at),updated_at=NOW() WHERE id=?')
        ->execute([$status,$error,$terminal?1:0,(int)$row['id']]);
    mg_demand_orchestration_event($pdo,(int)$row['id'],'status_'.$status,'status_'.$status,['source_status'=>$sourceStatus,'signal_status'=>$row['signal_status'],'error'=>$error]);
    return ['processed'=>true,'orchestration_id'=>$row['public_id'],'status'=>$status,'source_status'=>$sourceStatus,'duplicate'=>false];
}
