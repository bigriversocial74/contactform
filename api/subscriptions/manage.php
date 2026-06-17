<?php
declare(strict_types=1);
require_once __DIR__ . '/_subscriptions.php';
mg_require_method('POST');
$user=mg_require_permission('subscriptions.manage_own');
$input=mg_input();mg_require_csrf_for_write($input);
$publicId=trim((string)($input['subscription_id']??''));
$action=trim((string)($input['action']??''));
if($publicId===''||!in_array($action,['pause','resume','cancel','cancel_at_period_end'],true))mg_fail('Subscription and valid action are required.',422);
$pdo=mg_db();$pdo->beginTransaction();
try{
    $stmt=$pdo->prepare('SELECT s.*,p.interval_unit,p.interval_count FROM subscriptions s INNER JOIN subscription_plans p ON p.id=s.plan_id WHERE s.public_id=? AND s.subscriber_user_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$publicId,(int)$user['id']]);$subscription=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$subscription)throw new RuntimeException('Subscription not found.');
    $recoveryStatus=(string)($subscription['recovery_status']??'clear');
    if($recoveryStatus!=='clear'&&$action!=='cancel')throw new RuntimeException('Subscription payment recovery must be resolved before this action.');
    $from=(string)$subscription['status'];$to=$from;$reason=$action;
    if($action==='pause'){
        if(!in_array($from,['active','trialing','past_due'],true))throw new RuntimeException('Subscription cannot be paused.');
        $to='paused';$pdo->prepare("UPDATE subscriptions SET status='paused',paused_at=NOW(),next_billing_at=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$subscription['id']]);
    }elseif($action==='resume'){
        if($from!=='paused')throw new RuntimeException('Subscription is not paused.');
        $to=mg_subscription_initial_payment_required($subscription)?'pending_payment':'active';
        $next=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $pdo->prepare('UPDATE subscriptions SET status=?,resumed_at=NOW(),next_billing_at=?,retry_count=0,last_failure_message=NULL,updated_at=NOW() WHERE id=?')->execute([$to,$next,(int)$subscription['id']]);
    }elseif($action==='cancel_at_period_end'){
        if(in_array($from,['pending_payment','canceled','expired'],true))throw new RuntimeException('Subscription cannot schedule period-end cancellation.');
        $to='cancel_pending';$pdo->prepare("UPDATE subscriptions SET status='cancel_pending',cancel_at_period_end=1,updated_at=NOW() WHERE id=?")->execute([(int)$subscription['id']]);
    }else{
        if(in_array($from,['canceled','expired'],true))throw new RuntimeException('Subscription is already closed.');
        $to='canceled';$pdo->prepare("UPDATE subscriptions SET status='canceled',cancel_at_period_end=0,canceled_at=NOW(),next_billing_at=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$subscription['id']]);
    }
    mg_subscription_event($pdo,(int)$subscription['id'],'subscription.'.$action,$from,$to,(int)$user['id'],$reason,['recovery_status'=>$recoveryStatus]);
    $pdo->commit();
    mg_audit('subscription.'.$action,'subscription',['subscription_id'=>$publicId,'from_status'=>$from,'to_status'=>$to,'recovery_status'=>$recoveryStatus],(int)$user['id']);
    mg_ok(['subscription_id'=>$publicId,'status'=>$to,'recovery_status'=>$recoveryStatus],'Subscription updated.');
}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to update subscription.',500);}
