<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/agents/_workflow.php';
require_once dirname(__DIR__).'/tests/integration/MicrogiftBehaviorFixture.php';

function apc_assert(bool $condition,string $name):void{if(!$condition)throw new RuntimeException('Agent approval validation failed: '.$name);}
function apc_throws(callable $callback,string $message):bool{try{$callback();}catch(Throwable $error){return str_contains($error->getMessage(),$message);}return false;}
function apc_agent(PDO $pdo,int $userId,string $name):array{$now=gmdate('Y-m-d H:i:s');$public=mg_public_uuid();$id=mg_it_insert($pdo,'agents',['public_id'=>$public,'user_id'=>$userId,'name'=>$name,'category'=>'community','config_json'=>'{}','runtime_status'=>'paused','lifecycle_status'=>'active','version_no'=>1,'started_at'=>null,'paused_at'=>$now,'archived_at'=>null,'restored_at'=>null,'deleted_at'=>null,'created_at'=>$now,'updated_at'=>$now]);return['id'=>$id,'public_id'=>$public];}
function apc_strategy_input(string $agentId):array{return['agent_id'=>$agentId,'name'=>'Approval Review','objective'=>'Review operational recommendations before execution.','trigger_type'=>'demand_signal','trigger_config'=>['minimum_level'=>'warning'],'policy'=>['review_required'=>true],'action_catalog'=>['create_operational_alert','acknowledge_demand_signal','pause_distribution_program'],'max_actions_per_run'=>5,'requires_approval'=>true];}
function apc_approval_row(PDO $pdo,int $runId,int $sequence):array{$stmt=$pdo->prepare('SELECT ar.public_id approval_public_id,ar.status approval_status,ar.owner_user_id,ar.requested_at,wr.public_id run_public_id FROM agent_approval_requests ar INNER JOIN agent_workflow_actions wa ON wa.id=ar.action_id INNER JOIN agent_workflow_runs wr ON wr.id=ar.run_id WHERE ar.run_id=? AND wa.sequence_no=? LIMIT 1');$stmt->execute([$runId,$sequence]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Approval row missing.');return$row;}

$pdo=mg_db();$runKey='apc'.bin2hex(random_bytes(5));
$result=array_fill_keys(['plan_created','strategy_version_snapshot','safe_plan_projection','safe_approval_projection','high_risk_reason_required','approve','duplicate_approve','conflicting_decision','owner_isolation','reject_with_reason','expiration','partial_reconciliation','events_recorded','rollback_clean'],false);
$pdo->beginTransaction();
try{
    $owner=mg_it_user($pdo,$runKey.'-owner@example.test','Approval Owner');$other=mg_it_user($pdo,$runKey.'-other@example.test','Other Owner');$agent=apc_agent($pdo,$owner,'Approval Agent');
    $strategy=mg_agent_create_strategy($pdo,$owner,apc_strategy_input($agent['public_id']));$strategy=mg_agent_transition_strategy($pdo,$owner,['strategy_id'=>$strategy['public_id'],'version'=>1],'active');
    $run=mg_agent_create_run($pdo,$owner,['strategy_id'=>$strategy['public_id'],'idempotency_key'=>'approval-run-'.$runKey,'trigger_type'=>'demand_signal','trigger_reference'=>mg_public_uuid(),'input'=>['reason'=>'Demand signal exceeded threshold.','source'=>'stage15','secret'=>'must-not-project']]);
    $planResult=mg_agent_plan_run($pdo,$run,[
        ['action_type'=>'create_operational_alert','target_type'=>'user','target_reference'=>(string)$owner,'risk_level'=>'low','reason'=>'Notify the owner about demand pressure.','request'=>['title'=>'Demand pressure','message'=>'Review the upcoming demand window.','action_url'=>'/intelligence.php','expected_effect'=>'Create a reviewable operational notification.','provider_secret'=>'hidden']],
        ['action_type'=>'acknowledge_demand_signal','target_type'=>'demand_signal','target_reference'=>mg_public_uuid(),'risk_level'=>'high','reason'=>'Mark the signal as reviewed after owner confirmation.','request'=>['summary'=>'Acknowledge the demand signal.','expected_effect'=>'Remove the signal from the unreviewed queue.','internal_id'=>123]],
        ['action_type'=>'pause_distribution_program','target_type'=>'distribution_program','target_reference'=>mg_public_uuid(),'risk_level'=>'medium','reason'=>'Pause distribution while demand is reviewed.','request'=>['summary'=>'Pause the program.','expected_effect'=>'Stop new distribution activity.','payment_data'=>'hidden']],
    ],$owner);
    $result['plan_created']=$planResult['status']==='approval_pending'&&count($planResult['actions'])===3&&$planResult['strategy_version']===2;

    $paused=mg_agent_transition_strategy($pdo,$owner,['strategy_id'=>$strategy['public_id'],'version'=>2],'paused');
    mg_agent_update_strategy($pdo,$owner,apc_strategy_input($agent['public_id'])+['strategy_id'=>$strategy['public_id'],'version'=>(int)$paused['version_no'],'name'=>'Approval Review Updated']);
    $plan=mg_agent_plan_projection($pdo,(string)$run['public_id'],$owner);
    $result['strategy_version_snapshot']=$plan['strategy']['version']===2;
    $keys=[];$walk=function($value)use(&$walk,&$keys){if(!is_array($value))return;foreach($value as $key=>$child){$keys[]=strtolower((string)$key);$walk($child);}};$walk($plan);
    $result['safe_plan_projection']=array_intersect(['owner_user_id','strategy_id','agent_id','input_json','plan_json','request_json','idempotency_key','provider_secret','internal_id','payment_data','secret'],$keys)===[]&&$plan['input']['reason']==='Demand signal exceeded threshold.';

    $runId=(int)$run['id'];$lowRow=apc_approval_row($pdo,$runId,1);$highRow=apc_approval_row($pdo,$runId,2);$expiryRow=apc_approval_row($pdo,$runId,3);
    $approval=mg_agent_approval_projection($pdo,$highRow);$approvalKeys=[];$walkApproval=function($value)use(&$walkApproval,&$approvalKeys){if(!is_array($value))return;foreach($value as $key=>$child){$approvalKeys[]=strtolower((string)$key);$walkApproval($child);}};$walkApproval($approval);
    $result['safe_approval_projection']=array_intersect(['owner_user_id','action_id','run_id','request_json','input_json','idempotency_key'],$approvalKeys)===[]&&$approval['permissions']['reason_required']===true;
    $result['high_risk_reason_required']=apc_throws(fn()=>mg_agent_decide_approval($pdo,$owner,['approval_id'=>$highRow['approval_public_id'],'decision'=>'approve','reason'=>'']),'reason is required');

    $approved=mg_agent_decide_approval($pdo,$owner,['approval_id'=>$lowRow['approval_public_id'],'decision'=>'approve','reason'=>'']);
    $result['approve']=$approved['status']==='approved'&&$approved['duplicate']===false&&$approved['run_status']==='approval_pending';
    $duplicate=mg_agent_decide_approval($pdo,$owner,['approval_id'=>$lowRow['approval_public_id'],'decision'=>'approve','reason'=>'']);
    $result['duplicate_approve']=$duplicate['duplicate']===true;
    $result['conflicting_decision']=apc_throws(fn()=>mg_agent_decide_approval($pdo,$owner,['approval_id'=>$lowRow['approval_public_id'],'decision'=>'reject','reason'=>'Changed mind']),'conflicts');
    $result['owner_isolation']=apc_throws(fn()=>mg_agent_decide_approval($pdo,$other,['approval_id'=>$highRow['approval_public_id'],'decision'=>'reject','reason'=>'Not mine']),'not found');

    $rejected=mg_agent_decide_approval($pdo,$owner,['approval_id'=>$highRow['approval_public_id'],'decision'=>'reject','reason'=>'The target signal needs additional verification.']);
    $result['reject_with_reason']=$rejected['status']==='rejected'&&$rejected['run_status']==='approval_pending';
    $pdo->prepare("UPDATE agent_approval_requests SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE public_id=?")->execute([$expiryRow['approval_public_id']]);
    $expired=mg_agent_expire_approvals($pdo,$owner);
    $result['expiration']=$expired===1&&(string)mg_it_scalar($pdo,'SELECT status FROM agent_approval_requests WHERE public_id=?',[$expiryRow['approval_public_id']])==='expired'&&(string)mg_it_scalar($pdo,'SELECT wa.status FROM agent_workflow_actions wa INNER JOIN agent_approval_requests ar ON ar.action_id=wa.id WHERE ar.public_id=?',[$expiryRow['approval_public_id']])==='canceled';
    $result['partial_reconciliation']=(string)mg_it_scalar($pdo,'SELECT status FROM agent_workflow_runs WHERE id=?',[$runId])==='partially_completed';
    $result['events_recorded']=(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM agent_execution_events WHERE run_id=? AND event_type IN ('approval_approved','approval_rejected','approval_expired')",[$runId])===3;

    foreach($result as $name=>$passed)if($name!=='rollback_clean')apc_assert($passed,$name);
    $pdo->rollBack();$result['rollback_clean']=true;
    echo json_encode($result+['suite'=>'agent_plan_review_approval_center_section_2'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1);}
