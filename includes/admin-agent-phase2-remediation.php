<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-agent-phase2.php';

function mg_admin_agent_phase2_action_catalog(): array
{
    return [
        'run_admin_agent_scan'=>['system','Run full Admin Agent analysis','low'],
        'run_ai_credit_reconciliation'=>['ai_accounting','Run AI credit reconciliation','medium'],
        'generate_migration_plan'=>['database','Generate migration repair plan','low'],
        'investigate_security_events'=>['security','Generate security investigation package','low'],
        'run_queue_automation'=>['operations','Run queue automation','high'],
        'retry_failed_notifications'=>['notifications','Retry failed notifications','high'],
        'declare_operations_incident'=>['operations','Declare operations incident','high'],
    ];
}

function mg_admin_agent_phase2_request_action(PDO $pdo, int $adminId, array $input): array
{
    $actionKey = strtolower(trim((string)($input['action_key'] ?? '')));
    $catalog = mg_admin_agent_phase2_action_catalog();
    if (!isset($catalog[$actionKey])) throw new InvalidArgumentException('This Admin Agent action is not review-enabled.');
    [$domain,$title,$risk] = $catalog[$actionKey];
    $findingPublic = trim((string)($input['finding_id'] ?? ''));
    $findingId = null;
    if ($findingPublic !== '') {
        $stmt = $pdo->prepare('SELECT id FROM admin_agent_findings WHERE public_id=? LIMIT 1');
        $stmt->execute([$findingPublic]);
        $value = $stmt->fetchColumn();
        if ($value !== false) $findingId = (int)$value;
    }
    $rationale = mb_substr(trim((string)($input['rationale'] ?? 'Requested from the Main Admin Agent review queue.')),0,2000);
    if ($rationale === '') throw new InvalidArgumentException('Enter a rationale for the remediation review.');
    $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
    $idempotency = hash('sha256',$adminId.'|'.$actionKey.'|'.($findingPublic ?: 'global').'|'.json_encode($payload));
    $public = mg_public_id();
    $pdo->prepare('INSERT INTO admin_agent_action_reviews (public_id,idempotency_key,requested_by_user_id,finding_id,action_key,domain,title,rationale,payload_json,risk_level,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,? ,"pending",NOW(),NOW()) ON DUPLICATE KEY UPDATE rationale=VALUES(rationale),updated_at=NOW()')->execute([$public,$idempotency,$adminId,$findingId,$actionKey,$domain,$title,$rationale,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$risk]);
    $row = mg_admin_agent_safe_row($pdo,'SELECT public_id,status FROM admin_agent_action_reviews WHERE idempotency_key=? LIMIT 1',[$idempotency]);
    mg_audit('admin_agent_action_requested','system',['action_key'=>$actionKey,'finding_id'=>$findingPublic ?: null,'review_only'=>true],$adminId);
    mg_security_log('info','admin_agent.action_requested','Main Admin Agent review-gated action requested.',['action_key'=>$actionKey,'review_only'=>true],$adminId);
    return ['id'=>(string)($row['public_id'] ?? $public),'action_key'=>$actionKey,'status'=>(string)($row['status'] ?? 'pending'),'review_required'=>true,'executed'=>false];
}

