<?php
declare(strict_types=1);

require_once __DIR__ . '/_workflow.php';

mg_require_method('GET');
$user=mg_require_permission('agent.approvals.decide');
$userId=(int)$user['id'];
$pdo=mg_db();
mg_rate_limit('agent.plans.read','user:'.$userId,180,60);
$runId=trim((string)($_GET['run_id']??''));

try{
    $pdo->beginTransaction();
    $expired=mg_agent_expire_approvals($pdo,$userId);
    $pdo->commit();
    $plan=mg_agent_plan_projection($pdo,$runId,$userId);
    header('Cache-Control: private, no-store, max-age=0');
    mg_event('agent.plan_read',['run_id'=>$plan['id'],'status'=>$plan['status'],'action_count'=>$plan['summary']['total'],'expired_reconciled'=>$expired],$userId);
    mg_ok(['plan'=>$plan,'policy'=>['individual_decisions_only'=>true,'bulk_approval_enabled'=>false,'financial_actions_enabled'=>false]]);
}catch(MgAgentWorkflowException $error){if($pdo->inTransaction())$pdo->rollBack();mg_fail($error->getMessage(),$error->httpStatus);}
catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','agent.plan_read_failed','Agent plan review read failed.',['exception_class'=>$error::class],$userId);mg_fail('Unable to load workflow plan.',500);}
