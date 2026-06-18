<?php
declare(strict_types=1);
require_once __DIR__ . '/_funding.php';
require_once dirname(__DIR__) . '/payments/_payments.php';
mg_require_method('POST');
$provider=trim((string)($_GET['provider']??'stripe'));
$payload=file_get_contents('php://input')?:'';
$signature=(string)($_SERVER['HTTP_X_MG_SIGNATURE']??'');
$event=json_decode($payload,true);
if(!is_array($event)||!mg_payment_verify_signature($provider,$payload,$signature))mg_fail('Invalid subscription payment webhook.',401);
$eventId=trim((string)($event['id']??''));$type=trim((string)($event['type']??''));$data=is_array($event['data']??null)?$event['data']:[];
$paymentId=trim((string)($data['payment_id']??$data['payment_intent']??$data['id']??''));
if($eventId===''||$type===''||$paymentId==='')mg_fail('Invalid subscription payment event.',422);
$pdo=mg_db();
try{$pdo->prepare("INSERT INTO payment_webhook_events (public_id,provider_key,provider_event_id,event_type,signature_valid,status,payload_hash,payload_json,received_at) VALUES (?,?,?,?,1,'received',?,?,NOW())")->execute([mg_public_uuid(),$provider,$eventId,$type,hash('sha256',$payload),$payload]);}
catch(Throwable $e){if(str_contains($e->getMessage(),'Duplicate'))mg_ok(['duplicate'=>true],'Subscription payment webhook already received.');throw $e;}
$pdo->beginTransaction();
try{
$stmt=$pdo->prepare("SELECT a.id attempt_id,a.status attempt_status,s.id subscription_id,s.public_id subscription_public_id,s.subscriber_user_id,s.recipient_user_id,s.target_type,s.target_reference,s.amount_cents,s.currency,s.funding_type,s.status,s.current_period_start,s.current_period_end,s.next_billing_at,s.cancel_at_period_end,s.retry_count,s.trial_ends_at,s.initial_payment_required,s.funded_at,s.activated_at,p.interval_unit,p.interval_count FROM subscription_attempts a INNER JOIN subscriptions s ON s.id=a.subscription_id INNER JOIN subscription_plans p ON p.id=s.plan_id WHERE a.provider_payment_id=? LIMIT 1 FOR UPDATE");
$stmt->execute([$paymentId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Subscription payment attempt not found.');
if(in_array($type,['payment_intent.succeeded','charge.succeeded','subscription.payment_succeeded'],true))$result=mg_subscription_apply_payment_success($pdo,$row,$paymentId);
elseif(in_array($type,['payment_intent.payment_failed','charge.failed','subscription.payment_failed'],true))$result=mg_subscription_apply_payment_failure($pdo,$row,$paymentId,(string)($data['failure_message']??'Provider reported payment failure.'));
else $result=['ignored'=>true];
$pdo->prepare('UPDATE payment_webhook_events SET status=?,processed_at=NOW() WHERE provider_key=? AND provider_event_id=?')->execute([isset($result['ignored'])?'ignored':'processed',$provider,$eventId]);
$pdo->commit();mg_ok(['received'=>true,'result'=>$result]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$pdo->prepare("UPDATE payment_webhook_events SET status='failed',failure_message=? WHERE provider_key=? AND provider_event_id=?")->execute([mb_substr($e->getMessage(),0,500),$provider,$eventId]);mg_fail('Unable to process subscription payment webhook.',500);}
