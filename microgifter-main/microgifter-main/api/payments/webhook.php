<?php
declare(strict_types=1);
require_once __DIR__ . '/_capture.php';
require_once __DIR__ . '/_disputes.php';

mg_require_method('POST');
$provider=trim((string)($_GET['provider']??''));
$payload=file_get_contents('php://input')?:'';
$signature=(string)($_SERVER['HTTP_X_MG_SIGNATURE']??'');
$event=json_decode($payload,true);
if($provider===''||!is_array($event))mg_fail('Invalid webhook.',422);
$eventId=trim((string)($event['id']??''));
$type=trim((string)($event['type']??''));
if($eventId===''||$type==='')mg_fail('Invalid webhook event.',422);

$valid=mg_payment_verify_signature($provider,$payload,$signature);
if(!$valid){
    mg_security_log('warning','payment.webhook_invalid_signature','Payment webhook signature validation failed.',[
        'provider'=>$provider,
        'provider_event_id'=>$eventId,
        'event_type'=>$type,
    ]);
    mg_fail('Invalid webhook signature.',401);
}

$disputeTypes=['charge.dispute.created','payment.dispute.created','dispute.opened','charge.dispute.closed.won','payment.dispute.won','dispute.won','charge.dispute.closed.lost','payment.dispute.lost','dispute.lost'];
if(in_array($type,$disputeTypes,true)){
    $pdo=mg_db();$pdo->beginTransaction();
    try{
        $result=mg_dispute_process_webhook($pdo,$provider,$event,$payload);
        $pdo->commit();
        mg_ok(['received'=>true]+$result,!empty($result['duplicate'])?'Dispute webhook already processed.':'Dispute webhook processed.');
    }catch(MgDisputeWorkflowException $e){
        if($pdo->inTransaction())$pdo->rollBack();
        mg_fail($e->getMessage(),$e->httpStatus);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        mg_fail('Unable to process dispute webhook.',500);
    }
}

$payloadHash=hash('sha256',$payload);
$pdo=mg_db();
$existingStmt=$pdo->prepare('SELECT signature_valid,status,payload_hash,event_type FROM payment_webhook_events WHERE provider_key=? AND provider_event_id=? LIMIT 1');
$existingStmt->execute([$provider,$eventId]);
$existing=$existingStmt->fetch();
if($existing){
    $sameEvent=(int)$existing['signature_valid']===1
        && hash_equals((string)$existing['payload_hash'],$payloadHash)
        && hash_equals((string)$existing['event_type'],$type);
    if(!$sameEvent){
        mg_security_log('critical','payment.webhook_idempotency_conflict','Provider event ID was replayed with a different signed payload.',[
            'provider'=>$provider,
            'provider_event_id'=>$eventId,
            'event_type'=>$type,
        ]);
        mg_fail('Webhook event conflicts with an existing provider event.',409);
    }
    if(in_array((string)$existing['status'],['processed','ignored'],true)){
        mg_ok(['duplicate'=>true,'status'=>(string)$existing['status']],'Webhook already processed.');
    }
    $pdo->prepare("UPDATE payment_webhook_events SET status='received',failure_message=NULL,received_at=NOW() WHERE provider_key=? AND provider_event_id=?")
        ->execute([$provider,$eventId]);
}else{
    $pdo->prepare("INSERT INTO payment_webhook_events (public_id,provider_key,provider_event_id,event_type,signature_valid,status,payload_hash,payload_json,received_at) VALUES (?,?,?,?,1,'received',?,?,NOW())")
        ->execute([mg_public_uuid(),$provider,$eventId,$type,$payloadHash,$payload]);
}

$data=is_array($event['data']??null)?$event['data']:[];
$orderPublicId=trim((string)($data['order_id']??$data['metadata']['order_id']??''));
$intentPublicId=trim((string)($data['payment_intent_id']??$data['metadata']['payment_intent_id']??''));
$providerRef=trim((string)($data['provider_reference']??$data['id']??$eventId));
$processed=false;
$pdo->beginTransaction();
try{
    if(in_array($type,['payment.succeeded','payment_intent.succeeded','checkout.session.completed'],true)){
        $stmt=$pdo->prepare('SELECT o.id order_db_id,o.public_id order_id,o.merchant_user_id,pi.id intent_db_id FROM commerce_orders o INNER JOIN payment_intents pi ON pi.order_id=o.id WHERE (o.public_id=? OR pi.public_id=?) LIMIT 1 FOR UPDATE');
        $stmt->execute([$orderPublicId,$intentPublicId]);
        $row=$stmt->fetch();
        if($row){
            mg_finance_record_paid_order($pdo,(int)$row['order_db_id'],(int)$row['intent_db_id'],$providerRef,null);
            $processed=true;
        }
    }

    if(in_array($type,['payment.failed','payment_intent.payment_failed','checkout.session.failed'],true) && ($orderPublicId!==''||$intentPublicId!=='')){
        $stmt=$pdo->prepare('SELECT o.id order_db_id,o.public_id order_id,o.merchant_user_id,o.buyer_user_id,o.payment_status,pi.id intent_db_id,pi.status intent_status FROM commerce_orders o INNER JOIN payment_intents pi ON pi.order_id=o.id WHERE (o.public_id=? OR pi.public_id=?) LIMIT 1 FOR UPDATE');
        $stmt->execute([$orderPublicId,$intentPublicId]);
        if($row=$stmt->fetch()){
            if((string)$row['payment_status']==='paid'||(string)$row['intent_status']==='succeeded'){
                mg_security_log('warning','payment.webhook_stale_failure','Ignored payment failure received after successful capture.',[
                    'provider'=>$provider,
                    'provider_event_id'=>$eventId,
                    'order_id'=>(string)$row['order_id'],
                ]);
                $processed=true;
            }else{
                $pdo->prepare("UPDATE payment_intents SET status='failed',failure_code=?,failure_message=?,updated_at=NOW() WHERE id=? AND status<>'succeeded'")
                    ->execute([$data['failure_code']??null,mb_substr((string)($data['failure_message']??'Provider reported failure.'),0,500),(int)$row['intent_db_id']]);
                $pdo->prepare("UPDATE commerce_orders SET payment_status='failed',updated_at=NOW() WHERE id=? AND payment_status<>'paid'")
                    ->execute([(int)$row['order_db_id']]);
                mg_payment_alert($pdo,(int)$row['merchant_user_id'],'payment_failed','high','Payment failed','A checkout payment failed and requires review.','/merchant-payments.php',['merchant_user_id'=>(int)$row['merchant_user_id'],'order_id'=>(string)$row['order_id']]);
                $processed=true;
            }
        }
    }

    $pdo->prepare("UPDATE payment_webhook_events SET status=?,processed_at=NOW() WHERE provider_key=? AND provider_event_id=?")
        ->execute([$processed?'processed':'ignored',$provider,$eventId]);
    $pdo->commit();
    mg_ok(['received'=>true,'processed'=>$processed]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    $pdo->prepare("UPDATE payment_webhook_events SET status='failed',failure_message=? WHERE provider_key=? AND provider_event_id=?")
        ->execute([mb_substr($e->getMessage(),0,500),$provider,$eventId]);
    mg_fail('Unable to process payment webhook.',500);
}
