<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/agents/_workflow.php';
require_once dirname(__DIR__).'/tests/integration/MicrogiftBehaviorFixture.php';

function mg_agent_behavior_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_agent_behavior_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
}

function mg_agent_behavior_fixture(PDO $pdo,int $ownerId,string $runId): array
{
    $publicId=mg_public_uuid();
    $pdo->prepare("INSERT INTO agents (public_id,user_id,name,category,config_json,runtime_status,lifecycle_status,version_no,created_at,updated_at) VALUES (?,?,?,'operations','{}','running','active',1,NOW(),NOW())")
        ->execute([$publicId,$ownerId,'Behavior Agent '.$runId]);
    return ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$publicId];
}

function mg_agent_behavior_run(PDO $pdo,int $ownerId,string $agentPublicId,string $runId,string $suffix): array
{
    $strategy=mg_agent_create_strategy($pdo,$ownerId,[
        'agent_id'=>$agentPublicId,'name'=>'Behavior strategy '.$suffix,'objective'=>'Validate controlled agent execution',
        'trigger_type'=>'manual','action_catalog'=>['create_operational_alert'],'max_actions_per_run'=>2,'requires_approval'=>true,
        'policy'=>['behavior_run_id'=>$runId],
    ]);
    $pdo->prepare("UPDATE agent_strategies SET status='active',updated_at=NOW() WHERE id=?")->execute([(int)$strategy['id']]);
    $run=mg_agent_create_run($pdo,$ownerId,[
        'strategy_id'=>$strategy['public_id'],'idempotency_key'=>'behavior:agent:'.$runId.':'.$suffix,
        'input'=>['run_id'=>$runId,'suffix'=>$suffix],
    ]);
    $plan=mg_agent_plan_run($pdo,$run,[[
        'action_type'=>'create_operational_alert','target_type'=>'user','target_reference'=>(string)$ownerId,
        'risk_level'=>'high','reason'=>'Behavioral approval required.',
        'request'=>['title'=>'Behavior alert '.$suffix,'message'=>'Agent behavior validation '.$runId,'action_url'=>'/account.php'],
    ]],$ownerId);
    $approval=$pdo->prepare('SELECT * FROM agent_approval_requests WHERE run_id=? LIMIT 1');
    $approval->execute([(int)$run['id']]);
    return ['strategy'=>$strategy,'run'=>$run,'plan'=>$plan,'approval'=>$approval->fetch(PDO::FETCH_ASSOC)];
}