function mg_admin_agent_phase2_remediation_state(PDO $pdo): array
{
    if (!mg_admin_agent_phase2_ready($pdo)) return ['adapters'=>[],'reviews'=>[],'executions'=>[]];
    $adapters = array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],'adapter_key'=>(string)$row['adapter_key'],'label'=>(string)$row['label'],'domain'=>(string)$row['domain'],'description'=>(string)$row['description'],
        'risk_level'=>(string)$row['risk_level'],'execution_mode'=>(string)$row['execution_mode'],'enabled'=>(bool)$row['enabled'],'requires_confirmation'=>(bool)$row['requires_confirmation'],
    ],mg_admin_agent_safe_rows($pdo,'SELECT public_id,adapter_key,label,domain,description,risk_level,execution_mode,enabled,requires_confirmation FROM admin_agent_remediation_adapters ORDER BY enabled DESC,risk_level,domain,label'));
    $reviews = array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],'action_key'=>(string)$row['action_key'],'domain'=>(string)$row['domain'],'title'=>(string)$row['title'],'rationale'=>(string)($row['rationale'] ?? ''),'risk_level'=>(string)$row['risk_level'],'status'=>(string)$row['status'],
        'requested_by_user_id'=>(int)$row['requested_by_user_id'],'reviewed_by_user_id'=>$row['reviewed_by_user_id'] !== null ? (int)$row['reviewed_by_user_id'] : null,'review_note'=>$row['review_note'],'reviewed_at'=>$row['reviewed_at'],'executed_at'=>$row['executed_at'],'created_at'=>(string)$row['created_at'],
        'adapter_enabled'=>$row['adapter_enabled'] !== null ? (bool)$row['adapter_enabled'] : false,'execution_id'=>$row['execution_public_id'],'execution_status'=>$row['execution_status'],
    ],mg_admin_agent_safe_rows($pdo,'SELECT r.public_id,r.action_key,r.domain,r.title,r.rationale,r.risk_level,r.status,r.requested_by_user_id,r.reviewed_by_user_id,r.review_note,r.reviewed_at,r.executed_at,r.created_at,a.enabled adapter_enabled,x.public_id execution_public_id,x.status execution_status FROM admin_agent_action_reviews r LEFT JOIN admin_agent_remediation_adapters a ON a.adapter_key=r.action_key LEFT JOIN admin_agent_remediation_executions x ON x.review_id=r.id ORDER BY CASE r.status WHEN "pending" THEN 1 WHEN "approved" THEN 2 ELSE 3 END,r.created_at DESC LIMIT 100'));
    $executions = array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],'review_id'=>(string)$row['review_public_id'],'adapter_key'=>(string)$row['adapter_key'],'label'=>(string)$row['label'],'status'=>(string)$row['status'],'approval_note'=>$row['approval_note'],'failure_code'=>$row['failure_code'],'failure_message'=>$row['failure_message'],'approved_at'=>(string)$row['approved_at'],'started_at'=>$row['started_at'],'completed_at'=>$row['completed_at'],
    ],mg_admin_agent_safe_rows($pdo,'SELECT x.public_id,x.status,x.approval_note,x.failure_code,x.failure_message,x.approved_at,x.started_at,x.completed_at,r.public_id review_public_id,a.adapter_key,a.label FROM admin_agent_remediation_executions x JOIN admin_agent_action_reviews r ON r.id=x.review_id JOIN admin_agent_remediation_adapters a ON a.id=x.adapter_id ORDER BY x.created_at DESC LIMIT 100'));
    return ['adapters'=>$adapters,'reviews'=>$reviews,'executions'=>$executions];
}

