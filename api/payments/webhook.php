<?php
declare(strict_types=1);

require_once __DIR__ . '/_webhook.php';
require_once __DIR__ . '/_disputes.php';

mg_require_method('POST');
$provider=strtolower(trim((string)($_GET['provider']??'')));
$payload=file_get_contents('php://input')?:'';
$signature=$provider==='stripe'
    ? (string)($_SERVER['HTTP_STRIPE_SIGNATURE']??'')
    : (string)($_SERVER['HTTP_X_MG_SIGNATURE']??'');
try{$event=json_decode($payload,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){$event=null;}
if($provider===''||!is_array($event))mg_fail('Invalid webhook.',422);
$eventId=trim((string)($event['id']??''));
$type=trim((string)($event['type']??''));
if($eventId===''||$type==='')mg_fail('Invalid webhook event.',422);

$pdo=mg_db();
try{$valid=mg_payment_verify_signature($provider,$payload,$signature,$pdo);}catch(Throwable){$valid=false;}
if(!$valid){
    mg_security_log('warning','payment.webhook_invalid_signature','Payment webhook signature validation failed.',[
        'provider'=>$provider,
        'provider_event_id'=>$eventId,
        'event_type'=>$type,
    ]);
    mg_fail('Invalid webhook signature.',401);
}

$disputeTypes=[
    'charge.dispute.created','payment.dispute.created','dispute.opened',
    'charge.dispute.closed.won','payment.dispute.won','dispute.won',
    'charge.dispute.closed.lost','payment.dispute.lost','dispute.lost',
];
if(in_array($type,$disputeTypes,true)){
    $pdo->beginTransaction();
    try{
        $result=mg_dispute_process_webhook($pdo,$provider,$event,$payload);
        $pdo->commit();
        mg_ok(['received'=>true]+$result,!empty($result['duplicate'])?'Dispute webhook already processed.':'Dispute webhook processed.');
    }catch(MgDisputeWorkflowException $error){
        if($pdo->inTransaction())$pdo->rollBack();
        mg_fail($error->getMessage(),$error->httpStatus);
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        mg_fail('Unable to process dispute webhook.',500);
    }
}

$pdo->beginTransaction();
try{
    $result=mg_payment_process_webhook_event($pdo,$provider,$event,$payload);
    $pdo->commit();
    mg_ok(['received'=>true]+$result,!empty($result['duplicate'])?'Webhook already processed.':'Webhook processed.');
}catch(MgPaymentWebhookException|MgCaptureWorkflowException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('warning','payment.webhook_rejected','Signed payment webhook was rejected.',[
        'provider'=>$provider,
        'provider_event_id'=>$eventId,
        'event_type'=>$type,
        'reason'=>$error->getMessage(),
    ]);
    mg_fail($error->getMessage(),$error->httpStatus);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','payment.webhook_failed','Signed payment webhook processing failed.',[
        'provider'=>$provider,
        'provider_event_id'=>$eventId,
        'event_type'=>$type,
        'exception_class'=>$error::class,
    ]);
    mg_fail('Unable to process payment webhook.',500);
}
