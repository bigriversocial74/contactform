<?php
declare(strict_types=1);

require_once __DIR__ . '/_subscriptions.php';
require_once __DIR__ . '/_notifications.php';

function mg_subscription_recovery_context(PDO $pdo,string $tipRecoveryPublicId): ?array
{
    $available=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('subscriptions','subscription_attempts','subscription_payment_recoveries')")->fetchColumn();
    if($available!==3)return null;

    $stmt=$pdo->prepare(
        "SELECT
            r.id tip_recovery_id,
            r.public_id tip_recovery_public_id,
            r.recovery_type,
            r.provider_reference,
            r.amount_cents recovery_amount_cents,
            r.currency recovery_currency,
            a.id attempt_id,
            a.public_id attempt_public_id,
            a.cycle_key,
            a.status attempt_status,
            a.amount_cents attempt_amount_cents,
            a.currency attempt_currency,
            a.recovery_status attempt_recovery_status,
            a.recovered_amount_cents,
            s.id subscription_id,
            s.public_id subscription_public_id,
            s.subscriber_user_id,
            s.recipient_user_id,
            s.status subscription_status,
            s.recovery_status subscription_recovery_status,
            s.recovery_attempt_id,
            s.recovery_reference,
            s.pre_recovery_status,
            s.pre_recovery_next_billing_at,
            s.current_period_end,
            s.next_billing_at
         FROM tip_payment_recoveries r
         INNER JOIN subscription_attempts a ON a.tip_id=r.tip_id
         INNER JOIN subscriptions s ON s.id=a.subscription_id
         WHERE r.public_id=?
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$tipRecoveryPublicId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_subscription_recovery_existing(PDO $pdo,int $tipRecoveryId): ?array
{
    $stmt=$pdo->prepare('SELECT * FROM subscription_payment_recoveries WHERE tip_recovery_id=? LIMIT 1');
    $stmt->execute([$tipRecoveryId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_subscription_recovery_event_name(string $kind,bool $full): string
{
    return match($kind){
        'dispute_opened'=>'subscription.recovery_disputed',
        'dispute_won'=>'subscription.recovery_restored',
        'refund'=>$full?'subscription.recovery_refunded':'subscription.recovery_partial_refund',
        'dispute_lost','chargeback'=>'subscription.recovery_chargeback',
        default=>'subscription.recovery_recorded',
    };
}

function mg_subscription_recovery_notify(PDO $pdo,array $context,string $eventType,string $accessAction,int $recoveredAmount): void
{
    $payload=[
        'subscription_attempt_id'=>(string)$context['attempt_public_id'],
        'tip_recovery_id'=>(string)$context['tip_recovery_public_id'],
        'recovery_type'=>(string)$context['recovery_type'],
        'provider_reference'=>(string)$context['provider_reference'],
        'amount_cents'=>(int)$context['recovery_amount_cents'],
        'recovered_amount_cents'=>$recoveredAmount,
        'access_action'=>$accessAction,
    ];
    match($eventType){
        'subscription.recovery_disputed'=>mg_subscription_notify($pdo,$context,'subscription_disputed','Subscription payment disputed','Subscriber access has been suspended while the payment dispute is reviewed.',$payload),
        'subscription.recovery_restored'=>mg_subscription_notify($pdo,$context,'subscription_access_restored','Subscription access restored','The payment dispute was resolved in your favor and eligible subscriber access has been restored.',$payload),
        'subscription.recovery_refunded'=>mg_subscription_notify($pdo,$context,'subscription_refunded','Subscription payment refunded','The subscription payment was fully refunded and subscriber access has been paused.',$payload),
        'subscription.recovery_chargeback'=>mg_subscription_notify($pdo,$context,'subscription_chargeback','Subscription payment reversed','The subscription payment was reversed and subscriber access has been paused.',$payload),
        'subscription.recovery_partial_refund'=>mg_subscription_notify($pdo,$context,'subscription_partial_refund','Subscription payment partially refunded','A partial refund was recorded. Subscriber access remains governed by the current paid period.',$payload),
        default=>null,
    };
}

function mg_subscription_reconcile_tip_recovery(PDO $pdo,array $tipRecoveryResult,?callable $failureHook=null): ?array
{
    $tipRecoveryPublicId=trim((string)($tipRecoveryResult['recovery_id']??''));
    if($tipRecoveryPublicId==='')return null;

    $context=mg_subscription_recovery_context($pdo,$tipRecoveryPublicId);
    if(!$context)return null;

    $existing=mg_subscription_recovery_existing($pdo,(int)$context['tip_recovery_id']);
    if($existing)return $existing+['duplicate'=>true];

    if((string)$context['attempt_status']!=='succeeded'){
        throw new RuntimeException('Only succeeded subscription attempts can enter payment recovery.');
    }
    if((string)$context['attempt_currency']!==(string)$context['recovery_currency']){
        throw new RuntimeException('Subscription recovery currency does not match the funded attempt.');
    }

    $kind=(string)$context['recovery_type'];
    $previousStatus=(string)$context['subscription_status'];
    $previousRecovery=(string)$context['subscription_recovery_status'];
    $attemptAmount=(int)$context['attempt_amount_cents'];
    $recoveredBefore=(int)$context['recovered_amount_cents'];
    $recoveredAfter=$recoveredBefore;
    if(in_array($kind,['refund','dispute_lost','chargeback'],true)){
        $recoveredAfter=min($attemptAmount,$recoveredBefore+(int)$context['recovery_amount_cents']);
    }
    $full=$recoveredAfter>=$attemptAmount;
    $resultingStatus=$previousStatus;
    $resultingRecovery=$previousRecovery;
    $accessAction='unchanged';

    if($kind==='dispute_opened'){
        if(!in_array($previousRecovery,['clear','disputed'],true)){
            throw new RuntimeException('Subscription already has a terminal payment recovery.');
        }
        if($previousRecovery==='disputed'&&(int)$context['recovery_attempt_id']!==(int)$context['attempt_id']){
            throw new RuntimeException('Subscription already has another active payment dispute.');
        }
        $resultingRecovery='disputed';
        $accessAction='suspended';
        $pdo->prepare(
            "UPDATE subscriptions SET
                pre_recovery_status=IF(recovery_status='clear',status,pre_recovery_status),
                pre_recovery_next_billing_at=IF(recovery_status='clear',next_billing_at,pre_recovery_next_billing_at),
                recovery_status='disputed',
                recovery_attempt_id=?,
                recovery_reference=?,
                recovery_started_at=COALESCE(recovery_started_at,NOW()),
                recovery_resolved_at=NULL,
                access_suspended_at=COALESCE(access_suspended_at,NOW()),
                next_billing_at=NULL,
                updated_at=NOW()
             WHERE id=?"
        )->execute([(int)$context['attempt_id'],(string)$context['provider_reference'],(int)$context['subscription_id']]);
        $pdo->prepare(
            "UPDATE subscription_attempts SET recovery_status='disputed',recovery_reference=?,recovery_started_at=COALESCE(recovery_started_at,NOW()),recovery_resolved_at=NULL,updated_at=NOW() WHERE id=?"
        )->execute([(string)$context['provider_reference'],(int)$context['attempt_id']]);
    }elseif($kind==='dispute_won'){
        if($previousRecovery==='disputed'&&(int)$context['recovery_attempt_id']===(int)$context['attempt_id']){
            $resultingRecovery='clear';
            $eligible=in_array($previousStatus,['pending_payment','trialing','active','past_due','cancel_pending'],true);
            $accessAction=$eligible?'restored':'unchanged';
            $pdo->prepare(
                "UPDATE subscriptions SET
                    recovery_status='clear',
                    recovery_attempt_id=NULL,
                    recovery_reference=NULL,
                    next_billing_at=IF(?,pre_recovery_next_billing_at,next_billing_at),
                    pre_recovery_status=NULL,
                    pre_recovery_next_billing_at=NULL,
                    recovery_resolved_at=NOW(),
                    access_suspended_at=NULL,
                    updated_at=NOW()
                 WHERE id=?"
            )->execute([$eligible?1:0,(int)$context['subscription_id']]);
        }
        $pdo->prepare(
            "UPDATE subscription_attempts SET recovery_status=IF(recovered_amount_cents>0,'partial_refund','clear'),recovery_reference=NULL,recovery_resolved_at=NOW(),updated_at=NOW() WHERE id=?"
        )->execute([(int)$context['attempt_id']]);
    }elseif($kind==='refund'&&!$full){
        $resultingRecovery=$previousRecovery;
        $pdo->prepare(
            "UPDATE subscription_attempts SET recovered_amount_cents=?,recovery_status=IF(recovery_status='disputed','disputed','partial_refund'),recovery_reference=?,recovery_started_at=COALESCE(recovery_started_at,NOW()),recovery_resolved_at=NOW(),updated_at=NOW() WHERE id=?"
        )->execute([$recoveredAfter,(string)$context['provider_reference'],(int)$context['attempt_id']]);
    }else{
        $resultingRecovery=$kind==='refund'?'refunded':'chargeback';
        $resultingStatus=in_array($previousStatus,['canceled','expired'],true)?$previousStatus:'paused';
        $accessAction='revoked';
        $message=$kind==='refund'?'Subscription funding was fully refunded.':'Subscription funding was reversed by the payment provider.';
        $pdo->prepare(
            "UPDATE subscriptions SET
                pre_recovery_status=COALESCE(pre_recovery_status,status),
                pre_recovery_next_billing_at=COALESCE(pre_recovery_next_billing_at,next_billing_at),
                status=?,
                recovery_status=?,
                recovery_attempt_id=?,
                recovery_reference=?,
                recovery_started_at=COALESCE(recovery_started_at,NOW()),
                recovery_resolved_at=NOW(),
                access_suspended_at=COALESCE(access_suspended_at,NOW()),
                next_billing_at=NULL,
                paused_at=IF(?='paused',COALESCE(paused_at,NOW()),paused_at),
                initial_payment_required=IF(?='initial',1,initial_payment_required),
                last_failure_message=?,
                updated_at=NOW()
             WHERE id=?"
        )->execute([$resultingStatus,$resultingRecovery,(int)$context['attempt_id'],(string)$context['provider_reference'],$resultingStatus,(string)$context['cycle_key'],mb_substr($message,0,500),(int)$context['subscription_id']]);
        $pdo->prepare(
            "UPDATE subscription_attempts SET recovered_amount_cents=?,recovery_status=?,recovery_reference=?,recovery_started_at=COALESCE(recovery_started_at,NOW()),recovery_resolved_at=NOW(),updated_at=NOW() WHERE id=?"
        )->execute([$recoveredAfter,$resultingRecovery,(string)$context['provider_reference'],(int)$context['attempt_id']]);
    }

    if($failureHook)$failureHook('after_subscription_state',['context'=>$context,'access_action'=>$accessAction]);

    $eventType=mg_subscription_recovery_event_name($kind,$full);
    $payload=[
        'tip_recovery_id'=>(string)$context['tip_recovery_public_id'],
        'subscription_attempt_id'=>(string)$context['attempt_public_id'],
        'recovery_type'=>$kind,
        'provider_reference'=>(string)$context['provider_reference'],
        'amount_cents'=>(int)$context['recovery_amount_cents'],
        'recovered_amount_cents'=>$recoveredAfter,
        'attempt_amount_cents'=>$attemptAmount,
        'access_action'=>$accessAction,
    ];
    mg_subscription_event(
        $pdo,
        (int)$context['subscription_id'],
        $eventType,
        $previousStatus,
        $resultingStatus,
        null,
        $kind,
        $payload
    );

    $publicId=mg_public_uuid();
    $pdo->prepare(
        'INSERT INTO subscription_payment_recoveries (public_id,subscription_id,subscription_attempt_id,tip_recovery_id,recovery_type,provider_reference,amount_cents,recovered_amount_cents,previous_subscription_status,resulting_subscription_status,previous_recovery_status,resulting_recovery_status,access_action,payload_json,processed_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
    )->execute([
        $publicId,(int)$context['subscription_id'],(int)$context['attempt_id'],(int)$context['tip_recovery_id'],$kind,
        (string)$context['provider_reference'],(int)$context['recovery_amount_cents'],$recoveredAfter,$previousStatus,$resultingStatus,
        $previousRecovery,$resultingRecovery,$accessAction,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),
    ]);

    $recordId=(int)$pdo->lastInsertId();
    mg_subscription_recovery_notify($pdo,$context,$eventType,$accessAction,$recoveredAfter);
    if($failureHook)$failureHook('before_complete',['context'=>$context,'access_action'=>$accessAction]);

    return [
        'id'=>$recordId,
        'public_id'=>$publicId,
        'subscription_id'=>(string)$context['subscription_public_id'],
        'subscription_attempt_id'=>(string)$context['attempt_public_id'],
        'recovery_type'=>$kind,
        'subscription_status'=>$resultingStatus,
        'subscription_recovery_status'=>$resultingRecovery,
        'access_action'=>$accessAction,
        'recovered_amount_cents'=>$recoveredAfter,
        'duplicate'=>false,
    ];
}
