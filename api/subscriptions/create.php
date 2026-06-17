<?php
declare(strict_types=1);
require_once __DIR__ . '/_subscriptions.php';
mg_require_method('POST');
$user=mg_require_permission('subscriptions.create');
$input=mg_input();mg_require_csrf_for_write($input);
$pdo=mg_db();$pdo->beginTransaction();
try{
    $subscription=mg_subscription_subscribe($pdo,(int)$user['id'],$input);
    $pdo->commit();
    mg_audit('subscription.created','subscription',['subscription_id'=>$subscription['public_id'],'plan_id'=>(int)$subscription['plan_id'],'status'=>$subscription['status'],'duplicate'=>(bool)$subscription['duplicate']],(int)$user['id']);
    mg_ok(['subscription'=>$subscription],$subscription['duplicate']?'Existing subscription returned.':'Subscription created.',$subscription['duplicate']?200:201);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','subscription.create_failed','Subscription creation failed.',['exception'=>$e->getMessage()],(int)$user['id']);mg_fail('Unable to create subscription.',500);}
