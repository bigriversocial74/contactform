<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/operations/_orchestration_retry.php';
require_once dirname(__DIR__) . '/api/operations/_retention.php';
require_once dirname(__DIR__) . '/tests/integration/MicrogiftBehaviorFixture.php';

function mg_or_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_or_agent(PDO $pdo,int $ownerId,string $runId,string $suffix): array
{
    $public=mg_public_uuid();
    $id=mg_it_insert($pdo,'agents',[
        'public_id'=>$public,'user_id'=>$ownerId,'name'=>'Recovery Agent '.$runId.' '.$suffix,'category'=>'operations',
        'config_json'=>'{}','runtime_status'=>'running','lifecycle_status'=>'active','version_no'=>1,
        'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s'),
    ]);
    return ['id'=>$id,'public_id'=>$public];
}

function mg_or_strategy(PDO $pdo,int $ownerId,array $agent,string $runId,string $suffix,array $keys): array
{
    $strategy=mg_agent_create_strategy($pdo,$ownerId,[
        'agent_id'=>$agent['public_id'],'name'=>'Recovery Strategy '.$runId.' '.$suffix,
        'objective'=>'Safely retry demand orchestration.','trigger_type'=>'demand_signal',
        'trigger_config'=>['signal_keys'=>$keys,'minimum_level'=>'info','minimum_confidence'=>0.1,'orchestration_mode'=>'workflow'],
        'action_catalog'=>['create_operational_alert','acknowledge_demand_signal'],'max_actions_per_run'=>2,
        'requires_approval'=>false,'policy'=>['run_id'=>$runId],
    ]);
    $pdo->prepare("UPDATE agent_strategies SET status='active',updated_at=NOW() WHERE id=?")->execute([(int)$strategy['id']]);
    $strategy['status']='active';
    return $strategy;
}

function mg_or_signal(PDO $pdo,int $merchantId,string $runId,string $suffix,string $key,string $level='opportunity',string $status='open'): array
{
    $now=gmdate('Y-m-d H:i:s');$public=mg_public_uuid();
    $id=mg_it_insert($pdo,'demand_agent_signals',[
        'public_id'=>$public,'merchant_user_id'=>$merchantId,'location_id'=>null,'product_id'=>null,'signal_key'=>$key,
        'signal_level'=>$level,'status'=>$status,'observed_value'=>100,'baseline_value'=>50,'confidence_score'=>0.9,
        'summary'=>'Recovery validation '.$runId.' '.$suffix,
        'recommendation_json'=>json_encode(['action'=>'prepare_inventory','run_id'=>$runId],JSON_THROW_ON_ERROR),
        'source_snapshot_id'=>null,'dedupe_key'=>'recovery:'.$runId.':'.$suffix,'triggered_at'=>$now,
        'acknowledged_at'=>$status==='acknowledged'?$now:null,'resolved_at'=>$status==='resolved'?$now:null,
        'expires_at'=>gmdate('Y-m-d H:i:s',time()+86400),'created_at'=>$now,'updated_at'=>$now,
    ]);
    return ['id'=>$id,'public_id'=>$public];
}

function mg_or_root(PDO $pdo,array $signal,?array $strategy,string $runId,string $suffix,string $status='failed',string $type='workflow'): array
{
    $now=gmdate('Y-m-d H:i:s');$public=mg_public_uuid();
    $dispatch=hash('sha256','root-dispatch:'.$runId.':'.$suffix);
    $fingerprint=hash('sha256','root-fingerprint:'.$runId.':'.$suffix);
    $id=mg_it_insert($pdo,'demand_signal_orchestrations',[
        'public_id'=>$public,'demand_signal_id'=>$signal['id'],'strategy_id'=>$strategy['id']??null,
        'strategy_version'=>$strategy['version_no']??null,'team_id'=>null,'orchestration_type'=>$type,
        'workflow_run_id'=>null,'swarm_run_id'=>null,'status'=>$status,'recommendation_action'=>'prepare_inventory',
        'dispatch_key'=>$dispatch,'input_fingerprint'=>$fingerprint,'attempt_count'=>1,
        'last_error'=>$status==='failed'?'Fixture failure':null,'claimed_at'=>$now,'started_at'=>$now,
        'completed_at'=>in_array($status,['failed','completed','review_required'],true)?$now:null,
        'created_at'=>$now,'updated_at'=>$now,
    ]);
    return ['id'=>$id,'public_id'=>$public,'dispatch_key'=>$dispatch,'input_fingerprint'=>$fingerprint];
}

