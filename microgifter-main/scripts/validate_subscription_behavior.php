<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/subscriptions/_funding.php';
require_once dirname(__DIR__).'/tests/integration/MicrogiftBehaviorFixture.php';

function mg_sub_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_sub_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
}

function mg_sub_row(PDO $pdo,int $subscriptionId): array
{
    $stmt=$pdo->prepare('SELECT s.*,p.interval_unit,p.interval_count FROM subscriptions s INNER JOIN subscription_plans p ON p.id=s.plan_id WHERE s.id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$subscriptionId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Subscription fixture not found.');
    return $row;
}

function mg_sub_attempt_row(PDO $pdo,string $paymentId): array
{
    $stmt=$pdo->prepare("SELECT a.id attempt_id,a.status attempt_status,s.id subscription_id,s.public_id subscription_public_id,s.subscriber_user_id,s.recipient_user_id,s.target_type,s.target_reference,s.amount_cents,s.currency,s.funding_type,s.status,s.current_period_start,s.current_period_end,s.next_billing_at,s.cancel_at_period_end,s.retry_count,s.trial_ends_at,s.initial_payment_required,s.funded_at,s.activated_at,p.interval_unit,p.interval_count FROM subscription_attempts a INNER JOIN subscriptions s ON s.id=a.subscription_id INNER JOIN subscription_plans p ON p.id=s.plan_id WHERE a.provider_payment_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$paymentId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Subscription attempt fixture not found.');
    return $row;
}

function mg_sub_create(PDO $pdo,int $ownerId,int $subscriberId,string $runId,string $suffix): array
{
    $plan=mg_subscription_create_plan($pdo,$ownerId,[
        'target_type'=>'profile','target_reference'=>(string)$ownerId,'name'=>'Behavior subscription '.$suffix,
        'amount_cents'=>1200,'currency'=>'USD','interval_unit'=>'month','interval_count'=>1,'trial_days'=>0,
        'funding_type'=>'stripe','metadata'=>['run_id'=>$runId,'suffix'=>$suffix],
    ]);
    $subscription=mg_subscription_subscribe($pdo,$subscriberId,[
        'plan_id'=>$plan['public_id'],'idempotency_key'=>'behavior:subscription:'.$runId.':'.$suffix,
        'provider_customer_id'=>'cus_'.$runId.'_'.$suffix,'provider_payment_method_ref'=>'pm_'.$runId.'_'.$suffix,
    ]);
    return ['plan'=>$plan,'subscription'=>$subscription,'attempt'=>$subscription['initial_attempt']];
}

$pdo=mg_db();$runId='subscription_'.bin2hex(random_bytes(8));
$summary=[
    'suite'=>'subscription_funding_renewal_dunning_behavior','run_id'=>$runId,
    'created_pending'=>false,'initial_funding_activated'=>false,'activation_replay'=>false,'conflicting_settlement_rejected'=>false,
    'initial_ledger_balanced'=>false,'renewal_advanced'=>false,'renewal_replay'=>false,'recovery_after_failure'=>false,
    'dunning_scheduled'=>false,'dunning_exhausted'=>false,'notifications_once'=>false,'forced_failure_rolled_back'=>false,
    'financial_rows_consistent'=>false,'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $ownerEmail=$runId.'-owner@example.test';$subscriberEmail=$runId.'-subscriber@example.test';
    $ownerId=mg_it_user($pdo,$ownerEmail,'Subscription Owner');$subscriberId=mg_it_user($pdo,$subscriberEmail,'Subscription Subscriber');

    $main=mg_sub_create($pdo,$ownerId,$subscriberId,$runId,'main');
    $subscriptionId=(int)$main['subscription']['id'];$initialPayment=(string)$main['attempt']['provider_payment_id'];
    mg_sub_assert((string)$main['subscription']['status']==='pending_payment','Subscription did not start pending payment.');
    mg_sub_assert((int)$main['subscription']['initial_payment_required']===1,'Initial funding flag is missing.');
    mg_sub_assert((string)$main['attempt']['status']==='processing','Initial Stripe attempt was not processing.');
    $summary['created_pending']=true;

    $activation=mg_subscription_apply_payment_success($pdo,mg_sub_attempt_row($pdo,$initialPayment),$initialPayment);
    $active=mg_sub_row($pdo,$subscriptionId);
    mg_sub_assert($activation['phase']==='initial'&&(string)$active['status']==='active','Initial settlement did not activate subscription.');
    mg_sub_assert((int)$active['initial_payment_required']===0&&!empty($active['funded_at'])&&!empty($active['activated_at']),'Activation funding timestamps are incomplete.');
    mg_sub_assert((int)mg_sub_scalar($pdo,"SELECT COUNT(*) FROM subscription_events WHERE subscription_id=? AND event_type='subscription.activated'",[$subscriptionId])===1,'Activation event count is incorrect.');
    $summary['initial_funding_activated']=true;

    $postedTipId=(int)mg_sub_scalar($pdo,'SELECT tip_id FROM subscription_attempts WHERE provider_payment_id=?',[$initialPayment]);
    $groupId=(int)mg_sub_scalar($pdo,'SELECT ledger_group_id FROM tips WHERE id=?',[$postedTipId]);
    $sides=[];$entries=$pdo->prepare('SELECT entry_type,SUM(amount_cents) total FROM ledger_entries WHERE transaction_group_id=? GROUP BY entry_type');
    $entries->execute([$groupId]);foreach($entries->fetchAll(PDO::FETCH_ASSOC) as $entry)$sides[(string)$entry['entry_type']]=(int)$entry['total'];
    mg_sub_assert($groupId>0&&($sides['debit']??0)===1200&&($sides['credit']??0)===1200,'Initial subscription ledger group is not balanced.');
    $summary['initial_ledger_balanced']=true;

    $activationReplay=mg_subscription_apply_payment_success($pdo,mg_sub_attempt_row($pdo,$initialPayment),$initialPayment);
    mg_sub_assert($activationReplay['duplicate']===true,'Activation replay was not idempotent.');
    mg_sub_assert((int)mg_sub_scalar($pdo,"SELECT COUNT(*) FROM subscription_events WHERE subscription_id=? AND event_type='subscription.activated'",[$subscriptionId])===1,'Activation replay duplicated events.');
    $summary['activation_replay']=true;

    $ignored=mg_subscription_apply_payment_failure($pdo,mg_sub_attempt_row($pdo,$initialPayment),$initialPayment,'Conflicting late failure.');
    mg_sub_assert(($ignored['ignored']??false)===true,'Conflicting failure replay was not rejected safely.');
    $summary['conflicting_settlement_rejected']=true;

    $pdo->prepare('UPDATE subscriptions SET current_period_end=DATE_SUB(NOW(),INTERVAL 1 SECOND),next_billing_at=DATE_SUB(NOW(),INTERVAL 1 SECOND),updated_at=NOW() WHERE id=?')->execute([$subscriptionId]);
    $renewal=mg_subscription_attempt($pdo,mg_sub_row($pdo,$subscriptionId));
    mg_sub_assert((string)$renewal['status']==='processing'&&$renewal['phase']==='renewal','Renewal attempt was not created.');
    $renewalPayment=(string)$renewal['provider_payment_id'];
    $renewed=mg_subscription_apply_payment_success($pdo,mg_sub_attempt_row($pdo,$renewalPayment),$renewalPayment);
    $afterRenewal=mg_sub_row($pdo,$subscriptionId);
    mg_sub_assert($renewed['phase']==='renewal'&&(string)$afterRenewal['status']==='active','Renewal did not keep subscription active.');
    mg_sub_assert(strtotime((string)$afterRenewal['current_period_end'])>strtotime((string)$afterRenewal['current_period_start']),'Renewal period was not advanced.');
    mg_sub_assert((int)mg_sub_scalar($pdo,"SELECT COUNT(*) FROM subscription_events WHERE subscription_id=? AND event_type='subscription.renewed'",[$subscriptionId])===1,'Renewal event count is incorrect.');
    $summary['renewal_advanced']=true;

    $renewalReplay=mg_subscription_apply_payment_success($pdo,mg_sub_attempt_row($pdo,$renewalPayment),$renewalPayment);
    mg_sub_assert($renewalReplay['duplicate']===true,'Renewal replay was not idempotent.');
    mg_sub_assert((int)mg_sub_scalar($pdo,"SELECT COUNT(*) FROM subscription_events WHERE subscription_id=? AND event_type='subscription.renewed'",[$subscriptionId])===1,'Renewal replay duplicated events.');
    $summary['renewal_replay']=true;

    $recovery=mg_sub_create($pdo,$ownerId,$subscriberId,$runId,'recovery');
    $recoveryId=(int)$recovery['subscription']['id'];$recoveryInitial=(string)$recovery['attempt']['provider_payment_id'];
    mg_subscription_apply_payment_success($pdo,mg_sub_attempt_row($pdo,$recoveryInitial),$recoveryInitial);
    $pdo->prepare('UPDATE subscriptions SET current_period_end=DATE_SUB(NOW(),INTERVAL 1 SECOND),next_billing_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?')->execute([$recoveryId]);
    $failedAttempt=mg_subscription_attempt($pdo,mg_sub_row($pdo,$recoveryId));$failedPayment=(string)$failedAttempt['provider_payment_id'];
    $failed=mg_subscription_apply_payment_failure($pdo,mg_sub_attempt_row($pdo,$failedPayment),$failedPayment,'Behavior decline.');
    mg_sub_assert($failed['status']==='past_due'&&$failed['retry_count']===1&&!empty($failed['next_retry_at']),'First dunning failure did not schedule retry.');
    $failureReplay=mg_subscription_apply_payment_failure($pdo,mg_sub_attempt_row($pdo,$failedPayment),$failedPayment,'Behavior decline.');
    mg_sub_assert(($failureReplay['duplicate']??false)===true&&(int)$failureReplay['retry_count']===1,'Failure replay advanced dunning twice.');
    $summary['dunning_scheduled']=true;
    $pdo->prepare('UPDATE subscriptions SET next_billing_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?')->execute([$recoveryId]);
    $recoveryAttempt=mg_subscription_attempt($pdo,mg_sub_row($pdo,$recoveryId));$recoveryPayment=(string)$recoveryAttempt['provider_payment_id'];
    mg_subscription_apply_payment_success($pdo,mg_sub_attempt_row($pdo,$recoveryPayment),$recoveryPayment);
    $recovered=mg_sub_row($pdo,$recoveryId);
    mg_sub_assert((string)$recovered['status']==='active'&&(int)$recovered['retry_count']===0&&$recovered['last_failure_message']===null,'Successful recovery did not restore subscription.');
    $summary['recovery_after_failure']=true;

    $terminal=mg_sub_create($pdo,$ownerId,$subscriberId,$runId,'terminal');
    $terminalId=(int)$terminal['subscription']['id'];$terminalInitial=(string)$terminal['attempt']['provider_payment_id'];
    mg_subscription_apply_payment_success($pdo,mg_sub_attempt_row($pdo,$terminalInitial),$terminalInitial);
    $pdo->prepare('UPDATE subscriptions SET current_period_end=DATE_SUB(NOW(),INTERVAL 1 SECOND),next_billing_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?')->execute([$terminalId]);
    for($retry=1;$retry<=3;$retry++){
        $attempt=mg_subscription_attempt($pdo,mg_sub_row($pdo,$terminalId));$payment=(string)$attempt['provider_payment_id'];
        $result=mg_subscription_apply_payment_failure($pdo,mg_sub_attempt_row($pdo,$payment),$payment,'Terminal decline '.$retry);
        if($retry<3){mg_sub_assert($result['status']==='past_due','Dunning paused too early.');$pdo->prepare('UPDATE subscriptions SET next_billing_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?')->execute([$terminalId]);}
        else mg_sub_assert($result['status']==='paused'&&$result['next_retry_at']===null,'Dunning did not reach terminal paused state.');
    }
    $summary['dunning_exhausted']=true;

    $alerts=(int)mg_sub_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE user_id=? AND alert_type IN ('subscription_activated','subscription_renewed','subscription_payment_failed')",[$subscriberId]);
    $events=(int)mg_sub_scalar($pdo,'SELECT COUNT(*) FROM subscription_events WHERE subscription_id IN (?,?,?)',[$subscriptionId,$recoveryId,$terminalId]);
    mg_sub_assert($events>=12,'Subscription audit history is incomplete.');
    mg_sub_assert($alerts===9,'Subscription notifications were not created exactly once.');
    $summary['notifications_once']=true;

    $rollbackFixture=mg_sub_create($pdo,$ownerId,$subscriberId,$runId,'rollback');
    $rollbackId=(int)$rollbackFixture['subscription']['id'];$rollbackPayment=(string)$rollbackFixture['attempt']['provider_payment_id'];
    $beforeGroups=(int)mg_sub_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups');
    $beforeEvents=(int)mg_sub_scalar($pdo,'SELECT COUNT(*) FROM subscription_events WHERE subscription_id=?',[$rollbackId]);
    $pdo->exec('SAVEPOINT subscription_failure');$forced=false;
    try{
        mg_subscription_apply_payment_success($pdo,mg_sub_attempt_row($pdo,$rollbackPayment),$rollbackPayment,static function(string $stage): void {if($stage==='after_ledger')throw new RuntimeException('Forced subscription failure.');});
    }catch(Throwable){$forced=true;}
    mg_sub_assert($forced,'Forced subscription settlement did not fail.');
    $pdo->exec('ROLLBACK TO SAVEPOINT subscription_failure');
    $rollbackState=mg_sub_row($pdo,$rollbackId);
    mg_sub_assert((string)$rollbackState['status']==='pending_payment'&&(int)$rollbackState['initial_payment_required']===1,'Forced failure activated subscription.');
    mg_sub_assert((string)mg_sub_scalar($pdo,'SELECT status FROM subscription_attempts WHERE provider_payment_id=?',[$rollbackPayment])==='processing','Forced failure settled attempt.');
    mg_sub_assert((int)mg_sub_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups')===$beforeGroups,'Forced failure left ledger state.');
    mg_sub_assert((int)mg_sub_scalar($pdo,'SELECT COUNT(*) FROM subscription_events WHERE subscription_id=?',[$rollbackId])===$beforeEvents,'Forced failure left lifecycle events.');
    $summary['forced_failure_rolled_back']=true;

    $tipCount=(int)mg_sub_scalar($pdo,'SELECT COUNT(*) FROM tips WHERE metadata_json LIKE ?',['%'.$runId.'%']);
    $attemptCount=(int)mg_sub_scalar($pdo,'SELECT COUNT(*) FROM subscription_attempts WHERE subscription_id IN (?,?,?,?)',[$subscriptionId,$recoveryId,$terminalId,$rollbackId]);
    mg_sub_assert($tipCount===$attemptCount,'Subscription attempts and financial tip rows diverged.');
    $summary['financial_rows_consistent']=true;

    $pdo->rollBack();
    mg_sub_assert((int)mg_sub_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$ownerEmail,$subscriberEmail])===0,'Subscription users remain.');
    mg_sub_assert((int)mg_sub_scalar($pdo,'SELECT COUNT(*) FROM subscriptions WHERE idempotency_key LIKE ?',['behavior:subscription:'.$runId.'%'])===0,'Subscription fixtures remain.');
    mg_sub_assert((int)mg_sub_scalar($pdo,'SELECT COUNT(*) FROM tips WHERE metadata_json LIKE ?',['%'.$runId.'%'])===0,'Subscription financial fixtures remain.');
    $summary['fixtures_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
