<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-agent-phase3.php';

function mg_admin_agent_phase3_incident_mode_for_payload(array $payload): string
{
    $mode=strtolower(trim((string)($payload['mode_slug']??'')));
    if(isset(mg_ops_incident_modes()[$mode])) return $mode;
    $domain=strtolower(trim((string)($payload['domain']??'')));
    return match($domain){
        'commerce','payments'=>'payment_outage',
        'claims','redemption'=>'claim_redemption_issue',
        'notifications'=>'notification_delivery_issue',
        'security'=>'fraud_risk_spike',
        'campaigns','storefront'=>'catalog_publishing_issue',
        default=>'fulfillment_backlog',
    };
}

function mg_admin_agent_phase3_execute_adapter(PDO $pdo,string $adapterKey,int $adminId,array $payload): array
{
    if($adapterKey!=='declare_operations_incident') return mg_admin_agent_phase2_execute_adapter($pdo,$adapterKey,$adminId,$payload);
    $workspacePublic=trim((string)($payload['workspace_id']??''));
    $workspace=$workspacePublic!==''?mg_admin_agent_safe_row($pdo,'SELECT id,public_id,title,severity,summary,status FROM admin_agent_incident_workspaces WHERE public_id=? LIMIT 1',[$workspacePublic]):[];
    $mode=mg_admin_agent_phase3_incident_mode_for_payload($payload);
    $modeConfig=mg_ops_incident_mode($mode);
    $title=mb_substr(trim((string)($payload['title']??($workspace['title']??$modeConfig['title']))),0,180);
    $impact=trim((string)($payload['impact_summary']??($workspace['summary']??$modeConfig['impact'])));
    if($impact==='') throw new InvalidArgumentException('Incident impact summary is required.');
    $severity=strtolower(trim((string)($payload['severity']??($workspace['severity']??$modeConfig['severity']))));
    if(!in_array($severity,['low','medium','high','critical'],true)) $severity=$modeConfig['severity'];
    $result=mg_ops_incident_declare($pdo,$adminId,[
        'mode_slug'=>$mode,'severity'=>$severity,'title'=>$title,'impact_summary'=>$impact,
        'owner_user_id'=>isset($payload['owner_user_id'])?(int)$payload['owner_user_id']:$adminId,
    ]);
    if($workspace!==[]){
        $ops=mg_admin_agent_safe_row($pdo,'SELECT id FROM admin_ops_incidents WHERE public_id=? LIMIT 1',[(string)$result['id']]);
        $pdo->prepare('UPDATE admin_agent_incident_workspaces SET ops_incident_id=?,status="declared",incident_commander_user_id=COALESCE(incident_commander_user_id,?),updated_at=NOW() WHERE id=?')->execute([$ops!==[]?(int)$ops['id']:null,$adminId,(int)$workspace['id']]);
        mg_admin_agent_phase3_timeline($pdo,(int)$workspace['id'],'operations_incident_declared','Operations incident declared',$title.': '.$impact,'admin_ops_incidents',(string)$result['id'],$adminId,['mode_slug'=>$mode,'severity'=>$severity]);
    }
    return ['adapter'=>'declare_operations_incident','incident'=>$result,'workspace_id'=>$workspacePublic?:null,'changes_applied'=>true];
}

function mg_admin_agent_phase3_execute_action(PDO $pdo,int $adminId,array $input): array
{
    $executionPublic=trim((string)($input['execution_id']??''));
    $confirmation=trim((string)($input['confirmation']??''));
    $pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare('SELECT x.*,a.adapter_key,a.enabled,a.execution_mode,a.requires_confirmation,r.public_id review_public_id,r.payload_json,r.status review_status FROM admin_agent_remediation_executions x JOIN admin_agent_remediation_adapters a ON a.id=x.adapter_id JOIN admin_agent_action_reviews r ON r.id=x.review_id WHERE x.public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$executionPublic]);
        $execution=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$execution) throw new InvalidArgumentException('Approved remediation execution not found.');
        if((string)$execution['status']==='succeeded'){ $pdo->commit(); return ['execution_id'=>$executionPublic,'status'=>'succeeded','already_completed'=>true]; }
        if((string)$execution['status']!=='approved'||(string)$execution['review_status']!=='approved') throw new InvalidArgumentException('This remediation is not in an approved state.');
        if(!(bool)$execution['enabled']||(string)$execution['execution_mode']!=='in_process') throw new InvalidArgumentException('This remediation adapter is disabled.');
        $expected='EXECUTE '.(string)$execution['adapter_key'];
        if((bool)$execution['requires_confirmation']&&!hash_equals($expected,$confirmation)) throw new InvalidArgumentException('Type the exact execution confirmation: '.$expected);
        $pdo->prepare('UPDATE admin_agent_remediation_executions SET status="running",executed_by_user_id=?,started_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$adminId,(int)$execution['id']]);
        $pdo->commit();
    }catch(Throwable $error){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    try{
        $payload=mg_admin_agent_json($execution['payload_json']??null);
        $result=mg_admin_agent_phase3_execute_adapter($pdo,(string)$execution['adapter_key'],$adminId,$payload);
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE admin_agent_remediation_executions SET status="succeeded",result_json=?,completed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),(int)$execution['id']]);
        $pdo->prepare('UPDATE admin_agent_action_reviews SET status="executed",executed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$execution['review_id']]);
        $pdo->commit();
        mg_audit('admin_agent_phase3_remediation_executed','system',['execution_id'=>$executionPublic,'review_id'=>$execution['review_public_id'],'action_key'=>$execution['adapter_key'],'success'=>true],$adminId);
        mg_security_log('info','admin_agent.phase3_remediation_executed','Approved Main Admin Agent Phase 3 remediation executed.',['execution_id'=>$executionPublic,'action_key'=>$execution['adapter_key']],$adminId);
        return ['execution_id'=>$executionPublic,'review_id'=>(string)$execution['review_public_id'],'action_key'=>(string)$execution['adapter_key'],'status'=>'succeeded','result'=>$result];
    }catch(Throwable $error){
        if($pdo->inTransaction()) $pdo->rollBack();
        $pdo->prepare('UPDATE admin_agent_remediation_executions SET status="failed",failure_code=?,failure_message=?,completed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$error::class,mb_substr($error->getMessage(),0,1000),(int)$execution['id']]);
        mg_security_log('error','admin_agent.phase3_remediation_failed','Approved Main Admin Agent Phase 3 remediation failed.',['execution_id'=>$executionPublic,'action_key'=>$execution['adapter_key'],'exception_class'=>$error::class],$adminId);
        throw $error;
    }
}