function mg_admin_agent_phase2_review_action(PDO $pdo,int $adminId,array $input): array
{
    $publicId=trim((string)($input['review_id']??''));
    $decision=strtolower(trim((string)($input['decision']??'')));
    $note=mb_substr(trim((string)($input['note']??'')),0,1000);
    if (!in_array($decision,['approve','reject'],true)) throw new InvalidArgumentException('Choose approve or reject.');
    if ($note==='') throw new InvalidArgumentException('A review note is required.');
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT r.*,a.id adapter_id,a.public_id adapter_public_id,a.enabled,a.execution_mode,a.risk_level adapter_risk FROM admin_agent_action_reviews r LEFT JOIN admin_agent_remediation_adapters a ON a.adapter_key=r.action_key WHERE r.public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$publicId]);
        $review=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$review) throw new InvalidArgumentException('Action review not found.');
        if ((string)$review['status']!=='pending') throw new InvalidArgumentException('Only pending action reviews can be decided.');
        if ($decision==='reject') {
            $pdo->prepare('UPDATE admin_agent_action_reviews SET status="rejected",reviewed_by_user_id=?,review_note=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$adminId,$note,(int)$review['id']]);
            $pdo->commit();
            mg_audit('admin_agent_action_rejected','system',['review_id'=>$publicId,'action_key'=>$review['action_key']],$adminId);
            return ['review_id'=>$publicId,'action_key'=>(string)$review['action_key'],'status'=>'rejected','executed'=>false];
        }
        if ($review['adapter_id']===null || !(bool)$review['enabled'] || (string)$review['execution_mode']!=='in_process') throw new InvalidArgumentException('This remediation adapter is not enabled for execution.');
        $idempotency=hash('sha256','review|'.(int)$review['id'].'|'.(int)$review['adapter_id']);
        $executionPublic=mg_public_id();
        $pdo->prepare('INSERT INTO admin_agent_remediation_executions (public_id,idempotency_key,review_id,adapter_id,approved_by_user_id,status,approval_note,approved_at,created_at,updated_at) VALUES (?,?,?,?,?,"approved",?,NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE approved_by_user_id=VALUES(approved_by_user_id),approval_note=VALUES(approval_note),approved_at=NOW(),updated_at=NOW()')->execute([$executionPublic,$idempotency,(int)$review['id'],(int)$review['adapter_id'],$adminId,$note]);
        $pdo->prepare('UPDATE admin_agent_action_reviews SET status="approved",reviewed_by_user_id=?,review_note=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$adminId,$note,(int)$review['id']]);
        $execution=mg_admin_agent_safe_row($pdo,'SELECT public_id,status FROM admin_agent_remediation_executions WHERE idempotency_key=? LIMIT 1',[$idempotency]);
        $pdo->commit();
        mg_audit('admin_agent_action_approved','system',['review_id'=>$publicId,'action_key'=>$review['action_key'],'execution_id'=>$execution['public_id']],$adminId);
        mg_security_log('info','admin_agent.action_approved','Main Admin Agent remediation approved.',['review_id'=>$publicId,'action_key'=>$review['action_key']],$adminId);
        return ['review_id'=>$publicId,'execution_id'=>(string)$execution['public_id'],'action_key'=>(string)$review['action_key'],'status'=>'approved','confirmation'=>'EXECUTE '.(string)$review['action_key'],'executed'=>false];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_admin_agent_phase2_execute_adapter(PDO $pdo,string $adapterKey,int $adminId,array $payload): array
{
    if ($adapterKey==='run_admin_agent_scan') {
        return mg_admin_agent_phase2_run($pdo,['trigger_source'=>'api','initiated_by_user_id'=>$adminId]);
    }
    if ($adapterKey==='run_ai_credit_reconciliation') {
        require_once __DIR__.'/ai/ai-credit-reconciliation.php';
        return mg_ai_reconciliation_run($pdo,['trigger_source'=>'admin','initiated_by_user_id'=>$adminId,'provider_key'=>(string)($payload['provider_key']??'anthropic'),'days'=>max(1,min(90,(int)($payload['days']??30)))]);
    }
    if ($adapterKey==='generate_migration_plan') {
        $result=mg_admin_agent_monitor_migrations_runtime($pdo);
        return ['plan_type'=>'read_only','missing_migrations'=>$result['findings'][0]['evidence']['missing']??[],'metrics'=>$result['metrics']??[],'changes_applied'=>false];
    }
    if ($adapterKey==='investigate_security_events') {
        $events=mg_admin_agent_events_runtime($pdo,0,100,'security');
        $findings=array_values(array_filter(mg_admin_agent_findings($pdo,['status'=>'active','limit'=>100]),static fn(array $f):bool=>$f['domain']==='security'));
        return ['package_type'=>'read_only','events'=>$events,'findings'=>$findings,'generated_at'=>gmdate('Y-m-d H:i:s'),'changes_applied'=>false];
    }
    throw new RuntimeException('This remediation adapter is not executable.');
}

function mg_admin_agent_phase2_execute_action(PDO $pdo,int $adminId,array $input): array
{
    $executionPublic=trim((string)($input['execution_id']??''));
    $confirmation=trim((string)($input['confirmation']??''));
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT x.*,a.adapter_key,a.enabled,a.execution_mode,a.requires_confirmation,r.public_id review_public_id,r.payload_json,r.status review_status FROM admin_agent_remediation_executions x JOIN admin_agent_remediation_adapters a ON a.id=x.adapter_id JOIN admin_agent_action_reviews r ON r.id=x.review_id WHERE x.public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$executionPublic]);
        $execution=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$execution) throw new InvalidArgumentException('Approved remediation execution not found.');
        if ((string)$execution['status']==='succeeded') { $pdo->commit(); return ['execution_id'=>$executionPublic,'status'=>'succeeded','already_completed'=>true]; }
        if ((string)$execution['status']!=='approved' || (string)$execution['review_status']!=='approved') throw new InvalidArgumentException('This remediation is not in an approved state.');
        if (!(bool)$execution['enabled'] || (string)$execution['execution_mode']!=='in_process') throw new InvalidArgumentException('This remediation adapter is disabled.');
        $expected='EXECUTE '.(string)$execution['adapter_key'];
        if ((bool)$execution['requires_confirmation'] && !hash_equals($expected,$confirmation)) throw new InvalidArgumentException('Type the exact execution confirmation: '.$expected);
        $pdo->prepare('UPDATE admin_agent_remediation_executions SET status="running",executed_by_user_id=?,started_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$adminId,(int)$execution['id']]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    try {
        $payload=mg_admin_agent_json($execution['payload_json']??null);
        $result=mg_admin_agent_phase2_execute_adapter($pdo,(string)$execution['adapter_key'],$adminId,$payload);
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE admin_agent_remediation_executions SET status="succeeded",result_json=?,completed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),(int)$execution['id']]);
        $pdo->prepare('UPDATE admin_agent_action_reviews SET status="executed",executed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$execution['review_id']]);
        $pdo->commit();
        mg_audit('admin_agent_remediation_executed','system',['execution_id'=>$executionPublic,'review_id'=>$execution['review_public_id'],'action_key'=>$execution['adapter_key'],'success'=>true],$adminId);
        mg_security_log('info','admin_agent.remediation_executed','Approved Main Admin Agent remediation executed.',['execution_id'=>$executionPublic,'action_key'=>$execution['adapter_key']],$adminId);
        return ['execution_id'=>$executionPublic,'review_id'=>(string)$execution['review_public_id'],'action_key'=>(string)$execution['adapter_key'],'status'=>'succeeded','result'=>$result];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->prepare('UPDATE admin_agent_remediation_executions SET status="failed",failure_code=?,failure_message=?,completed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$error::class,mb_substr($error->getMessage(),0,1000),(int)$execution['id']]);
        mg_security_log('error','admin_agent.remediation_failed','Approved Main Admin Agent remediation failed.',['execution_id'=>$executionPublic,'action_key'=>$execution['adapter_key'],'exception_class'=>$error::class],$adminId);
        throw new RuntimeException('The approved remediation failed. Review the execution record and security log.');
    }
}

