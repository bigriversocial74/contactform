<?php
declare(strict_types=1);

require_once __DIR__ . '/_execution.php';

final class MgAgentWorkflowException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 409){parent::__construct($message);}
}

function mg_agent_safe_object(mixed $value,array $allowed):array
{
    $source=mg_agent_json_array($value);$safe=[];
    foreach($allowed as $key){if(array_key_exists($key,$source)&&is_scalar($source[$key]))$safe[$key]=$source[$key];}
    return $safe;
}

function mg_agent_safe_request(string $type,mixed $value):array
{
    return match($type){
        'create_operational_alert'=>mg_agent_safe_object($value,['title','message','action_url','expected_effect']),
        'acknowledge_demand_signal','resolve_demand_signal'=>mg_agent_safe_object($value,['summary','expected_effect']),
        'pause_distribution_program','resume_distribution_program'=>mg_agent_safe_object($value,['summary','expected_effect']),
        default=>[],
    };
}

function mg_agent_safe_run_input(mixed $value):array
{
    return mg_agent_safe_object($value,['reason','prompt','source','summary','demand_signal_id','distribution_program_id']);
}

function mg_agent_reconcile_approval_run(PDO $pdo,int $runId,?int $actorUserId=null):string
{
    $stmt=$pdo->prepare('SELECT status,COUNT(*) total FROM agent_workflow_actions WHERE run_id=? GROUP BY status');$stmt->execute([$runId]);$counts=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$counts[(string)$row['status']]=(int)$row['total'];
    if(($counts['approval_pending']??0)>0){$status='approval_pending';}
    else{
        $approved=$counts['approved']??0;$blocked=($counts['rejected']??0)+($counts['canceled']??0);
        $status=$approved>0?($blocked>0?'partially_completed':'approved'):'failed';
    }
    $pdo->prepare("UPDATE agent_workflow_runs SET status=?,approved_at=IF(?='approved',COALESCE(approved_at,NOW()),approved_at),completed_at=IF(?='failed',COALESCE(completed_at,NOW()),completed_at),updated_at=NOW() WHERE id=?")
        ->execute([$status,$status,$status,$runId]);
    mg_agent_execution_event($pdo,$runId,null,'run_approval_'.$status,$actorUserId,['counts'=>$counts]);
    return $status;
}