$pdo=mg_db();$runId='agent_'.bin2hex(random_bytes(8));
$summary=[
    'suite'=>'agent_approval_execution_reconciliation_behavior','run_id'=>$runId,
    'approval_required'=>false,'approval_replay'=>false,'conflicting_approval_rejected'=>false,
    'execution_completed'=>false,'execution_replay_safe'=>false,'alert_created_once'=>false,
    'forced_failure_rolled_back'=>false,'failure_reconciled'=>false,'audit_consistent'=>false,'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $ownerEmail=$runId.'-owner@example.test';$ownerId=mg_it_user($pdo,$ownerEmail,'Agent Behavior Owner');
    $agent=mg_agent_behavior_fixture($pdo,$ownerId,$runId);

    $success=mg_agent_behavior_run($pdo,$ownerId,$agent['public_id'],$runId,'success');
    mg_agent_behavior_assert($success['plan']['status']==='approval_pending','High-risk action did not require approval.');
    mg_agent_behavior_assert((string)$success['approval']['status']==='pending','Approval request was not created.');
    $summary['approval_required']=true;

    $approvalInput=['approval_id'=>$success['approval']['public_id'],'decision'=>'approve','reason'=>'Behavior approval.'];
    $approved=mg_agent_decide_approval($pdo,$ownerId,$approvalInput);
    mg_agent_behavior_assert($approved['status']==='approved'&&$approved['duplicate']===false,'Approval decision failed.');
    $approvalReplay=mg_agent_decide_approval($pdo,$ownerId,$approvalInput);
    mg_agent_behavior_assert($approvalReplay['duplicate']===true,'Exact approval replay was not idempotent.');
    mg_agent_behavior_assert((int)mg_agent_behavior_scalar($pdo,"SELECT COUNT(*) FROM agent_execution_events WHERE run_id=? AND event_type='approval_approved'",[(int)$success['run']['id']])===1,'Approval replay duplicated events.');
    $summary['approval_replay']=true;

    $conflict=false;
    try{mg_agent_decide_approval($pdo,$ownerId,['approval_id'=>$success['approval']['public_id'],'decision'=>'reject','reason'=>'Conflict.']);}
    catch(MgAgentWorkflowException $error){$conflict=$error->httpStatus===409;}
    mg_agent_behavior_assert($conflict,'Conflicting approval replay was accepted.');
    $summary['conflicting_approval_rejected']=true;

    $beforeAlerts=(int)mg_agent_behavior_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE user_id=? AND alert_type='agent_action'",[$ownerId]);
    $processed=mg_agent_process_next_action($pdo);
    mg_agent_behavior_assert($processed['status']==='completed'&&$processed['run_status']==='completed','Approved action did not complete.');
    mg_agent_behavior_assert((string)mg_agent_behavior_scalar($pdo,'SELECT status FROM agent_workflow_actions WHERE run_id=?',[(int)$success['run']['id']])==='completed','Action row did not complete.');
    mg_agent_behavior_assert((string)mg_agent_behavior_scalar($pdo,'SELECT status FROM agent_workflow_runs WHERE id=?',[(int)$success['run']['id']])==='completed','Run did not reconcile to completed.');
    $summary['execution_completed']=true;
    mg_agent_behavior_assert((int)mg_agent_behavior_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE user_id=? AND alert_type='agent_action'",[$ownerId])===$beforeAlerts+1,'Agent alert was not created exactly once.');
    $summary['alert_created_once']=true;

    $noReplay=mg_agent_process_next_action($pdo);
    mg_agent_behavior_assert($noReplay['processed']===false,'Completed action was executed twice.');
    $summary['execution_replay_safe']=true;

    $failure=mg_agent_behavior_run($pdo,$ownerId,$agent['public_id'],$runId,'failure');
    mg_agent_decide_approval($pdo,$ownerId,['approval_id'=>$failure['approval']['public_id'],'decision'=>'approve','reason'=>'Test rollback.']);
    $alertsBeforeFailure=(int)mg_agent_behavior_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE user_id=? AND alert_type='agent_action'",[$ownerId]);
    $failed=mg_agent_process_next_action($pdo,static function(string $stage): void {
        if($stage==='after_effect')throw new RuntimeException('Forced agent execution failure.');
    });
    mg_agent_behavior_assert($failed['status']==='failed'&&$failed['run_status']==='failed','Failed action was not reconciled.');
    mg_agent_behavior_assert((int)mg_agent_behavior_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE user_id=? AND alert_type='agent_action'",[$ownerId])===$alertsBeforeFailure,'Forced failure left target side effects.');
    mg_agent_behavior_assert((string)mg_agent_behavior_scalar($pdo,'SELECT status FROM agent_workflow_actions WHERE run_id=?',[(int)$failure['run']['id']])==='failed','Failure receipt was not persisted.');
    mg_agent_behavior_assert((string)mg_agent_behavior_scalar($pdo,'SELECT status FROM agent_workflow_runs WHERE id=?',[(int)$failure['run']['id']])==='failed','Failed run did not reconcile.');
    $summary['forced_failure_rolled_back']=true;$summary['failure_reconciled']=true;

    mg_agent_behavior_assert((int)mg_agent_behavior_scalar($pdo,"SELECT COUNT(*) FROM agent_execution_events WHERE run_id=? AND event_type='action_execution_completed'",[(int)$success['run']['id']])===1,'Success audit history is inconsistent.');
    mg_agent_behavior_assert((int)mg_agent_behavior_scalar($pdo,"SELECT COUNT(*) FROM agent_execution_events WHERE run_id=? AND event_type='action_execution_failed'",[(int)$failure['run']['id']])===1,'Failure audit history is inconsistent.');
    mg_agent_behavior_assert((int)mg_agent_behavior_scalar($pdo,"SELECT COUNT(*) FROM agent_execution_events WHERE run_id=? AND event_type='run_failed'",[(int)$failure['run']['id']])===1,'Run failure event is inconsistent.');
    $summary['audit_consistent']=true;

    $pdo->rollBack();
    mg_agent_behavior_assert((int)mg_agent_behavior_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email=?',[$ownerEmail])===0,'Agent behavior user remains.');
    mg_agent_behavior_assert((int)mg_agent_behavior_scalar($pdo,'SELECT COUNT(*) FROM agent_workflow_runs WHERE idempotency_key LIKE ?',['behavior:agent:'.$runId.'%'])===0,'Agent workflow fixtures remain.');
    mg_agent_behavior_assert((int)mg_agent_behavior_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE body LIKE ?",['%'.$runId.'%'])===0,'Agent alert fixtures remain.');
    $summary['fixtures_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
