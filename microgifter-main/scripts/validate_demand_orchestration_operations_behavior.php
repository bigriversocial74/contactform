<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__) . '/api/operations/_operations.php';
require_once dirname(__DIR__) . '/tests/integration/MicrogiftBehaviorFixture.php';

function mg_doo_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_doo_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
}

function mg_doo_signal(PDO $pdo,int $userId,string $runId,string $suffix,string $level,string $status,string $triggeredAt): array
{
    $public=mg_public_uuid();
    $pdo->prepare("INSERT INTO demand_agent_signals (public_id,merchant_user_id,location_id,product_id,signal_key,signal_level,status,observed_value,baseline_value,confidence_score,summary,recommendation_json,source_snapshot_id,dedupe_key,triggered_at,acknowledged_at,resolved_at,expires_at,created_at,updated_at) VALUES (?,?,NULL,NULL,?,?,?,100,50,0.9,?,?,NULL,?,?,NULL,NULL,DATE_ADD(NOW(),INTERVAL 30 DAY),NOW(),NOW())")
        ->execute([$public,$userId,'operations_'.$suffix,$level,$status,'Operations validation '.$runId.' '.$suffix,json_encode(['action'=>'review_demand_signal','secret'=>'not-for-admin-output'],JSON_THROW_ON_ERROR),'operations:'.$runId.':'.$suffix,$triggeredAt]);
    $id=(int)$pdo->lastInsertId();
    return ['id'=>$id,'public_id'=>$public];
}

function mg_doo_orchestration(PDO $pdo,array $signal,string $runId,string $suffix,string $type,string $status,string $updatedAt): array
{
    $public=mg_public_uuid();
    $pdo->prepare("INSERT INTO demand_signal_orchestrations (public_id,demand_signal_id,strategy_id,strategy_version,team_id,orchestration_type,workflow_run_id,swarm_run_id,status,recommendation_action,dispatch_key,input_fingerprint,attempt_count,last_error,claimed_at,started_at,completed_at,created_at,updated_at) VALUES (?,?,NULL,NULL,NULL,?,NULL,NULL,?,'review_demand_signal',?,?,1,?, ?,NULL,NULL,?,?)")
        ->execute([$public,(int)$signal['id'],$type,$status,hash('sha256','dispatch:'.$runId.':'.$suffix),hash('sha256','fingerprint:'.$runId.':'.$suffix),$status==='failed'?'Fixture failure secret':null,$updatedAt,$updatedAt,$updatedAt]);
    return ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$public];
}

$pdo=mg_db();
$runId='demand_ops_'.bin2hex(random_bytes(6));
$summary=[
    'suite'=>'demand_orchestration_operations_monitoring',
    'stale_claimed_detected'=>false,
    'stale_running_detected'=>false,
    'stale_approval_detected'=>false,
    'stale_review_detected'=>false,
    'recent_failure_detected'=>false,
    'critical_overdue_fails_readiness'=>false,
    'admin_list_filters'=>false,
    'admin_detail_is_sanitized'=>false,
    'checks_recorded'=>false,
    'permission_admin_only'=>false,
    'fixtures_clean'=>false,
];
$baseline=[
    'signals'=>(int)mg_doo_scalar($pdo,'SELECT COUNT(*) FROM demand_agent_signals'),
    'orchestrations'=>(int)mg_doo_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestrations'),
    'events'=>(int)mg_doo_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_events'),
    'checks'=>(int)mg_doo_scalar($pdo,'SELECT COUNT(*) FROM operational_check_results'),
];

