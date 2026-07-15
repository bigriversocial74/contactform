<?php
declare(strict_types=1);

require_once __DIR__ . '/_stripe_webhook.php';

mg_require_method('POST');

$payload=(string)(file_get_contents('php://input')?:'');
$signature=(string)($_SERVER['HTTP_STRIPE_SIGNATURE']??'');
$pdo=null;

try{
    if($payload==='')throw new InvalidArgumentException('Event payload is required.');
    $pdo=mg_db();
    if(!mg_payment_verify_signature('stripe',$payload,$signature,$pdo))throw new InvalidArgumentException('Invalid Stripe signature.');
    $event=json_decode($payload,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($event))throw new InvalidArgumentException('Invalid event payload.');

    $pdo->beginTransaction();
    $result=mg_subscription_stripe_process_webhook_event($pdo,$event,$payload);
    $pdo->commit();
    mg_ok($result,'Subscription Stripe event processed.');
}catch(InvalidArgumentException|JsonException $error){
    if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),400);
}catch(MgSubscriptionStripeWebhookException|MgSubscriptionPackageWebhookException $error){
    if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    mg_security_log('warning','subscription.stripe_webhook_rejected','Signed Stripe subscription event was rejected.',['reason'=>$error->getMessage()]);
    mg_fail($error->getMessage(),$error->httpStatus);
}catch(Throwable $error){
    if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','subscription.stripe_webhook_failed','Signed Stripe subscription event processing failed.',['exception_class'=>$error::class]);
    mg_fail('Unable to process subscription Stripe event.',500);
}
