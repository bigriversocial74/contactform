<?php
declare(strict_types=1);

require_once __DIR__ . '/_operations.php';
require_once dirname(__DIR__) . '/agents/_demand_orchestration.php';

function mg_operations_orchestration_load(PDO $pdo,string $publicId): array
{
    $stmt=$pdo->prepare(
        "SELECT o.*,s.public_id signal_public_id,s.merchant_user_id,s.signal_key,s.signal_level,s.status signal_status,
                s.summary signal_summary,s.recommendation_json,s.confidence_score,s.expires_at signal_expires_at
         FROM demand_signal_orchestrations o
         INNER JOIN demand_agent_signals s ON s.id=o.demand_signal_id
         WHERE o.public_id=? LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$publicId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Demand orchestration not found.');
    return $row;
}

function mg_operations_orchestration_retry_match(PDO $pdo,array $root): array
{
    $signal=[
        'id'=>(int)$root['demand_signal_id'],
        'public_id'=>(string)$root['signal_public_id'],
        'merchant_user_id'=>(int)$root['merchant_user_id'],
        'signal_key'=>(string)$root['signal_key'],
        'signal_level'=>(string)$root['signal_level'],
        'status'=>(string)$root['signal_status'],
        'summary'=>(string)$root['signal_summary'],
        'recommendation_json'=>(string)$root['recommendation_json'],
        'confidence_score'=>(float)$root['confidence_score'],
        'expires_at'=>$root['signal_expires_at'],
    ];
    if($root['strategy_id']===null){
        $match=mg_demand_orchestration_match($pdo,$signal);
        if($match===null)throw new RuntimeException('No active merchant-owned strategy is available for retry.');
        return ['signal'=>$signal]+$match;
    }
    $stmt=$pdo->prepare(
        "SELECT s.*,a.public_id agent_public_id
         FROM agent_strategies s
         INNER JOIN agents a ON a.id=s.agent_id
         WHERE s.id=? AND s.owner_user_id=? AND s.version_no=? AND s.status='active'
           AND a.user_id=s.owner_user_id AND a.lifecycle_status='active'
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([(int)$root['strategy_id'],(int)$root['merchant_user_id'],(int)$root['strategy_version']]);
    $strategy=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$strategy)throw new RuntimeException('The original strategy version is no longer active and cannot be retried safely.');
    $config=mg_demand_orchestration_config_matches($strategy,$signal);
    if($config===null)throw new RuntimeException('The original strategy no longer matches the demand signal.');
    $team=null;
    if((string)$root['orchestration_type']==='swarm'){
        if($root['team_id']===null)throw new RuntimeException('The original swarm team is missing.');
        $teamStmt=$pdo->prepare("SELECT * FROM agent_teams WHERE id=? AND owner_user_id=? AND status='active' LIMIT 1 FOR UPDATE");
        $teamStmt->execute([(int)$root['team_id'],(int)$root['merchant_user_id']]);
        $team=$teamStmt->fetch(PDO::FETCH_ASSOC)?:null;
        if($team===null)throw new RuntimeException('The original swarm team is no longer active.');
        $config['orchestration_mode']='swarm';
        $config['capability_key']=trim((string)($config['capability_key']??'operations'))?:'operations';
    }
    return ['signal'=>$signal,'strategy'=>$strategy,'config'=>$config,'team'=>$team];
}

function mg_operations_retry_demand_orchestration(PDO $pdo,int $adminUserId,string $publicId,string $idempotencyKey,string $reason,?callable $failureHook=null): array
{
    $idempotencyKey=trim($idempotencyKey);
    $reason=mb_substr(trim($reason),0,1000);
    if($idempotencyKey===''||$reason==='')throw new InvalidArgumentException('Retry idempotency key and reason are required.');
    $root=mg_operations_orchestration_load($pdo,$publicId);
    if(!in_array((string)$root['status'],['failed','review_required'],true))throw new RuntimeException('Only failed or review-required orchestrations can be retried.');
    if((string)$root['signal_status']!=='open')throw new RuntimeException('The demand signal is no longer open.');
    if($root['signal_expires_at']!==null&&strtotime((string)$root['signal_expires_at'])<=time())throw new RuntimeException('The demand signal has expired.');

    $existing=$pdo->prepare('SELECT * FROM demand_signal_orchestration_attempts WHERE orchestration_id=? AND request_idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([(int)$root['id'],$idempotencyKey]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC))return $row+['duplicate'=>true,'orchestration_id'=>$root['public_id']];

    $active=$pdo->prepare("SELECT COUNT(*) FROM demand_signal_orchestration_attempts WHERE orchestration_id=? AND status IN ('claimed','awaiting_approval','running')");
    $active->execute([(int)$root['id']]);
    if((int)$active->fetchColumn()>0)throw new RuntimeException('An orchestration attempt is already active.');

    $match=mg_operations_orchestration_retry_match($pdo,$root);
    $signal=$match['signal'];$strategy=$match['strategy'];$config=$match['config'];$team=$match['team'];
    $mode=(string)$config['orchestration_mode'];
    $actions=mg_demand_orchestration_actions($signal,$strategy);
    $attemptNo=(int)$pdo->query('SELECT COALESCE(MAX(attempt_no),0)+1 FROM demand_signal_orchestration_attempts WHERE orchestration_id='.(int)$root['id'])->fetchColumn();
    $dispatchKey=hash('sha256','demand-retry:'.$root['public_id'].':'.$attemptNo.':'.$strategy['public_id'].':v'.$strategy['version_no'].':'.$mode);
    $fingerprint=hash('sha256',json_encode([
        'root_dispatch_key'=>$root['dispatch_key'],'root_fingerprint'=>$root['input_fingerprint'],
        'signal_id'=>$signal['public_id'],'strategy_id'=>$strategy['public_id'],'strategy_version'=>(int)$strategy['version_no'],
        'mode'=>$mode,'config'=>$config,'actions'=>$actions,
    ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $attemptPublic=mg_public_uuid();
    $pdo->prepare(
        "INSERT INTO demand_signal_orchestration_attempts
         (public_id,orchestration_id,attempt_no,strategy_id,strategy_version,team_id,orchestration_type,status,dispatch_key,input_fingerprint,request_idempotency_key,requested_by_user_id,requested_reason,started_at,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,'claimed',?,?,?,?,?,NOW(),NOW(),NOW())"
    )->execute([
        $attemptPublic,(int)$root['id'],$attemptNo,(int)$strategy['id'],(int)$strategy['version_no'],$team!==null?(int)$team['id']:null,
        $mode,$dispatchKey,$fingerprint,$idempotencyKey,$adminUserId,$reason,
    ]);
    $attemptId=(int)$pdo->lastInsertId();
    mg_demand_orchestration_event($pdo,(int)$root['id'],'retry_'.$attemptNo.'_requested','retry_requested',[
        'attempt_id'=>$attemptPublic,'attempt_no'=>$attemptNo,'requested_by_user_id'=>$adminUserId,'reason'=>$reason,
    ]);

    $workflowId=null;$swarmId=null;$status='running';
    $recommendation=json_decode((string)$signal['recommendation_json'],true,512,JSON_THROW_ON_ERROR);
    if($mode==='swarm'){
        $estimated=max(1,min((int)($config['estimated_units']??100),100000));
        $run=mg_swarm_create_run($pdo,(int)$signal['merchant_user_id'],[
            'team_id'=>$team['public_id'],'idempotency_key'=>'demand-retry:'.$dispatchKey,
            'objective'=>'Retry review and acknowledgement of demand signal '.$signal['signal_key'],
            'budget_units'=>max($estimated,(int)($config['budget_units']??$estimated)),
            'input'=>['demand_signal_id'=>$signal['public_id'],'recommendation'=>$recommendation,'orchestration_id'=>$root['public_id'],'attempt_id'=>$attemptPublic],
            'tasks'=>[[
                'task_key'=>'demand-response','task_type'=>'demand_signal','capability_key'=>(string)($config['capability_key']??'operations'),
                'objective'=>'Create a merchant review alert and acknowledge the demand signal.',
                'strategy_id'=>$strategy['public_id'],'requires_review'=>(bool)($config['requires_review']??false),'estimated_units'=>$estimated,
                'input'=>['actions'=>$actions,'demand_signal_id'=>$signal['public_id'],'recommendation'=>$recommendation,'attempt_id'=>$attemptPublic],
            ]],
        ]);
        $swarmId=(int)$run['id'];
    }else{
        $run=mg_agent_create_run($pdo,(int)$signal['merchant_user_id'],[
            'strategy_id'=>$strategy['public_id'],'idempotency_key'=>'demand-retry:'.$dispatchKey,
            'trigger_type'=>'demand_signal','trigger_reference'=>$signal['public_id'],
            'input'=>['demand_signal_id'=>$signal['public_id'],'recommendation'=>$recommendation,'orchestration_id'=>$root['public_id'],'attempt_id'=>$attemptPublic],
        ]);
        $workflowId=(int)$run['id'];
        $plan=mg_agent_plan_run($pdo,$run,$actions,(int)$signal['merchant_user_id']);
        $status=(string)$plan['status']==='approval_pending'?'awaiting_approval':'running';
    }
    if($failureHook)$failureHook('after_retry_run_created',['orchestration'=>$root,'attempt_id'=>$attemptPublic]);

    $pdo->prepare('UPDATE demand_signal_orchestration_attempts SET workflow_run_id=?,swarm_run_id=?,status=?,updated_at=NOW() WHERE id=?')
        ->execute([$workflowId,$swarmId,$status,$attemptId]);
    $pdo->prepare('UPDATE demand_signal_orchestrations SET workflow_run_id=?,swarm_run_id=?,status=?,attempt_count=?,last_error=NULL,started_at=NOW(),completed_at=NULL,updated_at=NOW() WHERE id=?')
        ->execute([$workflowId,$swarmId,$status,$attemptNo,(int)$root['id']]);
    mg_demand_orchestration_event($pdo,(int)$root['id'],'retry_'.$attemptNo.'_dispatched','retry_dispatched',[
        'attempt_id'=>$attemptPublic,'attempt_no'=>$attemptNo,'type'=>$mode,
    ]);
    return ['orchestration_id'=>$root['public_id'],'attempt_id'=>$attemptPublic,'attempt_no'=>$attemptNo,'status'=>$status,'type'=>$mode,'duplicate'=>false];
}

function mg_operations_reconcile_demand_incidents(PDO $pdo,int $actorUserId): array
{
    $summary=['opened'=>0,'updated'=>0,'resolved'=>0];
    $candidates=$pdo->query(
        "SELECT o.*,s.public_id signal_public_id,s.signal_key,s.signal_level,s.status signal_status,s.summary signal_summary
         FROM demand_signal_orchestrations o
         INNER JOIN demand_agent_signals s ON s.id=o.demand_signal_id
         WHERE s.signal_level='critical' AND s.status='open' AND (
           o.status='failed'
           OR (o.status='claimed' AND o.updated_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE))
           OR (o.status='running' AND o.updated_at<DATE_SUB(NOW(),INTERVAL 1 HOUR))
           OR (o.status IN ('awaiting_approval','review_required') AND o.updated_at<DATE_SUB(NOW(),INTERVAL 24 HOUR))
         )
         ORDER BY o.id FOR UPDATE"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach($candidates as $root){
        $map=$pdo->prepare('SELECT m.*,i.status incident_status FROM demand_signal_orchestration_incidents m INNER JOIN operational_incidents i ON i.id=m.incident_id WHERE m.orchestration_id=? LIMIT 1 FOR UPDATE');
        $map->execute([(int)$root['id']]);$existing=$map->fetch(PDO::FETCH_ASSOC)?:null;
        if($existing===null){
            $incident=mg_operations_create_incident($pdo,$actorUserId,[
                'incident_key'=>'demand-orchestration-'.$root['public_id'],
                'title'=>'Critical demand orchestration requires intervention',
                'severity'=>'sev2','service_key'=>'demand-orchestration',
                'summary'=>'Critical signal '.$root['signal_key'].' is in orchestration status '.$root['status'].'.',
                'impact_summary'=>$root['signal_summary'],
                'metadata'=>['orchestration_id'=>$root['public_id'],'demand_signal_id'=>$root['signal_public_id'],'orchestration_status'=>$root['status']],
            ]);
            $pdo->prepare("INSERT INTO demand_signal_orchestration_incidents (public_id,orchestration_id,incident_id,escalation_status,last_orchestration_status,escalated_at,created_at,updated_at) VALUES (?,?,?,'active',?,NOW(),NOW(),NOW())")
                ->execute([mg_public_uuid(),(int)$root['id'],(int)$incident['id'],(string)$root['status']]);
            $summary['opened']++;
            continue;
        }
        if(in_array((string)$existing['incident_status'],['resolved','closed'],true)){
            $incidentStmt=$pdo->prepare('SELECT * FROM operational_incidents WHERE id=? LIMIT 1 FOR UPDATE');
            $incidentStmt->execute([(int)$existing['incident_id']]);$incident=$incidentStmt->fetch(PDO::FETCH_ASSOC);
            if($incident)mg_operations_transition_incident($pdo,$incident,'reopen',$actorUserId,['note'=>'Critical demand orchestration requires intervention again.']);
            $pdo->prepare("UPDATE demand_signal_orchestration_incidents SET escalation_status='active',last_orchestration_status=?,resolved_at=NULL,updated_at=NOW() WHERE id=?")
                ->execute([(string)$root['status'],(int)$existing['id']]);
            $summary['updated']++;
        }elseif((string)$existing['last_orchestration_status']!==(string)$root['status']){
            mg_operations_incident_event($pdo,(int)$existing['incident_id'],'orchestration_status_changed',null,null,$actorUserId,'Demand orchestration status changed.',['from'=>$existing['last_orchestration_status'],'to'=>$root['status'],'orchestration_id'=>$root['public_id']]);
            $pdo->prepare('UPDATE demand_signal_orchestration_incidents SET last_orchestration_status=?,updated_at=NOW() WHERE id=?')
                ->execute([(string)$root['status'],(int)$existing['id']]);
            $summary['updated']++;
        }
    }

    $resolved=$pdo->query(
        "SELECT m.*,i.status incident_status,o.public_id orchestration_public_id,o.status orchestration_status,s.status signal_status
         FROM demand_signal_orchestration_incidents m
         INNER JOIN operational_incidents i ON i.id=m.incident_id
         INNER JOIN demand_signal_orchestrations o ON o.id=m.orchestration_id
         INNER JOIN demand_agent_signals s ON s.id=o.demand_signal_id
         WHERE m.escalation_status='active' AND (o.status='completed' OR s.status<>'open')
         ORDER BY m.id FOR UPDATE"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach($resolved as $row){
        if(!in_array((string)$row['incident_status'],['resolved','closed'],true)){
            $incidentStmt=$pdo->prepare('SELECT * FROM operational_incidents WHERE id=? LIMIT 1 FOR UPDATE');
            $incidentStmt->execute([(int)$row['incident_id']]);$incident=$incidentStmt->fetch(PDO::FETCH_ASSOC);
            if($incident)mg_operations_transition_incident($pdo,$incident,'resolve',$actorUserId,['note'=>'Demand orchestration completed or its signal is no longer open.']);
        }
        $pdo->prepare("UPDATE demand_signal_orchestration_incidents SET escalation_status='resolved',resolved_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([(int)$row['id']]);
        $summary['resolved']++;
    }
    return $summary;
}
