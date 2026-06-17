<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__) . '/api/agents/_demand_orchestration.php';
require_once dirname(__DIR__) . '/tests/integration/MicrogiftBehaviorFixture.php';

function mg_do_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_do_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
}

function mg_do_agent(PDO $pdo,int $ownerId,string $runId,string $suffix): array
{
    $public=mg_public_uuid();
    $pdo->prepare("INSERT INTO agents (public_id,user_id,name,category,config_json,runtime_status,lifecycle_status,version_no,created_at,updated_at) VALUES (?,?,?,'operations','{}','running','active',1,NOW(),NOW())")
        ->execute([$public,$ownerId,'Demand Agent '.$runId.' '.$suffix]);
    return ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$public];
}

function mg_do_strategy(PDO $pdo,int $ownerId,array $agent,string $runId,string $suffix,array $keys,string $mode='workflow',?string $teamId=null,bool $requiresApproval=false): array
{
    $config=[
        'signal_keys'=>$keys,
        'minimum_level'=>'info',
        'minimum_confidence'=>0.1,
        'orchestration_mode'=>$mode,
    ];
    if($mode==='swarm'){
        $config['team_id']=$teamId;
        $config['capability_key']='operations';
        $config['estimated_units']=125;
        $config['budget_units']=125;
        $config['requires_review']=false;
    }
    $strategy=mg_agent_create_strategy($pdo,$ownerId,[
        'agent_id'=>$agent['public_id'],
        'name'=>'Demand strategy '.$runId.' '.$suffix,
        'objective'=>'Safely review and acknowledge demand signals.',
        'trigger_type'=>'demand_signal',
        'trigger_config'=>$config,
        'action_catalog'=>['create_operational_alert','acknowledge_demand_signal'],
        'max_actions_per_run'=>2,
        'requires_approval'=>$requiresApproval,
        'policy'=>['run_id'=>$runId,'suffix'=>$suffix],
    ]);
    $pdo->prepare("UPDATE agent_strategies SET status='active',updated_at=NOW() WHERE id=?")->execute([(int)$strategy['id']]);
    $strategy['status']='active';
    return $strategy;
}

