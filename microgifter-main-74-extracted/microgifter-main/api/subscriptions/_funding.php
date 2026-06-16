<?php
declare(strict_types=1);

require_once __DIR__ . '/_subscriptions.php';
require_once __DIR__ . '/_notifications.php';

function mg_subscription_apply_payment_success(PDO $pdo,array $row,string $paymentId,?callable $failureHook=null): array
{
    if((string)$row['attempt_status']==='succeeded'){
        return ['status'=>'succeeded','subscription_id'=>$row['subscription_public_id'],'duplicate'=>true];
    }
    if((string)$row['attempt_status']==='failed'){
        throw new RuntimeException('Failed subscription attempt cannot be settled as succeeded.');
    }
    $initial=mg_subscription_initial_payment_required($row);
    $tip=mg_tip_finalize_stripe($pdo,$paymentId,'succeeded');
    if(empty($tip['duplicate']))mg_tip_notify_recipient($pdo,$tip);
    $pdo->prepare("UPDATE subscription_attempts SET status='succeeded',completed_at=NOW(),updated_at=NOW() WHERE id=? AND status<>'succeeded'")
        ->execute([(int)$row['attempt_id']]);
    if($failureHook)$failureHook('after_ledger',['subscription'=>$row,'tip'=>$tip]);
    if($initial){
        mg_subscription_activate_initial($pdo,$row,(int)$row['subscriber_user_id']);
        mg_subscription_notify($pdo,$row,'subscription_activated','Subscription activated','Your first subscription payment was completed and access is now active.',['tip_id'=>$tip['public_id']]);
    }else{
        mg_subscription_advance($pdo,$row,(int)$row['subscriber_user_id']);
        mg_subscription_notify($pdo,$row,'subscription_renewed','Subscription renewed','Your recurring support payment was completed.',['tip_id'=>$tip['public_id']]);
    }
    if($failureHook)$failureHook('before_complete',['subscription'=>$row,'tip'=>$tip]);
    return ['status'=>'succeeded','subscription_id'=>$row['subscription_public_id'],'tip_id'=>$tip['public_id'],'phase'=>$initial?'initial':'renewal','duplicate'=>false];
}

function mg_subscription_apply_payment_failure(PDO $pdo,array $row,string $paymentId,string $message): array
{
    if((string)$row['attempt_status']==='succeeded')return ['ignored'=>true,'reason'=>'attempt_already_succeeded'];
    if((string)$row['attempt_status']==='failed')return ['status'=>(string)$row['status'],'subscription_id'=>$row['subscription_public_id'],'duplicate'=>true,'retry_count'=>(int)$row['retry_count'],'next_retry_at'=>$row['next_billing_at']??null];
    mg_tip_finalize_stripe($pdo,$paymentId,'failed',$message);
    $pdo->prepare("UPDATE subscription_attempts SET status='failed',failure_message=?,completed_at=NOW(),updated_at=NOW() WHERE id=? AND status<>'failed'")
        ->execute([mb_substr($message,0,500),(int)$row['attempt_id']]);
    $failure=mg_subscription_mark_failure($pdo,$row,$message,(int)$row['subscriber_user_id']);
    mg_subscription_notify($pdo,$row,'subscription_payment_failed','Subscription payment failed',$message,['retry_count'=>$failure['retry_count'],'next_retry_at'=>$failure['next_retry_at'],'initial_payment'=>$failure['initial_payment']]);
    return $failure+['subscription_id'=>$row['subscription_public_id'],'duplicate'=>false];
}
