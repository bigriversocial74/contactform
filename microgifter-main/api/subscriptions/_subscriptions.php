<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/tips/_tips.php';
require_once dirname(__DIR__) . '/tips/_notifications.php';

function mg_subscription_event(PDO $pdo,int $subscriptionId,string $eventType,?string $from,?string $to,?int $actorUserId,?string $reason=null,array $payload=[]): void
{
    $pdo->prepare('INSERT INTO subscription_events (public_id,subscription_id,event_type,from_status,to_status,actor_user_id,reason_code,payload_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([mg_public_uuid(),$subscriptionId,$eventType,$from,$to,$actorUserId,$reason,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
}

function mg_subscription_period_end(DateTimeImmutable $start,string $unit,int $count): DateTimeImmutable
{
    $count=max(1,$count);
    return match($unit){'week'=>$start->modify('+'.$count.' week'),'year'=>$start->modify('+'.$count.' year'),default=>$start->modify('+'.$count.' month')};
}

function mg_subscription_db_id(array $subscription): int
{
    $id=(int)($subscription['subscription_id']??$subscription['id']??0);
    if($id<1)throw new RuntimeException('Subscription identity is unavailable.');
    return $id;
}

function mg_subscription_public_id(array $subscription): string
{
    return (string)($subscription['subscription_public_id']??$subscription['public_id']??'');
}

function mg_subscription_initial_payment_required(array $subscription): bool
{
    return (int)($subscription['initial_payment_required']??0)===1;
}

function mg_subscription_metadata(array $subscription): array
{
    $metadata=$subscription['metadata_json']??[];
    if(is_string($metadata))$metadata=json_decode($metadata,true);
    return is_array($metadata)?$metadata:[];
}

function mg_subscription_load_plan(PDO $pdo,string $publicId,bool $forUpdate=false): array
{
    $stmt=$pdo->prepare('SELECT * FROM subscription_plans WHERE public_id=? LIMIT 1'.($forUpdate?' FOR UPDATE':''));
    $stmt->execute([$publicId]);$plan=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$plan)throw new RuntimeException('Subscription plan not found.');
    return $plan;
}

function mg_subscription_create_plan(PDO $pdo,int $ownerUserId,array $input): array
{
    $targetType=trim((string)($input['target_type']??''));$targetReference=trim((string)($input['target_reference']??''));
    $name=trim((string)($input['name']??''));$amount=(int)($input['amount_cents']??0);$currency=mg_money_currency((string)($input['currency']??'USD'));
    $intervalUnit=trim((string)($input['interval_unit']??'month'));$intervalCount=max(1,(int)($input['interval_count']??1));
    $trialDays=max(0,min((int)($input['trial_days']??0),365));$fundingType=trim((string)($input['funding_type']??'stripe'));
    if($name===''||$amount<100||!in_array($intervalUnit,['week','month','year'],true)||!in_array($fundingType,['wallet','stripe'],true))throw new InvalidArgumentException('Invalid subscription plan.');
    $target=mg_tip_resolve_target($pdo,$targetType,$targetReference);
    if((int)$target['recipient_user_id']!==$ownerUserId)throw new RuntimeException('You do not own this monetization target.');
    $publicId=mg_public_uuid();
    $pdo->prepare("INSERT INTO subscription_plans (public_id,owner_user_id,target_type,target_reference,name,description,amount_cents,currency,interval_unit,interval_count,trial_days,funding_type,status,provider_price_id,policy_json,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'active',?,?,?,NOW(),NOW())")
        ->execute([$publicId,$ownerUserId,$targetType,$target['target_reference'],$name,mb_substr(trim((string)($input['description']??'')),0,1000),$amount,$currency,$intervalUnit,$intervalCount,$trialDays,$fundingType,trim((string)($input['provider_price_id']??''))?:null,json_encode($input['policy']??[],JSON_THROW_ON_ERROR),json_encode($input['metadata']??[],JSON_THROW_ON_ERROR)]);
    return mg_subscription_load_plan($pdo,$publicId);
}

function mg_subscription_activate_initial(PDO $pdo,array $subscription,?int $actorUserId=null): void
{
    if(!mg_subscription_initial_payment_required($subscription))return;
    $subscriptionId=mg_subscription_db_id($subscription);
    $start=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $end=mg_subscription_period_end($start,(string)$subscription['interval_unit'],(int)$subscription['interval_count']);
    $stmt=$pdo->prepare("UPDATE subscriptions SET status='active',initial_payment_required=0,funded_at=NOW(),activated_at=COALESCE(activated_at,NOW()),current_period_start=?,current_period_end=?,next_billing_at=?,retry_count=0,last_failure_message=NULL,paused_at=NULL,updated_at=NOW() WHERE id=? AND initial_payment_required=1");
    $stmt->execute([$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$subscriptionId]);
    if($stmt->rowCount()===1)mg_subscription_event($pdo,$subscriptionId,'subscription.activated',(string)$subscription['status'],'active',$actorUserId,'initial_payment_succeeded',['period_start'=>$start->format('c'),'period_end'=>$end->format('c')]);
}

