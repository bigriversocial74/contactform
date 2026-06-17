<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/subscriptions/_funding.php';
require_once dirname(__DIR__).'/api/tips/_recovery_webhook.php';
require_once dirname(__DIR__).'/api/social/_social.php';
require_once dirname(__DIR__).'/tests/integration/TipRecoveryBehaviorFixture.php';

function mg_sr_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_sr_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
}

function mg_sr_subscription(PDO $pdo,int $subscriptionId): array
{
    $stmt=$pdo->prepare('SELECT s.*,p.interval_unit,p.interval_count FROM subscriptions s INNER JOIN subscription_plans p ON p.id=s.plan_id WHERE s.id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$subscriptionId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Subscription recovery fixture not found.');
    return $row;
}

function mg_sr_attempt_by_payment(PDO $pdo,string $paymentId): array
{
    $stmt=$pdo->prepare("SELECT a.id attempt_id,a.status attempt_status,s.id subscription_id,s.public_id subscription_public_id,s.subscriber_user_id,s.recipient_user_id,s.target_type,s.target_reference,s.amount_cents,s.currency,s.funding_type,s.status,s.current_period_start,s.current_period_end,s.next_billing_at,s.cancel_at_period_end,s.retry_count,s.trial_ends_at,s.initial_payment_required,s.funded_at,s.activated_at,p.interval_unit,p.interval_count FROM subscription_attempts a INNER JOIN subscriptions s ON s.id=a.subscription_id INNER JOIN subscription_plans p ON p.id=s.plan_id WHERE a.provider_payment_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$paymentId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Subscription recovery attempt not found.');
    return $row;
}

function mg_sr_tip_by_payment(PDO $pdo,string $paymentId): array
{
    $stmt=$pdo->prepare('SELECT * FROM tips WHERE provider_payment_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$paymentId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Subscription recovery tip not found.');
    return $row;
}

function mg_sr_create_active(PDO $pdo,int $ownerId,int $subscriberId,string $runId,string $suffix): array
{
    $plan=mg_subscription_create_plan($pdo,$ownerId,[
        'target_type'=>'profile','target_reference'=>(string)$ownerId,'name'=>'Recovery plan '.$suffix,
        'amount_cents'=>1200,'currency'=>'USD','interval_unit'=>'month','interval_count'=>1,'trial_days'=>0,
        'funding_type'=>'stripe','metadata'=>['run_id'=>$runId,'suffix'=>$suffix],
    ]);
    $subscription=mg_subscription_subscribe($pdo,$subscriberId,[
        'plan_id'=>$plan['public_id'],'idempotency_key'=>'subscription-recovery:'.$runId.':'.$suffix,
        'provider_customer_id'=>'cus_'.$runId.'_'.$suffix,'provider_payment_method_ref'=>'pm_'.$runId.'_'.$suffix,
    ]);
    $paymentId=(string)$subscription['initial_attempt']['provider_payment_id'];
    mg_subscription_apply_payment_success($pdo,mg_sr_attempt_by_payment($pdo,$paymentId),$paymentId);
    $subscription=mg_sr_subscription($pdo,(int)$subscription['id']);
    $tip=mg_sr_tip_by_payment($pdo,$paymentId);
    $post=mg_social_create_post($pdo,$ownerId,[
        'headline'=>'Subscriber recovery post '.$suffix,
        'body'=>'Recovery-gated subscriber content.',
        'visibility'=>'subscribers',
        'subscription_plan_id'=>$plan['public_id'],
        'publish'=>true,
    ]);
    return ['plan'=>$plan,'subscription'=>$subscription,'tip'=>$tip,'post'=>$post,'payment_id'=>$paymentId];
}

function mg_sr_recovery_event(string $runId,string $suffix,string $type,array $tip,string $reference,int $amount,array $overrides=[]): array
{
    return mg_tip_recovery_it_event('subscription-recovery:'.$runId.':'.$suffix,$type,$tip,$reference,$amount,$overrides);
}

$pdo=mg_db();$runId='subscription_recovery_'.bin2hex(random_bytes(6));
$summary=[
    'suite'=>'subscription_refund_dispute_chargeback_reconciliation',
    'dispute_suspends_access'=>false,
    'dispute_replay_safe'=>false,
    'dispute_win_restores_access'=>false,
    'full_refund_revokes_access'=>false,
    'partial_refund_accumulates'=>false,
    'dispute_loss_revokes_access'=>false,
    'chargeback_revokes_renewal_access'=>false,
    'stage14_access_follows_recovery'=>false,
    'billing_paused_during_recovery'=>false,
    'notifications_and_events_once'=>false,
    'downstream_failure_rolls_back'=>false,
    'fixtures_clean'=>false,
];
$baseline=[
    'subscriptions'=>(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM subscriptions'),
    'attempts'=>(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM subscription_attempts'),
    'subscription_recoveries'=>(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM subscription_payment_recoveries'),
    'tip_recoveries'=>(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM tip_payment_recoveries'),
];

$pdo->beginTransaction();
try{
    $ownerEmail=$runId.'-owner@example.test';$subscriberEmail=$runId.'-subscriber@example.test';
    $ownerId=mg_it_user($pdo,$ownerEmail,'Recovery Subscription Owner');
    $subscriberId=mg_it_user($pdo,$subscriberEmail,'Recovery Subscription Subscriber');

    $won=mg_sr_create_active($pdo,$ownerId,$subscriberId,$runId,'won');
    $wonSubscriptionId=(int)$won['subscription']['id'];$wonReference='dp_won_'.$runId;
    mg_sr_assert(mg_social_can_view($pdo,$won['post'],$subscriberId),'Subscriber content was unavailable before dispute.');
    $originalBilling=(string)$won['subscription']['next_billing_at'];
    $opened=mg_tip_route_payment_event($pdo,(string)$won['tip']['provider_key'],mg_sr_recovery_event($runId,'open-won','charge.dispute.created',$won['tip'],$wonReference,1200));
    $openedSubscription=mg_sr_subscription($pdo,$wonSubscriptionId);
    mg_sr_assert((string)$openedSubscription['recovery_status']==='disputed','Dispute did not mark subscription recovery state.');
    mg_sr_assert($openedSubscription['next_billing_at']===null,'Dispute did not pause subscription billing.');
    mg_sr_assert(!mg_social_can_view($pdo,$won['post'],$subscriberId),'Dispute did not suspend subscriber content access.');
    mg_sr_assert(($opened['subscription_recovery']['access_action']??null)==='suspended','Dispute reconciliation did not report suspended access.');
    $summary['dispute_suspends_access']=true;$summary['billing_paused_during_recovery']=true;

    $recoveryCount=(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM subscription_payment_recoveries WHERE subscription_id=?',[$wonSubscriptionId]);
    $eventCount=(int)mg_sr_scalar($pdo,"SELECT COUNT(*) FROM subscription_events WHERE subscription_id=? AND event_type='subscription.recovery_disputed'",[$wonSubscriptionId]);
    $alertCount=(int)mg_sr_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE user_id=? AND alert_type='subscription_disputed'",[$subscriberId]);
    $replay=mg_tip_route_payment_event($pdo,(string)$won['tip']['provider_key'],mg_sr_recovery_event($runId,'open-won','charge.dispute.created',$won['tip'],$wonReference,1200));
    mg_sr_assert(!empty($replay['duplicate']),'Dispute replay was not recognized as duplicate.');
    mg_sr_assert($recoveryCount===(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM subscription_payment_recoveries WHERE subscription_id=?',[$wonSubscriptionId]),'Dispute replay duplicated reconciliation records.');
    mg_sr_assert($eventCount===(int)mg_sr_scalar($pdo,"SELECT COUNT(*) FROM subscription_events WHERE subscription_id=? AND event_type='subscription.recovery_disputed'",[$wonSubscriptionId]),'Dispute replay duplicated subscription events.');
    mg_sr_assert($alertCount===(int)mg_sr_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE user_id=? AND alert_type='subscription_disputed'",[$subscriberId]),'Dispute replay duplicated subscriber alerts.');
    $summary['dispute_replay_safe']=true;

    $wonResult=mg_tip_route_payment_event($pdo,(string)$won['tip']['provider_key'],mg_sr_recovery_event($runId,'close-won','charge.dispute.closed',$won['tip'],$wonReference,1200,['status'=>'won']));
    $restored=mg_sr_subscription($pdo,$wonSubscriptionId);
    mg_sr_assert((string)$restored['recovery_status']==='clear','Won dispute did not clear subscription recovery state.');
    mg_sr_assert((string)$restored['next_billing_at']===$originalBilling,'Won dispute did not restore the original billing schedule.');
    mg_sr_assert(mg_social_can_view($pdo,$won['post'],$subscriberId),'Won dispute did not restore eligible subscriber access.');
    mg_sr_assert(($wonResult['subscription_recovery']['access_action']??null)==='restored','Won dispute did not report restored access.');
    $summary['dispute_win_restores_access']=true;

    $refund=mg_sr_create_active($pdo,$ownerId,$subscriberId,$runId,'refund');
    $refundId=(int)$refund['subscription']['id'];
    $refundResult=mg_tip_route_payment_event($pdo,(string)$refund['tip']['provider_key'],mg_sr_recovery_event($runId,'refund','refund.succeeded',$refund['tip'],'re_'.$runId,1200));
    $refunded=mg_sr_subscription($pdo,$refundId);
    mg_sr_assert((string)$refunded['status']==='paused'&&(string)$refunded['recovery_status']==='refunded','Full refund did not pause the subscription.');
    mg_sr_assert((int)$refunded['initial_payment_required']===1,'Initial payment refund did not restore the funding requirement.');
    mg_sr_assert($refunded['next_billing_at']===null&&!mg_social_can_view($pdo,$refund['post'],$subscriberId),'Full refund did not revoke subscriber access and billing.');
    mg_sr_assert(($refundResult['subscription_recovery']['access_action']??null)==='revoked','Full refund did not report revoked access.');
    $summary['full_refund_revokes_access']=true;

    $partial=mg_sr_create_active($pdo,$ownerId,$subscriberId,$runId,'partial');
    $partialId=(int)$partial['subscription']['id'];$partialTipId=(int)$partial['tip']['id'];
    mg_tip_route_payment_event($pdo,(string)$partial['tip']['provider_key'],mg_sr_recovery_event($runId,'partial-one','refund.succeeded',$partial['tip'],'re_partial_one_'.$runId,400));
    $partialState=mg_sr_subscription($pdo,$partialId);
    mg_sr_assert((string)$partialState['status']==='active'&&(string)$partialState['recovery_status']==='clear','Partial refund incorrectly revoked subscription access.');
    mg_sr_assert(mg_social_can_view($pdo,$partial['post'],$subscriberId),'Partial refund incorrectly removed subscriber content access.');
    mg_sr_assert((int)mg_sr_scalar($pdo,'SELECT recovered_amount_cents FROM subscription_attempts WHERE tip_id=?',[$partialTipId])===400,'Partial refund amount was not accumulated.');
    mg_tip_route_payment_event($pdo,(string)$partial['tip']['provider_key'],mg_sr_recovery_event($runId,'partial-two','refund.succeeded',$partial['tip'],'re_partial_two_'.$runId,800));
    $partialFull=mg_sr_subscription($pdo,$partialId);
    mg_sr_assert((string)$partialFull['status']==='paused'&&(string)$partialFull['recovery_status']==='refunded','Accumulated refunds did not revoke access at the funded amount.');
    mg_sr_assert((int)mg_sr_scalar($pdo,'SELECT recovered_amount_cents FROM subscription_attempts WHERE tip_id=?',[$partialTipId])===1200,'Accumulated refund amount is incorrect.');
    $summary['partial_refund_accumulates']=true;

    $lost=mg_sr_create_active($pdo,$ownerId,$subscriberId,$runId,'lost');
    $lostId=(int)$lost['subscription']['id'];$lostReference='dp_lost_'.$runId;
    mg_tip_route_payment_event($pdo,(string)$lost['tip']['provider_key'],mg_sr_recovery_event($runId,'open-lost','charge.dispute.created',$lost['tip'],$lostReference,1200));
    $lostResult=mg_tip_route_payment_event($pdo,(string)$lost['tip']['provider_key'],mg_sr_recovery_event($runId,'close-lost','charge.dispute.closed',$lost['tip'],$lostReference,1200,['status'=>'lost']));
    $lostState=mg_sr_subscription($pdo,$lostId);
    mg_sr_assert((string)$lostState['status']==='paused'&&(string)$lostState['recovery_status']==='chargeback','Lost dispute did not terminate paid subscription access.');
    mg_sr_assert(!mg_social_can_view($pdo,$lost['post'],$subscriberId)&&($lostResult['subscription_recovery']['access_action']??null)==='revoked','Lost dispute did not revoke subscriber content access.');
    $summary['dispute_loss_revokes_access']=true;

    $chargeback=mg_sr_create_active($pdo,$ownerId,$subscriberId,$runId,'chargeback');
    $chargebackId=(int)$chargeback['subscription']['id'];
    $pdo->prepare('UPDATE subscriptions SET current_period_end=DATE_SUB(NOW(),INTERVAL 1 SECOND),next_billing_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?')->execute([$chargebackId]);
    $renewal=mg_subscription_attempt($pdo,mg_sr_subscription($pdo,$chargebackId));
    $renewalPayment=(string)$renewal['provider_payment_id'];
    mg_subscription_apply_payment_success($pdo,mg_sr_attempt_by_payment($pdo,$renewalPayment),$renewalPayment);
    $renewalTip=mg_sr_tip_by_payment($pdo,$renewalPayment);
    mg_sr_assert(mg_social_can_view($pdo,$chargeback['post'],$subscriberId),'Renewed subscription did not grant subscriber access.');
    $chargeResult=mg_tip_route_payment_event($pdo,(string)$renewalTip['provider_key'],mg_sr_recovery_event($runId,'chargeback','tip.chargeback',$renewalTip,'cb_'.$runId,1200));
    $chargeState=mg_sr_subscription($pdo,$chargebackId);
    mg_sr_assert((string)$chargeState['status']==='paused'&&(string)$chargeState['recovery_status']==='chargeback','Renewal chargeback did not pause subscription.');
    mg_sr_assert((int)$chargeState['initial_payment_required']===0,'Renewal chargeback incorrectly restored initial-payment state.');
    mg_sr_assert(!mg_social_can_view($pdo,$chargeback['post'],$subscriberId)&&($chargeResult['subscription_recovery']['access_action']??null)==='revoked','Renewal chargeback did not revoke subscriber access.');
    $summary['chargeback_revokes_renewal_access']=true;$summary['stage14_access_follows_recovery']=true;

    $recoveryEvents=(int)mg_sr_scalar($pdo,"SELECT COUNT(*) FROM subscription_events WHERE event_type LIKE 'subscription.recovery_%' AND subscription_id IN (?,?,?,?,?)",[$wonSubscriptionId,$refundId,$partialId,$lostId,$chargebackId]);
    $recoveryRecords=(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM subscription_payment_recoveries WHERE subscription_id IN (?,?,?,?,?)',[$wonSubscriptionId,$refundId,$partialId,$lostId,$chargebackId]);
    mg_sr_assert($recoveryEvents===$recoveryRecords,'Subscription recovery events and durable records diverged.');
    mg_sr_assert((int)mg_sr_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE user_id=? AND alert_type IN ('subscription_disputed','subscription_access_restored','subscription_refunded','subscription_partial_refund','subscription_chargeback')",[$subscriberId])===$recoveryRecords,'Subscription recovery notifications were not emitted exactly once.');
    $summary['notifications_and_events_once']=true;

    $rollback=mg_sr_create_active($pdo,$ownerId,$subscriberId,$runId,'rollback');
    $rollbackId=(int)$rollback['subscription']['id'];
    $before=[
        'tip_status'=>(string)mg_sr_scalar($pdo,'SELECT status FROM tips WHERE id=?',[(int)$rollback['tip']['id']]),
        'subscription_status'=>(string)mg_sr_scalar($pdo,'SELECT status FROM subscriptions WHERE id=?',[$rollbackId]),
        'subscription_recovery'=>(string)mg_sr_scalar($pdo,'SELECT recovery_status FROM subscriptions WHERE id=?',[$rollbackId]),
        'tip_recoveries'=>(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM tip_payment_recoveries WHERE tip_id=?',[(int)$rollback['tip']['id']]),
        'subscription_recoveries'=>(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM subscription_payment_recoveries WHERE subscription_id=?',[$rollbackId]),
        'groups'=>(int)mg_sr_scalar($pdo,"SELECT COUNT(*) FROM ledger_transaction_groups WHERE source_type='tip_recovery'"),
    ];
    $pdo->exec('SAVEPOINT subscription_recovery_failure');$failed=false;
    try{
        mg_tip_route_payment_event(
            $pdo,
            (string)$rollback['tip']['provider_key'],
            mg_sr_recovery_event($runId,'rollback','refund.succeeded',$rollback['tip'],'re_rollback_'.$runId,1200),
            static function(string $stage): void {if($stage==='after_subscription_state')throw new RuntimeException('Forced subscription recovery failure.');}
        );
    }catch(RuntimeException $e){$failed=$e->getMessage()==='Forced subscription recovery failure.';}
    mg_sr_assert($failed,'Forced downstream reconciliation failure did not occur.');
    $pdo->exec('ROLLBACK TO SAVEPOINT subscription_recovery_failure');$pdo->exec('RELEASE SAVEPOINT subscription_recovery_failure');
    $after=[
        'tip_status'=>(string)mg_sr_scalar($pdo,'SELECT status FROM tips WHERE id=?',[(int)$rollback['tip']['id']]),
        'subscription_status'=>(string)mg_sr_scalar($pdo,'SELECT status FROM subscriptions WHERE id=?',[$rollbackId]),
        'subscription_recovery'=>(string)mg_sr_scalar($pdo,'SELECT recovery_status FROM subscriptions WHERE id=?',[$rollbackId]),
        'tip_recoveries'=>(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM tip_payment_recoveries WHERE tip_id=?',[(int)$rollback['tip']['id']]),
        'subscription_recoveries'=>(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM subscription_payment_recoveries WHERE subscription_id=?',[$rollbackId]),
        'groups'=>(int)mg_sr_scalar($pdo,"SELECT COUNT(*) FROM ledger_transaction_groups WHERE source_type='tip_recovery'"),
    ];
    mg_sr_assert($before===$after&&mg_social_can_view($pdo,$rollback['post'],$subscriberId),'Downstream reconciliation failure did not roll back tip, ledger, subscription, and access state.');
    $summary['downstream_failure_rolls_back']=true;

    $pdo->rollBack();
    $afterRollback=[
        'subscriptions'=>(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM subscriptions'),
        'attempts'=>(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM subscription_attempts'),
        'subscription_recoveries'=>(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM subscription_payment_recoveries'),
        'tip_recoveries'=>(int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM tip_payment_recoveries'),
    ];
    mg_sr_assert($baseline===$afterRollback,'Subscription recovery fixtures remain after rollback.');
    mg_sr_assert((int)mg_sr_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$ownerEmail,$subscriberEmail])===0,'Subscription recovery users remain after rollback.');
    $summary['fixtures_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$e->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $e;
}