$pdo=mg_db();$runId='orchestration_recovery_'.bin2hex(random_bytes(6));
$summary=[
    'suite'=>'demand_orchestration_retry_incident_retention',
    'failed_retry_dispatched'=>false,'initial_attempt_preserved'=>false,'retry_replay_safe'=>false,
    'retry_completed'=>false,'review_retry_promoted'=>false,'downstream_failure_rolls_back'=>false,
    'critical_incident_opened_once'=>false,'critical_incident_resolved'=>false,
    'completed_events_retained_safely'=>false,'active_events_preserved'=>false,
    'business_truth_preserved'=>false,'permissions_admin_only'=>false,'fixtures_clean'=>false,
];
$baseline=[
    'attempts'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_attempts'),
    'incident_links'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_incidents'),
    'incidents'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM operational_incidents'),
    'events'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_events'),
    'retention_runs'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM retention_runs'),
];

$pdo->beginTransaction();
try{
    $merchantEmail=$runId.'-merchant@example.test';$adminEmail=$runId.'-admin@example.test';
    $merchantId=mg_it_user($pdo,$merchantEmail,'Recovery Merchant');
    $adminId=mg_it_user($pdo,$adminEmail,'Recovery Admin');
    $pdo->prepare("INSERT INTO user_roles (user_id,role_id,created_at) SELECT ?,id,NOW() FROM roles WHERE slug='super_admin'")->execute([$adminId]);
    $agent=mg_or_agent($pdo,$merchantId,$runId,'main');
    $strategy=mg_or_strategy($pdo,$merchantId,$agent,$runId,'main',['retry_signal','review_signal','rollback_signal','critical_signal']);

    $signal=mg_or_signal($pdo,$merchantId,$runId,'retry','retry_signal');
    $root=mg_or_root($pdo,$signal,$strategy,$runId,'retry');
    $retry=mg_operations_retry_demand_orchestration_coordinated($pdo,$adminId,$root['public_id'],'retry-'.$runId,'Retry failed orchestration.');
    mg_or_assert($retry['attempt_no']===2&&$retry['status']==='running','Failed orchestration retry was not dispatched as attempt 2.');
    mg_or_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_attempts WHERE orchestration_id=?',[$root['id']])===2,'Initial and retry attempts were not both preserved.');
    mg_or_assert((string)mg_it_scalar($pdo,'SELECT dispatch_key FROM demand_signal_orchestrations WHERE id=?',[$root['id']])===$root['dispatch_key'],'Original dispatch key changed during retry.');
    mg_or_assert((string)mg_it_scalar($pdo,'SELECT input_fingerprint FROM demand_signal_orchestrations WHERE id=?',[$root['id']])===$root['input_fingerprint'],'Original fingerprint changed during retry.');
    $summary['failed_retry_dispatched']=true;$summary['initial_attempt_preserved']=true;

    $replay=mg_operations_retry_demand_orchestration_coordinated($pdo,$adminId,$root['public_id'],'retry-'.$runId,'Retry failed orchestration.');
    mg_or_assert(!empty($replay['duplicate']),'Exact retry replay was not recognized.');
    mg_or_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM agent_workflow_runs WHERE trigger_reference=?',[$signal['public_id']])===1,'Retry replay duplicated workflow runs.');
    $summary['retry_replay_safe']=true;

    mg_or_assert(mg_agent_process_next_action($pdo)['status']==='completed','Retry alert action failed.');
    mg_or_assert(mg_agent_process_next_action($pdo)['status']==='completed','Retry acknowledgement action failed.');
    $reconciled=mg_operations_reconcile_next_demand_orchestration($pdo);
    mg_or_assert(($reconciled['status']??null)==='completed','Retry orchestration did not reconcile to completed.');
    mg_or_assert((string)mg_it_scalar($pdo,'SELECT status FROM demand_agent_signals WHERE id=?',[$signal['id']])==='acknowledged','Retry did not acknowledge the demand signal.');
    mg_or_assert((string)mg_it_scalar($pdo,'SELECT status FROM demand_signal_orchestration_attempts WHERE orchestration_id=? ORDER BY attempt_no DESC LIMIT 1',[$root['id']])==='completed','Retry attempt did not reconcile to completed.');
    $summary['retry_completed']=true;

    $reviewSignal=mg_or_signal($pdo,$merchantId,$runId,'review','review_signal');
    $reviewRoot=mg_or_root($pdo,$reviewSignal,null,$runId,'review','review_required','alert_only');
    $reviewRetry=mg_operations_retry_demand_orchestration_coordinated($pdo,$adminId,$reviewRoot['public_id'],'review-'.$runId,'A safe strategy is now available.');
    mg_or_assert($reviewRetry['type']==='workflow','Review-required orchestration was not promoted to a workflow.');
    mg_or_assert((string)mg_it_scalar($pdo,'SELECT orchestration_type FROM demand_signal_orchestrations WHERE id=?',[$reviewRoot['id']])==='workflow','Review root did not bind to its active execution mode.');
    $summary['review_retry_promoted']=true;

    $rollbackSignal=mg_or_signal($pdo,$merchantId,$runId,'rollback','rollback_signal');
    $rollbackRoot=mg_or_root($pdo,$rollbackSignal,$strategy,$runId,'rollback');
    $before=[
        'attempts'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_attempts WHERE orchestration_id=?',[$rollbackRoot['id']]),
        'runs'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM agent_workflow_runs'),
        'status'=>(string)mg_it_scalar($pdo,'SELECT status FROM demand_signal_orchestrations WHERE id=?',[$rollbackRoot['id']]),
    ];
    $pdo->exec('SAVEPOINT retry_downstream_failure');$forced=false;
    try{
        mg_operations_retry_demand_orchestration_coordinated($pdo,$adminId,$rollbackRoot['public_id'],'rollback-'.$runId,'Force rollback.',static function(string $stage):void{
            if($stage==='after_retry_run_created')throw new RuntimeException('Forced retry downstream failure.');
        });
    }catch(RuntimeException $e){$forced=$e->getMessage()==='Forced retry downstream failure.';}
    mg_or_assert($forced,'Forced retry failure did not occur.');
    $pdo->exec('ROLLBACK TO SAVEPOINT retry_downstream_failure');$pdo->exec('RELEASE SAVEPOINT retry_downstream_failure');
    $after=[
        'attempts'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_attempts WHERE orchestration_id=?',[$rollbackRoot['id']]),
        'runs'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM agent_workflow_runs'),
        'status'=>(string)mg_it_scalar($pdo,'SELECT status FROM demand_signal_orchestrations WHERE id=?',[$rollbackRoot['id']]),
    ];
    mg_or_assert($before===$after,'Retry downstream failure did not roll back attempts, runs, and root state.');
    $summary['downstream_failure_rolls_back']=true;

    $criticalSignal=mg_or_signal($pdo,$merchantId,$runId,'critical','critical_signal','critical');
    $criticalRoot=mg_or_root($pdo,$criticalSignal,$strategy,$runId,'critical');
    $incidents=mg_operations_reconcile_demand_incidents($pdo,$adminId);
    mg_or_assert($incidents['opened']===1,'Critical failed orchestration did not open an incident.');
    $again=mg_operations_reconcile_demand_incidents($pdo,$adminId);
    mg_or_assert($again['opened']===0&&(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_incidents WHERE orchestration_id=?',[$criticalRoot['id']])===1,'Incident escalation was not idempotent.');
    $summary['critical_incident_opened_once']=true;
    $pdo->prepare("UPDATE demand_signal_orchestrations SET status='completed',completed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$criticalRoot['id']]);
    $pdo->prepare("UPDATE demand_agent_signals SET status='acknowledged',acknowledged_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$criticalSignal['id']]);
    $resolved=mg_operations_reconcile_demand_incidents($pdo,$adminId);
    mg_or_assert($resolved['resolved']===1,'Successful orchestration did not resolve its incident.');
    mg_or_assert((string)mg_it_scalar($pdo,'SELECT status FROM operational_incidents WHERE id=(SELECT incident_id FROM demand_signal_orchestration_incidents WHERE orchestration_id=?)',[$criticalRoot['id']])==='resolved','Operational incident is not resolved.');
    $summary['critical_incident_resolved']=true;

    $completedSignal=mg_or_signal($pdo,$merchantId,$runId,'retention-complete','retention_complete','info','resolved');
    $completedRoot=mg_or_root($pdo,$completedSignal,$strategy,$runId,'retention-complete','completed');
    $activeSignal=mg_or_signal($pdo,$merchantId,$runId,'retention-active','retention_active','warning');
    $activeRoot=mg_or_root($pdo,$activeSignal,$strategy,$runId,'retention-active','failed');
    $old=gmdate('Y-m-d H:i:s',time()-400*86400);
    foreach([$completedRoot,$activeRoot] as $fixture){
        for($i=1;$i<=3;$i++)mg_it_insert($pdo,'demand_signal_orchestration_events',[
            'public_id'=>mg_public_uuid(),'orchestration_id'=>$fixture['id'],'event_key'=>'old_'.$i,'event_type'=>'fixture_'.$i,
            'payload_json'=>json_encode(['fixture'=>$i],JSON_THROW_ON_ERROR),'created_at'=>$old,
        ]);
    }
    $policyStmt=$pdo->prepare("SELECT * FROM retention_policies WHERE policy_key='demand_orchestration_events_365d' LIMIT 1 FOR UPDATE");
    $policyStmt->execute();$policy=$policyStmt->fetch(PDO::FETCH_ASSOC);
    mg_or_assert(is_array($policy),'Orchestration retention policy is missing.');
    $retention=mg_retention_run_policy($pdo,$policy);
    mg_or_assert((int)$retention['affected']===2,'Retention did not remove only superseded completed events.');
    mg_or_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_events WHERE orchestration_id=?',[$completedRoot['id']])===1,'Retention did not preserve the latest completed event.');
    mg_or_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_events WHERE orchestration_id=?',[$activeRoot['id']])===3,'Retention removed unresolved orchestration events.');
    $summary['completed_events_retained_safely']=true;$summary['active_events_preserved']=true;
    mg_or_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestrations WHERE id IN (?,?)',[$completedRoot['id'],$activeRoot['id']])===2,'Retention altered orchestration truth.');
    mg_or_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_agent_signals WHERE id IN (?,?)',[$completedSignal['id'],$activeSignal['id']])===2,'Retention altered demand-signal truth.');
    $summary['business_truth_preserved']=true;

    $adminRoles=(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM role_permissions rp INNER JOIN roles r ON r.id=rp.role_id INNER JOIN permissions p ON p.id=rp.permission_id WHERE p.slug='operations.orchestrations.retry' AND r.slug IN ('admin','super_admin')");
    $otherRoles=(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM role_permissions rp INNER JOIN roles r ON r.id=rp.role_id INNER JOIN permissions p ON p.id=rp.permission_id WHERE p.slug='operations.orchestrations.retry' AND r.slug NOT IN ('admin','super_admin')");
    mg_or_assert($adminRoles>=1&&$otherRoles===0,'Retry permission is not administrator-only.');
    $summary['permissions_admin_only']=true;

    $pdo->rollBack();
    $afterRollback=[
        'attempts'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_attempts'),
        'incident_links'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_incidents'),
        'incidents'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM operational_incidents'),
        'events'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_events'),
        'retention_runs'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM retention_runs'),
    ];
    mg_or_assert($baseline===$afterRollback,'Stage 18C fixtures remain after rollback.');
    mg_or_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$merchantEmail,$adminEmail])===0,'Stage 18C users remain after rollback.');
    $summary['fixtures_clean']=true;
    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