function mg_subscription_subscribe(PDO $pdo,int $subscriberUserId,array $input): array
{
    $plan=mg_subscription_load_plan($pdo,trim((string)($input['plan_id']??'')),true);
    if((string)$plan['status']!=='active')throw new RuntimeException('Subscription plan is not active.');
    if((int)$plan['owner_user_id']===$subscriberUserId)throw new RuntimeException('You cannot subscribe to your own plan.');
    $key=trim((string)($input['idempotency_key']??''));if($key==='')throw new InvalidArgumentException('Idempotency key is required.');
    $existing=$pdo->prepare('SELECT * FROM subscriptions WHERE subscriber_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([$subscriberUserId,$key]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC)){
        if((int)$row['plan_id']!==(int)$plan['id'])throw new RuntimeException('Idempotency key is already bound to another subscription.');
        return $row+['duplicate'=>true];
    }
    $start=new DateTimeImmutable('now',new DateTimeZone('UTC'));$trialDays=(int)$plan['trial_days'];
    $trialEnds=$trialDays>0?$start->modify('+'.$trialDays.' day'):null;
    $periodEnd=$trialEnds??$start;$status=$trialDays>0?'trialing':'pending_payment';$publicId=mg_public_uuid();$nextBilling=$trialEnds??$start;
    $providerSubscriptionId=(string)$plan['funding_type']==='stripe'?(trim((string)($input['provider_subscription_id']??''))?:'sub_'.$publicId):null;
    $planMetadata=json_decode((string)($plan['metadata_json']??'{}'),true);$metadata=is_array($input['metadata']??null)?$input['metadata']:(is_array($planMetadata)?$planMetadata:[]);
    $pdo->prepare('INSERT INTO subscriptions (public_id,plan_id,subscriber_user_id,recipient_user_id,target_type,target_reference,amount_cents,currency,funding_type,status,idempotency_key,provider_subscription_id,provider_customer_id,provider_payment_method_ref,current_period_start,current_period_end,next_billing_at,trial_ends_at,initial_payment_required,funded_at,activated_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NULL,NULL,?,NOW(),NOW())')
        ->execute([$publicId,(int)$plan['id'],$subscriberUserId,(int)$plan['owner_user_id'],$plan['target_type'],$plan['target_reference'],(int)$plan['amount_cents'],$plan['currency'],$plan['funding_type'],$status,$key,$providerSubscriptionId,trim((string)($input['provider_customer_id']??''))?:null,trim((string)($input['provider_payment_method_ref']??''))?:null,$start->format('Y-m-d H:i:s'),$periodEnd->format('Y-m-d H:i:s'),$nextBilling->format('Y-m-d H:i:s'),$trialEnds?->format('Y-m-d H:i:s'),json_encode($metadata,JSON_THROW_ON_ERROR)]);
    $subscriptionId=(int)$pdo->lastInsertId();
    mg_subscription_event($pdo,$subscriptionId,'subscription.created',null,$status,$subscriberUserId,null,['plan_id'=>$plan['public_id'],'initial_payment_required'=>true]);
    $stmt=$pdo->prepare('SELECT s.*,p.interval_unit,p.interval_count FROM subscriptions s INNER JOIN subscription_plans p ON p.id=s.plan_id WHERE s.id=?');$stmt->execute([$subscriptionId]);$subscription=$stmt->fetch(PDO::FETCH_ASSOC);
    $attempt=null;
    if($trialDays===0){$attempt=mg_subscription_attempt($pdo,$subscription);$stmt->execute([$subscriptionId]);$subscription=$stmt->fetch(PDO::FETCH_ASSOC);}
    return $subscription+['duplicate'=>false,'initial_attempt'=>$attempt];
}