$pdo->beginTransaction();
try{
    $email=$runId.'@example.test';
    $userId=mg_it_user($pdo,$email,'Demand Operations Fixture');
    $fixtures=[];
    $fixtures['claimed']=mg_doo_orchestration($pdo,mg_doo_signal($pdo,$userId,$runId,'claimed','critical','open',date('Y-m-d H:i:s',time()-3600)),$runId,'claimed','workflow','claimed',date('Y-m-d H:i:s',time()-1800));
    $fixtures['running']=mg_doo_orchestration($pdo,mg_doo_signal($pdo,$userId,$runId,'running','warning','open',date('Y-m-d H:i:s',time()-10800)),$runId,'running','swarm','running',date('Y-m-d H:i:s',time()-7200));
    $fixtures['approval']=mg_doo_orchestration($pdo,mg_doo_signal($pdo,$userId,$runId,'approval','opportunity','open',date('Y-m-d H:i:s',time()-93600)),$runId,'approval','workflow','awaiting_approval',date('Y-m-d H:i:s',time()-90000));
    $fixtures['review']=mg_doo_orchestration($pdo,mg_doo_signal($pdo,$userId,$runId,'review','warning','open',date('Y-m-d H:i:s',time()-93600)),$runId,'review','alert_only','review_required',date('Y-m-d H:i:s',time()-90000));
    $fixtures['failed']=mg_doo_orchestration($pdo,mg_doo_signal($pdo,$userId,$runId,'failed','critical','open',date('Y-m-d H:i:s',time()-7200)),$runId,'failed','workflow','failed',date('Y-m-d H:i:s',time()-3600));
    $fixtures['completed']=mg_doo_orchestration($pdo,mg_doo_signal($pdo,$userId,$runId,'completed','info','resolved',date('Y-m-d H:i:s',time()-600)),$runId,'completed','workflow','completed',date('Y-m-d H:i:s',time()-300));
    $pdo->prepare('INSERT INTO demand_signal_orchestration_events (public_id,orchestration_id,event_key,event_type,payload_json,created_at) VALUES (?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(),$fixtures['claimed']['id'],'fixture_event','claimed',json_encode(['secret'=>'must-not-leak'],JSON_THROW_ON_ERROR)]);

    $checks=mg_operations_demand_orchestration_health($pdo);
    $indexed=[];foreach($checks as $check)$indexed[$check['key']]=$check;
    $metrics=$indexed['demand_orchestration_queue']['details'];
    mg_doo_assert((int)$metrics['stale_claimed']===1,'Stale claimed orchestration was not detected.');
    mg_doo_assert((int)$metrics['stale_running']===1,'Stale running orchestration was not detected.');
    mg_doo_assert((int)$metrics['stale_approval']===1,'Stale approval orchestration was not detected.');
    mg_doo_assert((int)$metrics['stale_review']===1,'Stale review orchestration was not detected.');
    mg_doo_assert((int)$metrics['failed_recent']===1,'Recent failed orchestration was not detected.');
    mg_doo_assert((int)$metrics['critical_overdue']===2&&$indexed['demand_orchestration_queue']['status']==='fail','Critical overdue signals did not fail readiness.');
    mg_doo_assert($indexed['demand_orchestration_failures']['status']==='warn','Recent failures did not warn readiness.');
    mg_doo_assert($indexed['demand_orchestration_reviews']['status']==='warn','Overdue reviews did not warn readiness.');
    $summary['stale_claimed_detected']=true;$summary['stale_running_detected']=true;$summary['stale_approval_detected']=true;
    $summary['stale_review_detected']=true;$summary['recent_failure_detected']=true;$summary['critical_overdue_fails_readiness']=true;

    $failedRows=mg_operations_list_demand_orchestrations($pdo,['status'=>'failed','signal_level'=>'critical','orchestration_type'=>'workflow','limit'=>10]);
    mg_doo_assert(count($failedRows)===1&&(string)$failedRows[0]['public_id']===$fixtures['failed']['public_id'],'Administrator filters returned the wrong orchestration.');
    $summary['admin_list_filters']=true;

    $detail=mg_operations_get_demand_orchestration($pdo,$fixtures['claimed']['public_id']);
    mg_doo_assert(is_array($detail)&&count($detail['events'])===1,'Administrator detail did not include safe event history.');
    foreach(['dispatch_key','input_fingerprint','recommendation_json','payload_json'] as $forbidden){
        mg_doo_assert(!array_key_exists($forbidden,$detail),'Administrator detail exposed '.$forbidden.'.');
        mg_doo_assert(!array_key_exists($forbidden,$detail['events'][0]),'Administrator event exposed '.$forbidden.'.');
    }
    $summary['admin_detail_is_sanitized']=true;

    $beforeChecks=(int)mg_doo_scalar($pdo,'SELECT COUNT(*) FROM operational_check_results');
    mg_operations_record_demand_orchestration_health($pdo);
    mg_doo_assert((int)mg_doo_scalar($pdo,'SELECT COUNT(*) FROM operational_check_results')===$beforeChecks+3,'Operational health checks were not recorded exactly once.');
    $summary['checks_recorded']=true;

    $assigned=(int)mg_doo_scalar($pdo,"SELECT COUNT(*) FROM role_permissions rp INNER JOIN roles r ON r.id=rp.role_id INNER JOIN permissions p ON p.id=rp.permission_id WHERE p.slug='operations.orchestrations.view' AND r.slug IN ('admin','super_admin')");
    $other=(int)mg_doo_scalar($pdo,"SELECT COUNT(*) FROM role_permissions rp INNER JOIN roles r ON r.id=rp.role_id INNER JOIN permissions p ON p.id=rp.permission_id WHERE p.slug='operations.orchestrations.view' AND r.slug NOT IN ('admin','super_admin')");
    mg_doo_assert($assigned>=1&&$other===0,'Orchestration observability permission is not administrator-only.');
    $summary['permission_admin_only']=true;

    $pdo->rollBack();
    $after=[
        'signals'=>(int)mg_doo_scalar($pdo,'SELECT COUNT(*) FROM demand_agent_signals'),
        'orchestrations'=>(int)mg_doo_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestrations'),
        'events'=>(int)mg_doo_scalar($pdo,'SELECT COUNT(*) FROM demand_signal_orchestration_events'),
        'checks'=>(int)mg_doo_scalar($pdo,'SELECT COUNT(*) FROM operational_check_results'),
    ];
    mg_doo_assert($baseline===$after,'Demand orchestration operations fixtures remain after rollback.');
    mg_doo_assert((int)mg_doo_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email=?',[$email])===0,'Demand orchestration operations user remains after rollback.');
    $summary['fixtures_clean']=true;
    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