function mg_do_signal(PDO $pdo,int $merchantId,string $runId,string $suffix,string $key,string $level='opportunity',string $recommendation='prepare_inventory'): array
{
    $public=mg_public_uuid();
    $pdo->prepare("INSERT INTO demand_agent_signals (public_id,merchant_user_id,location_id,product_id,signal_key,signal_level,status,observed_value,baseline_value,confidence_score,summary,recommendation_json,source_snapshot_id,dedupe_key,triggered_at,expires_at,created_at,updated_at) VALUES (?,?,NULL,NULL,?,?,'open',100,50,0.9,?,?,NULL,?,NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY),NOW(),NOW())")
        ->execute([
            $public,$merchantId,$key,$level,
            'Demand orchestration behavior '.$runId.' '.$suffix,
            json_encode(['action'=>$recommendation,'run_id'=>$runId,'suffix'=>$suffix],JSON_THROW_ON_ERROR),
            'behavior:'.$runId.':'.$suffix,
        ]);
    $stmt=$pdo->prepare('SELECT * FROM demand_agent_signals WHERE id=?');$stmt->execute([(int)$pdo->lastInsertId()]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function mg_do_approve_run(PDO $pdo,int $ownerId,int $runId): int
{
    $stmt=$pdo->prepare("SELECT public_id FROM agent_approval_requests WHERE run_id=? AND status='pending' ORDER BY id");
    $stmt->execute([$runId]);$count=0;
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $approvalId){
        mg_agent_decide_approval($pdo,$ownerId,['approval_id'=>$approvalId,'decision'=>'approve','reason'=>'Demand orchestration behavior approval.']);
        $count++;
    }
    return $count;
}

$pdo=mg_db();$runId='demand_orchestration_'.bin2hex(random_bytes(6));
$summary=[
    'suite'=>'demand_signal_agent_orchestration_behavior',
    'workflow_dispatched'=>false,
    'merchant_strategy_scoped'=>false,
    'critical_requires_approval'=>false,
    'dispatch_replay_safe'=>false,
    'workflow_completed'=>false,
    'signal_acknowledged_once'=>false,
    'unsupported_review_alert'=>false,
    'swarm_dispatched'=>false,
    'swarm_completed'=>false,
    'budget_consumed_once'=>false,
    'failed_execution_leaves_signal_open'=>false,
    'failure_reconciled'=>false,
    'downstream_failure_rolls_back'=>false,
    'events_and_alerts_once'=>false,
    'fixtures_clean'=>false,
];

$baseline=[
    'orchestrations'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestrations'),
    'events'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_events'),
    'runs'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM agent_workflow_runs'),
    'swarms'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM agent_swarm_runs'),
];

$pdo->beginTransaction();
try{
    $ownerEmail=$runId.'-owner@example.test';
    $foreignEmail=$runId.'-foreign@example.test';
    $ownerId=mg_it_user($pdo,$ownerEmail,'Demand Orchestration Owner');
    $foreignId=mg_it_user($pdo,$foreignEmail,'Foreign Demand Owner');
    $agent=mg_do_agent($pdo,$ownerId,$runId,'workflow');
    $foreignAgent=mg_do_agent($pdo,$foreignId,$runId,'foreign');
    mg_do_strategy($pdo,$foreignId,$foreignAgent,$runId,'foreign',['committed_demand']);
    $workflowStrategy=mg_do_strategy($pdo,$ownerId,$agent,$runId,'workflow',['committed_demand']);

    $workflowSignal=mg_do_signal($pdo,$ownerId,$runId,'workflow','committed_demand','critical','prepare_inventory');
    $dispatch=mg_demand_orchestrate_signal($pdo,$workflowSignal);
    mg_do_assert($dispatch['type']==='workflow'&&$dispatch['status']==='awaiting_approval','Critical demand signal did not create an approval-gated workflow.');
    $workflowRunId=(int)mg_do_scalar($pdo,'SELECT workflow_run_id FROM demand_signal_orchestrations WHERE public_id=?',[$dispatch['orchestration_id']]);
    mg_do_assert((int)mg_do_scalar($pdo,'SELECT owner_user_id FROM agent_workflow_runs WHERE id=?',[$workflowRunId])===$ownerId,'Demand workflow escaped merchant ownership.');
    mg_do_assert((int)mg_do_scalar($pdo,'SELECT strategy_id FROM agent_workflow_runs WHERE id=?',[$workflowRunId])===(int)$workflowStrategy['id'],'Demand workflow selected the wrong merchant strategy.');
    $summary['workflow_dispatched']=true;$summary['merchant_strategy_scoped']=true;

    $approvalCount=(int)mg_do_scalar($pdo,"SELECT COUNT(*) FROM agent_approval_requests WHERE run_id=? AND status='pending'",[$workflowRunId]);
    mg_do_assert($approvalCount===2,'Critical demand workflow did not require approval for both actions.');
    $summary['critical_requires_approval']=true;

    $replay=mg_demand_orchestrate_signal($pdo,$workflowSignal);
    mg_do_assert($replay['duplicate']===true,'Exact demand dispatch replay was not idempotent.');
    mg_do_assert((int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestrations WHERE demand_signal_id=?',[(int)$workflowSignal['id']])===1,'Demand dispatch replay duplicated orchestration state.');
    $summary['dispatch_replay_safe']=true;

    mg_do_assert(mg_do_approve_run($pdo,$ownerId,$workflowRunId)===2,'Demand workflow approvals were not applied.');
    $first=mg_agent_process_next_action($pdo);$second=mg_agent_process_next_action($pdo);
    mg_do_assert($first['status']==='completed'&&$second['status']==='completed','Demand workflow actions did not complete.');
    $reconciled=mg_demand_reconcile_next_orchestration($pdo);
    mg_do_assert($reconciled['status']==='completed','Completed demand workflow did not reconcile.');
    mg_do_assert((string)mg_do_scalar($pdo,'SELECT status FROM demand_agent_signals WHERE id=?',[(int)$workflowSignal['id']])==='acknowledged','Completed demand workflow did not acknowledge its signal.');
    mg_do_assert((int)mg_do_scalar($pdo,"SELECT COUNT(*) FROM agent_execution_events WHERE run_id=? AND event_type='action_execution_completed'",[$workflowRunId])===2,'Demand workflow completion events are inconsistent.');
    $summary['workflow_completed']=true;$summary['signal_acknowledged_once']=true;

    $unmatched=mg_do_signal($pdo,$ownerId,$runId,'unmatched','future_visit_cluster','warning','schedule_staffing');
    $review=mg_demand_orchestrate_signal($pdo,$unmatched);
    mg_do_assert($review['type']==='alert_only'&&$review['status']==='review_required','Unmatched recommendation did not create a review-required alert.');
    mg_do_assert((string)mg_do_scalar($pdo,'SELECT status FROM demand_agent_signals WHERE id=?',[(int)$unmatched['id']])==='open','Unmatched recommendation changed signal authority.');
    mg_do_assert((int)mg_do_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE user_id=? AND alert_type='demand_signal_review' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.demand_signal_id'))=?",[$ownerId,$unmatched['public_id']])===1,'Unmatched recommendation alert was not emitted exactly once.');
    $summary['unsupported_review_alert']=true;

    $swarmAgent=mg_do_agent($pdo,$ownerId,$runId,'swarm');
    $team=mg_swarm_create_team($pdo,$ownerId,[
        'name'=>'Demand swarm '.$runId,'objective'=>'Review demand signals safely.','coordination_mode'=>'pipeline',
        'conflict_policy'=>'owner_decides','max_parallel_tasks'=>1,'default_budget_units'=>125,'metadata'=>['run_id'=>$runId],
    ]);
    $pdo->prepare("UPDATE agent_teams SET status='active',updated_at=NOW() WHERE id=?")->execute([(int)$team['id']]);
    $team['status']='active';
    mg_swarm_add_member($pdo,$team,$ownerId,[
        'agent_id'=>$swarmAgent['public_id'],'role_key'=>'demand-executor','role_type'=>'executor','priority'=>10,
        'capabilities'=>['operations'],'routing_profile'=>['run_id'=>$runId],'max_concurrent_tasks'=>1,
    ]);
    mg_do_strategy($pdo,$ownerId,$swarmAgent,$runId,'swarm',['future_visit_cluster'],'swarm',$team['public_id']);
    $swarmSignal=mg_do_signal($pdo,$ownerId,$runId,'swarm','future_visit_cluster','opportunity','schedule_staffing');
    $swarmDispatch=mg_demand_orchestrate_signal($pdo,$swarmSignal);
    mg_do_assert($swarmDispatch['type']==='swarm'&&$swarmDispatch['status']==='running','Demand signal did not create a swarm run.');
    $summary['swarm_dispatched']=true;
    $route=mg_swarm_route_next_task($pdo);
    mg_do_assert($route['processed']===true,'Demand swarm task was not routed into Stage 16.');
    mg_do_assert(mg_agent_process_next_action($pdo)['status']==='completed','Demand swarm alert action failed.');
    mg_do_assert(mg_agent_process_next_action($pdo)['status']==='completed','Demand swarm acknowledge action failed.');
    $sync=mg_swarm_sync_workflow($pdo,(string)$route['task_id']);
    mg_do_assert($sync['status']==='completed'&&$sync['run_status']==='completed','Demand swarm did not reconcile to completion.');
    $swarmReconcile=mg_demand_reconcile_next_orchestration($pdo);
    mg_do_assert($swarmReconcile['status']==='completed','Completed demand swarm did not reconcile.');
    mg_do_assert((string)mg_do_scalar($pdo,'SELECT status FROM demand_agent_signals WHERE id=?',[(int)$swarmSignal['id']])==='acknowledged','Demand swarm did not acknowledge its signal.');
    $swarmRunId=(int)mg_do_scalar($pdo,'SELECT swarm_run_id FROM demand_signal_orchestrations WHERE demand_signal_id=?',[(int)$swarmSignal['id']]);
    mg_do_assert((int)mg_do_scalar($pdo,'SELECT consumed_units FROM agent_swarm_runs WHERE id=?',[$swarmRunId])===125,'Demand swarm budget was not consumed exactly once.');
    $summary['swarm_completed']=true;$summary['budget_consumed_once']=true;

    $failureAgent=mg_do_agent($pdo,$ownerId,$runId,'failure');
    mg_do_strategy($pdo,$ownerId,$failureAgent,$runId,'failure',['velocity_spike']);
    $failureSignal=mg_do_signal($pdo,$ownerId,$runId,'failure','velocity_spike','opportunity','increase_capacity');
    $failureDispatch=mg_demand_orchestrate_signal($pdo,$failureSignal);
    mg_do_assert($failureDispatch['type']==='workflow','Failure fixture did not create a workflow.');
    mg_do_assert(mg_agent_process_next_action($pdo)['status']==='completed','Failure fixture alert action did not complete.');
    $failed=mg_agent_process_next_action($pdo,static function(string $stage): void {
        if($stage==='after_effect')throw new RuntimeException('Forced demand acknowledgement failure.');
    });
    mg_do_assert($failed['status']==='failed'&&$failed['run_status']==='partially_completed','Forced demand action failure did not reconcile the workflow.');
    $failedReconcile=mg_demand_reconcile_next_orchestration($pdo);
    mg_do_assert($failedReconcile['status']==='failed','Failed demand workflow did not reconcile the orchestration.');
    mg_do_assert((string)mg_do_scalar($pdo,'SELECT status FROM demand_agent_signals WHERE id=?',[(int)$failureSignal['id']])==='open','Failed demand execution did not leave the signal retryable.');
    $summary['failed_execution_leaves_signal_open']=true;$summary['failure_reconciled']=true;

    $rollbackAgent=mg_do_agent($pdo,$ownerId,$runId,'rollback');
    mg_do_strategy($pdo,$ownerId,$rollbackAgent,$runId,'rollback',['rollback_signal']);
    $rollbackSignal=mg_do_signal($pdo,$ownerId,$runId,'rollback','rollback_signal','opportunity','review_capacity');
    $beforeRollback=[
        'orchestrations'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestrations'),
        'runs'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM agent_workflow_runs'),
        'events'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_events'),
    ];
    $pdo->exec('SAVEPOINT demand_orchestration_failure');$forced=false;
    try{
        mg_demand_orchestrate_signal($pdo,$rollbackSignal,static function(string $stage): void {
            if($stage==='after_orchestration_created')throw new RuntimeException('Forced downstream orchestration failure.');
        });
    }catch(RuntimeException $error){$forced=$error->getMessage()==='Forced downstream orchestration failure.';}
    mg_do_assert($forced,'Forced downstream orchestration failure did not occur.');
    $pdo->exec('ROLLBACK TO SAVEPOINT demand_orchestration_failure');
    $pdo->exec('RELEASE SAVEPOINT demand_orchestration_failure');
    $afterRollback=[
        'orchestrations'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestrations'),
        'runs'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM agent_workflow_runs'),
        'events'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_events'),
    ];
    mg_do_assert($beforeRollback===$afterRollback,'Downstream failure did not roll back orchestration, workflow, and audit state.');
    mg_do_assert((string)mg_do_scalar($pdo,'SELECT status FROM demand_agent_signals WHERE id=?',[(int)$rollbackSignal['id']])==='open','Rollback changed the demand signal.');
    $summary['downstream_failure_rolls_back']=true;

    $orchestrationCount=(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestrations');
    $claimedEvents=(int)mg_do_scalar($pdo,"SELECT COUNT(*) FROM demand_signal_orchestration_events WHERE event_type IN ('claimed','review_required')");
    mg_do_assert($claimedEvents===$orchestrationCount,'Demand orchestration start events are not exactly once.');
    mg_do_assert((int)mg_do_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE user_id=? AND body LIKE ?",[$ownerId,'%'.$runId.'%'])===4,'Demand orchestration alerts were duplicated or missing.');
    $summary['events_and_alerts_once']=true;

    $pdo->rollBack();
    $after=[
        'orchestrations'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestrations'),
        'events'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_events'),
        'runs'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM agent_workflow_runs'),
        'swarms'=>(int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM agent_swarm_runs'),
    ];
    mg_do_assert($baseline===$after,'Demand orchestration fixtures remain after rollback.');
    mg_do_assert((int)mg_do_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$ownerEmail,$foreignEmail])===0,'Demand orchestration users remain after rollback.');
    $summary['fixtures_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