function mg_subscription_attempt(PDO $pdo,array $subscription): array
{
    $subscriptionId=mg_subscription_db_id($subscription);$subscriptionPublicId=mg_subscription_public_id($subscription);$isInitial=mg_subscription_initial_payment_required($subscription);
    $cycleKey=$isInitial?'initial':gmdate('YmdHis',strtotime((string)$subscription['current_period_end']));$attemptNumber=max(1,(int)$subscription['retry_count']+1);
    $idempotency='subscription:'.$subscriptionPublicId.':'.$cycleKey.':'.$attemptNumber;
    $find=$pdo->prepare('SELECT * FROM subscription_attempts WHERE idempotency_key=? LIMIT 1 FOR UPDATE');$find->execute([$idempotency]);
    if($row=$find->fetch(PDO::FETCH_ASSOC))return $row+['duplicate'=>true,'phase'=>$isInitial?'initial':'renewal'];
    $metadata=mg_subscription_metadata($subscription)+['subscription_id'=>$subscriptionPublicId,'cycle_key'=>$cycleKey,'phase'=>$isInitial?'initial':'renewal'];
    $tip=mg_tip_create($pdo,(int)$subscription['subscriber_user_id'],['target_type'=>$subscription['target_type'],'target_reference'=>$subscription['target_reference'],'amount_cents'=>(int)$subscription['amount_cents'],'currency'=>$subscription['currency'],'funding_type'=>$subscription['funding_type'],'idempotency_key'=>$idempotency,'metadata'=>$metadata]);
    $providerPaymentId=(string)$subscription['funding_type']==='stripe'?trim((string)($tip['provider_payment_id']??'')):null;
    if((string)$subscription['funding_type']==='stripe'&&$providerPaymentId==='')throw new RuntimeException('Subscription tip payment intent is unavailable.');
    $status=(string)$tip['status']==='posted'?'succeeded':'processing';$publicId=mg_public_uuid();
    $tipStmt=$pdo->prepare('SELECT id FROM tips WHERE public_id=? LIMIT 1');$tipStmt->execute([$tip['public_id']]);$tipId=(int)$tipStmt->fetchColumn();
    $pdo->prepare('INSERT INTO subscription_attempts (public_id,subscription_id,cycle_key,attempt_number,status,tip_id,provider_payment_id,idempotency_key,amount_cents,currency,scheduled_at,started_at,completed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),IF(?=\'succeeded\',NOW(),NULL),NOW(),NOW())')
        ->execute([$publicId,$subscriptionId,$cycleKey,$attemptNumber,$status,$tipId,$providerPaymentId,$idempotency,(int)$subscription['amount_cents'],$subscription['currency'],$status]);
    if($status==='succeeded'){mg_tip_notify_recipient($pdo,$tip);if($isInitial)mg_subscription_activate_initial($pdo,$subscription,(int)$subscription['subscriber_user_id']);else mg_subscription_advance($pdo,$subscription,(int)$subscription['subscriber_user_id']);}
    return ['public_id'=>$publicId,'status'=>$status,'provider_payment_id'=>$providerPaymentId,'tip'=>$tip,'phase'=>$isInitial?'initial':'renewal','duplicate'=>false];
}

function mg_subscription_advance(PDO $pdo,array $subscription,?int $actorUserId=null): void
{
    $subscriptionId=mg_subscription_db_id($subscription);$start=new DateTimeImmutable((string)$subscription['current_period_end'],new DateTimeZone('UTC'));$end=mg_subscription_period_end($start,(string)$subscription['interval_unit'],(int)$subscription['interval_count']);$newStatus=(int)$subscription['cancel_at_period_end']===1?'canceled':'active';
    $pdo->prepare('UPDATE subscriptions SET status=?,current_period_start=?,current_period_end=?,next_billing_at=?,retry_count=0,last_failure_message=NULL,canceled_at=IF(?=\'canceled\',NOW(),canceled_at),updated_at=NOW() WHERE id=?')
        ->execute([$newStatus,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$newStatus==='active'?$end->format('Y-m-d H:i:s'):null,$newStatus,$subscriptionId]);
    mg_subscription_event($pdo,$subscriptionId,'subscription.renewed',(string)$subscription['status'],$newStatus,$actorUserId,null,['period_end'=>$end->format('c')]);
}

function mg_subscription_mark_failure(PDO $pdo,array $subscription,string $message,?int $actorUserId=null): array
{
    $subscriptionId=mg_subscription_db_id($subscription);$retry=(int)$subscription['retry_count']+1;$maxRetries=max(1,min((int)(getenv('MG_SUBSCRIPTION_MAX_RETRIES')?:3),10));$status=$retry>=$maxRetries?'paused':'past_due';$nextRetry=$status==='past_due'?(new DateTimeImmutable('+'.min(7,$retry*2).' day'))->format('Y-m-d H:i:s'):null;$initial=mg_subscription_initial_payment_required($subscription);
    $pdo->prepare('UPDATE subscriptions SET status=?,retry_count=?,last_failure_message=?,next_billing_at=?,paused_at=IF(?=\'paused\',NOW(),paused_at),updated_at=NOW() WHERE id=?')->execute([$status,$retry,mb_substr($message,0,500),$nextRetry,$status,$subscriptionId]);
    mg_subscription_event($pdo,$subscriptionId,'subscription.payment_failed',(string)$subscription['status'],$status,$actorUserId,'payment_failed',['retry_count'=>$retry,'next_retry_at'=>$nextRetry,'initial_payment'=>$initial]);
    return ['status'=>$status,'retry_count'=>$retry,'next_retry_at'=>$nextRetry,'initial_payment'=>$initial];
}