function mg_agent_expire_approvals(PDO $pdo,int $ownerUserId):int
{
    $stmt=$pdo->prepare("SELECT ar.id,ar.public_id,ar.run_id,ar.action_id FROM agent_approval_requests ar WHERE ar.owner_user_id=? AND ar.status='pending' AND ar.expires_at IS NOT NULL AND ar.expires_at<NOW() FOR UPDATE");
    $stmt->execute([$ownerUserId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);$runs=[];
    foreach($rows as $row){
        $pdo->prepare("UPDATE agent_approval_requests SET status='expired',updated_at=NOW() WHERE id=? AND status='pending'")->execute([(int)$row['id']]);
        $pdo->prepare("UPDATE agent_workflow_actions SET status='canceled',failure_message='Approval request expired.',completed_at=NOW(),updated_at=NOW() WHERE id=? AND status='approval_pending'")->execute([(int)$row['action_id']]);
        mg_agent_execution_event($pdo,(int)$row['run_id'],(int)$row['action_id'],'approval_expired',null,['approval_id'=>(string)$row['public_id']]);$runs[(int)$row['run_id']]=true;
    }
    foreach(array_keys($runs) as $runId)mg_agent_reconcile_approval_run($pdo,$runId);
    return count($rows);
}

function mg_agent_action_projection(array $row):array
{
    $approvalId=$row['approval_public_id']??null;$expires=$row['approval_expires_at']??null;$expired=$expires!==null&&strtotime((string)$expires)<time();
    return [
        'id'=>(string)$row['action_public_id'],'sequence'=>(int)$row['sequence_no'],'type'=>(string)$row['action_type'],
        'target'=>['type'=>(string)$row['target_type'],'reference'=>(string)$row['target_reference']],
        'status'=>(string)$row['action_status'],'risk'=>(string)$row['risk_level'],'requires_approval'=>(bool)$row['requires_approval'],
        'request'=>mg_agent_safe_request((string)$row['action_type'],$row['request_json']??null),
        'approval'=>$approvalId!==null?['id'=>(string)$approvalId,'status'=>(string)$row['approval_status'],'requested_reason'=>(string)$row['requested_reason'],'decision_reason'=>$row['decision_reason']!==null?(string)$row['decision_reason']:null,'requested_at'=>(string)$row['approval_requested_at'],'decided_at'=>$row['approval_decided_at']!==null?(string)$row['approval_decided_at']:null,'expires_at'=>$expires!==null?(string)$expires:null,'expired'=>$expired,'expiring_soon'=>!$expired&&$expires!==null&&strtotime((string)$expires)<=time()+86400]:null,
        'created_at'=>(string)$row['action_created_at'],
    ];
}

function mg_agent_plan_projection(PDO $pdo,string $runPublicId,int $ownerUserId):array
{
    if(preg_match('/^[a-f0-9-]{36}$/i',$runPublicId)!==1)throw new MgAgentWorkflowException('Workflow run is required.',422);
    $stmt=$pdo->prepare('SELECT r.*,s.public_id strategy_public_id,s.name strategy_name,s.objective strategy_objective,s.version_no strategy_current_version,a.public_id agent_public_id,a.name agent_name FROM agent_workflow_runs r INNER JOIN agent_strategies s ON s.id=r.strategy_id INNER JOIN agents a ON a.id=r.agent_id WHERE r.public_id=? AND r.owner_user_id=? LIMIT 1');
    $stmt->execute([$runPublicId,$ownerUserId]);$run=$stmt->fetch(PDO::FETCH_ASSOC);if(!$run)throw new MgAgentWorkflowException('Workflow plan not found.',404);
    $actions=$pdo->prepare('SELECT wa.public_id action_public_id,wa.sequence_no,wa.action_type,wa.target_type,wa.target_reference,wa.status action_status,wa.risk_level,wa.requires_approval,wa.request_json,wa.created_at action_created_at,ar.public_id approval_public_id,ar.status approval_status,ar.requested_reason,ar.decision_reason,ar.requested_at approval_requested_at,ar.decided_at approval_decided_at,ar.expires_at approval_expires_at FROM agent_workflow_actions wa LEFT JOIN agent_approval_requests ar ON ar.action_id=wa.id WHERE wa.run_id=? ORDER BY wa.sequence_no');
    $actions->execute([(int)$run['id']]);$items=array_map('mg_agent_action_projection',$actions->fetchAll(PDO::FETCH_ASSOC));
    $plan=mg_agent_json_array($run['plan_json']??null);$counts=[];foreach($items as $item)$counts[$item['status']]=($counts[$item['status']]??0)+1;
    return ['id'=>(string)$run['public_id'],'status'=>(string)$run['status'],'duplicate'=>false,'trigger'=>['type'=>(string)$run['trigger_type'],'reference'=>$run['trigger_reference']!==null?(string)$run['trigger_reference']:null],'input'=>mg_agent_safe_run_input($run['input_json']??null),'strategy'=>['id'=>(string)$run['strategy_public_id'],'name'=>(string)$run['strategy_name'],'objective'=>(string)$run['strategy_objective'],'version'=>(int)($plan['strategy_version']??$run['strategy_current_version'])],'agent'=>['id'=>(string)$run['agent_public_id'],'name'=>(string)$run['agent_name']],'summary'=>['total'=>count($items),'counts'=>$counts,'approval_required'=>count(array_filter($items,static fn(array $item):bool=>$item['requires_approval']))],'actions'=>$items,'requested_at'=>(string)$run['requested_at'],'updated_at'=>(string)$run['updated_at']];
}

function mg_agent_approval_projection(PDO $pdo,array $row):array
{
    $plan=mg_agent_plan_projection($pdo,(string)$row['run_public_id'],(int)$row['owner_user_id']);
    $action=null;foreach($plan['actions'] as $candidate)if(($candidate['approval']['id']??null)===(string)$row['approval_public_id']){$action=$candidate;break;}
    if(!$action)throw new RuntimeException('Approval action is unavailable.');
    return ['id'=>(string)$row['approval_public_id'],'status'=>(string)$row['approval_status'],'action'=>$action,'plan'=>['id'=>$plan['id'],'status'=>$plan['status'],'trigger'=>$plan['trigger'],'strategy'=>$plan['strategy'],'agent'=>$plan['agent'],'summary'=>$plan['summary'],'requested_at'=>$plan['requested_at']],'permissions'=>['can_decide'=>(string)$row['approval_status']==='pending'&&!($action['approval']['expired']??false),'reason_required'=>in_array($action['risk'],['high','critical'],true)]];
}

function mg_agent_reconcile_run(PDO $pdo,int $runId,?int $actorUserId=null): string
{
    $stmt=$pdo->prepare('SELECT status,COUNT(*) total FROM agent_workflow_actions WHERE run_id=? GROUP BY status');$stmt->execute([$runId]);$counts=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$counts[(string)$row['status']]=(int)$row['total'];
    $open=($counts['proposed']??0)+($counts['approval_pending']??0)+($counts['approved']??0)+($counts['executing']??0);if($open>0)return 'executing';
    $completed=$counts['completed']??0;$failed=($counts['failed']??0)+($counts['rejected']??0)+($counts['canceled']??0);
    $status=$failed===0?'completed':($completed>0?'partially_completed':'failed');
    $pdo->prepare('UPDATE agent_workflow_runs SET status=?,completed_at=NOW(),result_json=?,failure_message=?,updated_at=NOW() WHERE id=?')->execute([$status,json_encode(['status'=>$status,'counts'=>$counts],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$failed>0?'One or more workflow actions did not complete.':null,$runId]);
    mg_agent_execution_event($pdo,$runId,null,'run_'.$status,$actorUserId,['counts'=>$counts]);return $status;
}

function mg_agent_decide_approval(PDO $pdo,int $ownerUserId,array $input): array
{
    $approvalPublicId=trim((string)($input['approval_id']??''));$decision=trim((string)($input['decision']??''));$reason=mb_substr(trim((string)($input['reason']??'')),0,1000);
    if($approvalPublicId===''||!in_array($decision,['approve','reject'],true))throw new MgAgentWorkflowException('Approval and valid decision are required.',422);
    $stmt=$pdo->prepare('SELECT ar.*,wa.status action_status,wa.risk_level FROM agent_approval_requests ar INNER JOIN agent_workflow_actions wa ON wa.id=ar.action_id WHERE ar.public_id=? AND ar.owner_user_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$approvalPublicId,$ownerUserId]);$approval=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$approval)throw new MgAgentWorkflowException('Approval request not found.',404);$targetStatus=$decision==='approve'?'approved':'rejected';
    if((string)$approval['status']!=='pending'){if((string)$approval['status']===$targetStatus)return ['approval_id'=>$approvalPublicId,'status'=>$targetStatus,'duplicate'=>true,'run_id'=>(int)$approval['run_id'],'action_id'=>(int)$approval['action_id']];throw new MgAgentWorkflowException('Approval decision conflicts with the recorded decision.',409);}
    if($approval['expires_at']!==null&&strtotime((string)$approval['expires_at'])<time())throw new MgAgentWorkflowException('Approval request has expired.',409);
    if(in_array((string)$approval['risk_level'],['high','critical'],true)&&$reason==='')throw new MgAgentWorkflowException('A decision reason is required for high-risk actions.',422);
    $pdo->prepare('UPDATE agent_approval_requests SET status=?,decision_reason=?,decided_at=NOW(),decided_by_user_id=?,updated_at=NOW() WHERE id=? AND status=\'pending\'')->execute([$targetStatus,$reason!==''?$reason:null,$ownerUserId,(int)$approval['id']]);
    $pdo->prepare('UPDATE agent_workflow_actions SET status=?,approved_by_user_id=IF(?=\'approved\',?,NULL),approved_at=IF(?=\'approved\',NOW(),NULL),completed_at=IF(?=\'rejected\',NOW(),completed_at),updated_at=NOW() WHERE id=? AND status=\'approval_pending\'')->execute([$targetStatus,$targetStatus,$ownerUserId,$targetStatus,$targetStatus,(int)$approval['action_id']]);
    mg_agent_execution_event($pdo,(int)$approval['run_id'],(int)$approval['action_id'],'approval_'.$targetStatus,$ownerUserId,['approval_id'=>$approvalPublicId,'reason'=>$reason]);$runStatus=mg_agent_reconcile_approval_run($pdo,(int)$approval['run_id'],$ownerUserId);
    return ['approval_id'=>$approvalPublicId,'status'=>$targetStatus,'duplicate'=>false,'run_id'=>(int)$approval['run_id'],'action_id'=>(int)$approval['action_id'],'run_status'=>$runStatus];
}

function mg_agent_process_next_action(PDO $pdo,?callable $failureHook=null): array
{
    $stmt=$pdo->query("SELECT wa.*,wr.owner_user_id,wr.status run_status FROM agent_workflow_actions wa INNER JOIN agent_workflow_runs wr ON wr.id=wa.run_id WHERE wa.status='approved' AND wr.status IN ('approved','executing','partially_completed') ORDER BY wa.id ASC LIMIT 1 FOR UPDATE SKIP LOCKED");$action=$stmt->fetch(PDO::FETCH_ASSOC);if(!$action)return ['processed'=>false];
    $pdo->exec('SAVEPOINT agent_action_execution');
    try{$pdo->prepare("UPDATE agent_workflow_actions SET status='executing',started_at=NOW(),updated_at=NOW() WHERE id=? AND status='approved'")->execute([(int)$action['id']]);$pdo->prepare("UPDATE agent_workflow_runs SET status='executing',started_at=COALESCE(started_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int)$action['run_id']]);mg_agent_execution_event($pdo,(int)$action['run_id'],(int)$action['id'],'action_execution_started',null,['action_type'=>$action['action_type']]);$result=mg_agent_execute_action($pdo,$action,(int)$action['owner_user_id']);if($failureHook)$failureHook('after_effect',['action'=>$action,'result'=>$result]);$pdo->prepare("UPDATE agent_workflow_actions SET status='completed',result_json=?,failure_message=NULL,completed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),(int)$action['id']]);mg_agent_execution_event($pdo,(int)$action['run_id'],(int)$action['id'],'action_execution_completed',null,$result);$runStatus=mg_agent_reconcile_run($pdo,(int)$action['run_id']);return ['processed'=>true,'status'=>'completed','run_status'=>$runStatus,'action_id'=>(string)$action['public_id'],'result'=>$result];}
    catch(Throwable $error){$pdo->exec('ROLLBACK TO SAVEPOINT agent_action_execution');$pdo->prepare("UPDATE agent_workflow_actions SET status='failed',failure_message=?,completed_at=NOW(),updated_at=NOW() WHERE id=? AND status='approved'")->execute([mb_substr($error->getMessage(),0,1000),(int)$action['id']]);mg_agent_execution_event($pdo,(int)$action['run_id'],(int)$action['id'],'action_execution_failed',null,['message'=>$error->getMessage()]);$runStatus=mg_agent_reconcile_run($pdo,(int)$action['run_id']);return ['processed'=>true,'status'=>'failed','run_status'=>$runStatus,'action_id'=>(string)$action['public_id'],'error'=>$error->getMessage()];}
}
