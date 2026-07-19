<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-agent-phase4.php';
require_once dirname(__DIR__) . '/api/admin/_ops_reviews.php';

function mg_admin_agent_phase4_execute_adapter(PDO $pdo,string $adapterKey,int $adminId,array $payload): array
{
    if($adapterKey!=='create_prevention_followup') return mg_admin_agent_phase3_execute_adapter($pdo,$adapterKey,$adminId,$payload);

    $learningPublic=trim((string)($payload['learning_id']??''));
    $followupPublic=trim((string)($payload['followup_id']??''));
    if($learningPublic===''||$followupPublic==='') throw new InvalidArgumentException('Incident learning and prevention follow-up identifiers are required.');

    $learning=mg_admin_agent_safe_row($pdo,'SELECT l.id,l.public_id,l.status,l.summary_text,l.impact_text,l.root_cause_hypothesis,l.ops_incident_id,w.public_id workspace_public,o.public_id ops_public,o.status ops_status FROM admin_agent_incident_learning l JOIN admin_agent_incident_workspaces w ON w.id=l.workspace_id LEFT JOIN admin_ops_incidents o ON o.id=l.ops_incident_id WHERE l.public_id=? LIMIT 1',[$learningPublic]);
    if($learning===[]) throw new InvalidArgumentException('Incident learning record not found.');
    if($learning['ops_incident_id']===null||(string)$learning['ops_status']!=='resolved') throw new InvalidArgumentException('A resolved linked operations incident is required before creating a prevention follow-up.');

    $followup=mg_admin_agent_safe_row($pdo,'SELECT id,public_id,title,description,priority,status,owner_user_id,due_at FROM admin_agent_prevention_followups WHERE public_id=? AND learning_id=? LIMIT 1',[$followupPublic,(int)$learning['id']]);
    if($followup===[]) throw new InvalidArgumentException('Prevention follow-up proposal not found.');
    if(in_array((string)$followup['status'],['completed','dismissed'],true)) throw new InvalidArgumentException('This prevention follow-up is already closed.');

    $ownerId=isset($payload['owner_user_id'])&&(int)$payload['owner_user_id']>0?(int)$payload['owner_user_id']:($followup['owner_user_id']!==null?(int)$followup['owner_user_id']:$adminId);
    $dueRaw=trim((string)($payload['due_at']??($followup['due_at']??'')));
    $dueAt=$dueRaw!==''?gmdate('Y-m-d H:i:s',strtotime($dueRaw)?:strtotime('+14 days')):gmdate('Y-m-d H:i:s',strtotime('+14 days'));
    $reviewSummary=mb_substr((string)$learning['summary_text']."\n\nRoot-cause hypothesis: ".(string)$learning['root_cause_hypothesis'],0,4000);
    $impact=mb_substr((string)$learning['impact_text'],0,4000);
    $actionItems=mb_substr((string)$followup['title']."\n".(string)$followup['description'],0,4000);

    $review=mg_ops_review_save($pdo,$adminId,[
        'incident_id'=>(string)$learning['ops_public'],
        'review_summary'=>$reviewSummary,
        'customer_impact'=>$impact!==''?$impact:'No verified customer impact was recorded. Administrator review remains required.',
        'merchant_impact'=>$impact!==''?$impact:'No verified merchant impact was recorded. Administrator review remains required.',
        'action_items'=>$actionItems,
        'followup_owner_user_id'=>$ownerId,
        'followup_due_at'=>$dueAt,
        'status'=>'followup_open',
    ]);
    $reviewRow=mg_admin_agent_safe_row($pdo,'SELECT id FROM admin_ops_incident_reviews WHERE public_id=? LIMIT 1',[(string)$review['id']]);

    $pdo->prepare('UPDATE admin_agent_incident_learning SET review_id=?,status="review_ready",updated_at=NOW() WHERE id=?')->execute([$reviewRow!==[]?(int)$reviewRow['id']:null,(int)$learning['id']]);
    $pdo->prepare('UPDATE admin_agent_prevention_followups SET review_id=?,status="approved",owner_user_id=?,due_at=?,approved_by_user_id=?,approved_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$reviewRow!==[]?(int)$reviewRow['id']:null,$ownerId,$dueAt,$adminId,(int)$followup['id']]);

    mg_audit('admin_agent_phase4_prevention_followup_created','system',['learning_id'=>$learningPublic,'followup_id'=>$followupPublic,'review_id'=>$review['id'],'ops_incident_id'=>$learning['ops_public']],$adminId);
    mg_security_log('info','admin_agent.phase4_prevention_followup_created','Approved Admin Agent prevention follow-up created in the incident-review workflow.',['learning_id'=>$learningPublic,'followup_id'=>$followupPublic,'review_id'=>$review['id']],$adminId);

    return ['adapter'=>'create_prevention_followup','learning_id'=>$learningPublic,'followup_id'=>$followupPublic,'incident_review'=>$review,'owner_user_id'=>$ownerId,'due_at'=>$dueAt,'changes_applied'=>true];
}

function mg_admin_agent_phase4_execute_action(PDO $pdo,int $adminId,array $input): array
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
        $result=mg_admin_agent_phase4_execute_adapter($pdo,(string)$execution['adapter_key'],$adminId,$payload);
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE admin_agent_remediation_executions SET status="succeeded",result_json=?,completed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),(int)$execution['id']]);
        $pdo->prepare('UPDATE admin_agent_action_reviews SET status="executed",executed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$execution['review_id']]);
        $pdo->commit();
        mg_audit('admin_agent_phase4_remediation_executed','system',['execution_id'=>$executionPublic,'review_id'=>$execution['review_public_id'],'action_key'=>$execution['adapter_key'],'success'=>true],$adminId);
        mg_security_log('info','admin_agent.phase4_remediation_executed','Approved Main Admin Agent Phase 4 remediation executed.',['execution_id'=>$executionPublic,'action_key'=>$execution['adapter_key']],$adminId);
        return ['execution_id'=>$executionPublic,'review_id'=>(string)$execution['review_public_id'],'action_key'=>(string)$execution['adapter_key'],'status'=>'succeeded','result'=>$result];
    }catch(Throwable $error){
        if($pdo->inTransaction()) $pdo->rollBack();
        $pdo->prepare('UPDATE admin_agent_remediation_executions SET status="failed",failure_code=?,failure_message=?,completed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$error::class,mb_substr($error->getMessage(),0,1000),(int)$execution['id']]);
        mg_security_log('error','admin_agent.phase4_remediation_failed','Approved Main Admin Agent Phase 4 remediation failed.',['execution_id'=>$executionPublic,'action_key'=>$execution['adapter_key'],'exception_class'=>$error::class],$adminId);
        throw $error;
    }
}
