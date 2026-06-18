<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

function mg_admin_commerce_subscription_detail(PDO $pdo,string $reference): array
{
    $e=mg_admin_commerce_one($pdo,<<<'SQL'
SELECT s.*,p.name plan_name,p.interval_unit,p.interval_count,
COALESCE(mu.display_name,mu.full_name,mu.email) merchant_name,mu.email merchant_email,
COALESCE(cu.display_name,cu.full_name,cu.email) customer_name,cu.email customer_email
FROM subscriptions s INNER JOIN subscription_plans p ON p.id=s.plan_id
INNER JOIN users mu ON mu.id=s.recipient_user_id INNER JOIN users cu ON cu.id=s.subscriber_user_id
WHERE s.public_id=? LIMIT 1
SQL,[$reference]);
    if(!$e)throw new MgAdminCommerceException('Subscription not found.',404);
    $id=(int)$e['id'];
    $attempts=mg_admin_commerce_all($pdo,'SELECT public_id,cycle_key,attempt_number,status,provider_payment_id,amount_cents,currency,failure_code,failure_message,recovery_status,recovered_amount_cents,scheduled_at,started_at,completed_at,next_retry_at,created_at,updated_at FROM subscription_attempts WHERE subscription_id=? ORDER BY created_at DESC,id DESC LIMIT 100',[$id]);
    $events=mg_admin_commerce_all($pdo,'SELECT event_type,from_status,to_status,actor_user_id,reason_code,created_at FROM subscription_events WHERE subscription_id=? ORDER BY created_at DESC,id DESC LIMIT 100',[$id]);
    $recoveries=mg_admin_commerce_all($pdo,'SELECT public_id,recovery_type,provider_reference,amount_cents,recovered_amount_cents,previous_subscription_status,resulting_subscription_status,access_action,processed_at,created_at FROM subscription_payment_recoveries WHERE subscription_id=? ORDER BY created_at DESC,id DESC LIMIT 100',[$id]);
    $timeline=[mg_admin_commerce_timeline_item((string)$e['created_at'],'subscription.created','Subscription created',(string)$e['status'],(string)$e['plan_name'],'subscription')];
    foreach($events as $r)$timeline[]=mg_admin_commerce_timeline_item((string)$r['created_at'],(string)$r['event_type'],str_replace(['.','_'],' ',(string)$r['event_type']),$r['to_status']!==null?(string)$r['to_status']:null,$r['reason_code']!==null?(string)$r['reason_code']:null,'subscription');
    foreach($attempts as $r)$timeline[]=mg_admin_commerce_timeline_item((string)($r['completed_at']??$r['created_at']),'subscription.payment_attempt','Payment attempt #'.(int)$r['attempt_number'],(string)$r['status'],$r['failure_message']!==null?(string)$r['failure_message']:(string)$r['cycle_key'],'payment');
    foreach($recoveries as $r)$timeline[]=mg_admin_commerce_timeline_item((string)($r['processed_at']??$r['created_at']),'subscription.recovery.'.(string)$r['recovery_type'],'Recovery '.str_replace('_',' ',(string)$r['recovery_type']),(string)$r['resulting_subscription_status'],(string)$r['access_action'],'recovery');
    mg_admin_commerce_timeline_sort($timeline);
    return [
        'entity'=>['type'=>'subscription','public_id'=>(string)$e['public_id'],'status'=>(string)$e['status'],'secondary_status'=>(string)$e['recovery_status'],'title'=>(string)$e['plan_name'],'amount_cents'=>(int)$e['amount_cents'],'currency'=>(string)$e['currency'],'merchant'=>['id'=>(int)$e['recipient_user_id'],'display_name'=>(string)$e['merchant_name'],'email'=>(string)$e['merchant_email']],'customer'=>['id'=>(int)$e['subscriber_user_id'],'display_name'=>(string)$e['customer_name'],'email'=>(string)$e['customer_email']],'created_at'=>(string)$e['created_at'],'updated_at'=>(string)$e['updated_at']],
        'facts'=>[mg_admin_commerce_fact('Funding',(string)$e['funding_type']),mg_admin_commerce_fact('Interval',(int)$e['interval_count'].' '.(string)$e['interval_unit']),mg_admin_commerce_fact('Current period start',$e['current_period_start'],'date'),mg_admin_commerce_fact('Current period end',$e['current_period_end'],'date'),mg_admin_commerce_fact('Next billing',$e['next_billing_at'],'date'),mg_admin_commerce_fact('Retry count',(int)$e['retry_count']),mg_admin_commerce_fact('Recovery status',(string)$e['recovery_status'],'status'),mg_admin_commerce_fact('Last failure',$e['last_failure_message'])],
        'related'=>compact('attempts','recoveries'),'timeline'=>$timeline,
    ];
}
