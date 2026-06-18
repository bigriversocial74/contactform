<?php
declare(strict_types=1);
require_once __DIR__ . '/_subscriptions.php';
$user=mg_require_permission('subscription_plans.manage');
$pdo=mg_db();
if($_SERVER['REQUEST_METHOD']==='GET'){
    $stmt=$pdo->prepare('SELECT public_id,target_type,target_reference,name,description,amount_cents,currency,interval_unit,interval_count,trial_days,funding_type,status,created_at,updated_at FROM subscription_plans WHERE owner_user_id=? ORDER BY id DESC');
    $stmt->execute([(int)$user['id']]);
    mg_ok(['plans'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
mg_require_method('POST');
$input=mg_input();mg_require_csrf_for_write($input);
$pdo->beginTransaction();
try{$plan=mg_subscription_create_plan($pdo,(int)$user['id'],$input);$pdo->commit();mg_audit('subscription_plan.created','subscription_plan',['plan_id'=>$plan['public_id'],'target_type'=>$plan['target_type'],'target_reference'=>$plan['target_reference']],(int)$user['id']);mg_ok(['plan'=>$plan],'Subscription plan created.',201);}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to create subscription plan.',500);}