function mg_admin_agent_phase2_apply_intelligence_action(PDO $pdo,int $adminId,array $input): array
{
    $sourceType=strtolower(trim((string)($input['source_type']??'')));
    $publicId=trim((string)($input['source_id']??''));
    $action=strtolower(trim((string)($input['action_key']??'acknowledge')));
    $note=mb_substr(trim((string)($input['note']??'')),0,1000);
    $table=['anomaly'=>'admin_agent_anomalies','correlation'=>'admin_agent_correlations'][$sourceType]??null;
    if ($table===null) throw new InvalidArgumentException('Unknown intelligence source type.');
    if (!in_array($action,['acknowledge','under_review','resolve','dismiss','reopen'],true)) throw new InvalidArgumentException('Unknown intelligence action.');
    if (in_array($action,['resolve','dismiss'],true)&&$note==='') throw new InvalidArgumentException('A resolution or dismissal note is required.');
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT id,status FROM '.$table.' WHERE public_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$publicId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new InvalidArgumentException('Intelligence record not found.');
        if ($action==='acknowledge') $pdo->prepare('UPDATE '.$table.' SET status="acknowledged",acknowledged_by_user_id=?,acknowledged_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$adminId,(int)$row['id']]);
        elseif ($action==='under_review') {
            if ($sourceType==='correlation') $pdo->prepare('UPDATE '.$table.' SET status="under_review",assigned_admin_user_id=COALESCE(assigned_admin_user_id,?),updated_at=NOW() WHERE id=?')->execute([$adminId,(int)$row['id']]);
            else $pdo->prepare('UPDATE '.$table.' SET status="under_review",updated_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
        }
        elseif ($action==='resolve') $pdo->prepare('UPDATE '.$table.' SET status="resolved",resolved_by_user_id=?,resolved_at=NOW(),resolution_note=?,updated_at=NOW() WHERE id=?')->execute([$adminId,$note,(int)$row['id']]);
        elseif ($action==='dismiss') $pdo->prepare('UPDATE '.$table.' SET status="dismissed",resolved_by_user_id=?,resolved_at=NOW(),resolution_note=?,updated_at=NOW() WHERE id=?')->execute([$adminId,$note,(int)$row['id']]);
        else $pdo->prepare('UPDATE '.$table.' SET status="open",resolved_by_user_id=NULL,resolved_at=NULL,resolution_note=NULL,updated_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
        $pdo->commit();
        mg_audit('admin_agent_'.$sourceType.'_'.$action,'system',[$sourceType.'_id'=>$publicId,'note_recorded'=>$note!==''],$adminId);
        return ['source_type'=>$sourceType,'source_id'=>$publicId,'action'=>$action,'updated'=>true];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function mg_admin_agent_phase2_acknowledge_escalation(PDO $pdo,int $adminId,array $input): array
{
    $publicId=trim((string)($input['escalation_id']??''));
    $stmt=$pdo->prepare('UPDATE admin_agent_escalations SET status="acknowledged",acknowledged_by_user_id=?,acknowledged_at=NOW(),updated_at=NOW() WHERE public_id=? AND status IN ("scheduled","sent")');
    $stmt->execute([$adminId,$publicId]);
    if ($stmt->rowCount()===0) throw new InvalidArgumentException('Active escalation not found.');
    mg_audit('admin_agent_escalation_acknowledged','system',['escalation_id'=>$publicId],$adminId);
    return ['escalation_id'=>$publicId,'status'=>'acknowledged'];
}
