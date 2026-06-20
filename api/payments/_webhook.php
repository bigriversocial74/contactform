<?php
declare(strict_types=1);

require_once __DIR__ . '/_capture.php';

final class MgPaymentWebhookException extends RuntimeException
{
    public function __construct(string $message,public readonly int $httpStatus=409)
    {
        parent::__construct($message);
    }
}

function mg_payment_webhook_object(array $event): array
{
    $data=$event['data']??[];
    if(is_array($data)&&is_array($data['object']??null))return $data['object'];
    return is_array($data)?$data:[];
}

function mg_payment_webhook_identifiers(string $provider,array $event): array
{
    $object=mg_payment_webhook_object($event);
    $metadata=is_array($object['metadata']??null)?$object['metadata']:[];
    if($provider!=='stripe'&&is_array($event['data']??null)){
        $legacy=$event['data'];
        $metadata=array_merge(is_array($legacy['metadata']??null)?$legacy['metadata']:[],$metadata);
        $object=array_merge($legacy,$object);
    }
    $objectType=(string)($object['object']??'');
    $providerIntent='';
    $providerSession='';
    if($objectType==='checkout.session'){
        $providerSession=trim((string)($object['id']??''));
        $paymentIntent=$object['payment_intent']??'';
        $providerIntent=is_array($paymentIntent)?trim((string)($paymentIntent['id']??'')):trim((string)$paymentIntent);
    }elseif($objectType==='payment_intent'){
        $providerIntent=trim((string)($object['id']??''));
    }
    return [
        'object'=>$object,
        'metadata'=>$metadata,
        'order_id'=>trim((string)($metadata['order_id']??$object['client_reference_id']??$object['order_id']??'')),
        'payment_intent_id'=>trim((string)($metadata['payment_intent_id']??$object['payment_intent_id']??'')),
        'checkout_session_id'=>trim((string)($metadata['checkout_session_id']??$object['checkout_session_id']??'')),
        'provider_intent_reference'=>$providerIntent!==''?$providerIntent:trim((string)($object['provider_reference']??$object['id']??'')),
        'provider_session_reference'=>$providerSession,
        'amount_cents'=>(int)($object['amount_total']??$object['amount_received']??$object['amount']??0),
        'currency'=>strtoupper(trim((string)($object['currency']??''))),
        'payment_status'=>trim((string)($object['payment_status']??'')),
        'failure_code'=>trim((string)($object['last_payment_error']['code']??$object['failure_code']??'')),
        'failure_message'=>trim((string)($object['last_payment_error']['message']??$object['failure_message']??'Provider reported failure.')),
        'application_fee_cents'=>(int)($object['application_fee_amount']??0),
    ];
}

function mg_payment_webhook_find_order(PDO $pdo,string $provider,array $ids): ?array
{
    $stmt=$pdo->prepare(
        'SELECT o.id order_db_id,o.public_id order_id,o.merchant_user_id,o.buyer_user_id,
                o.total_cents,o.platform_fee_cents,o.currency,o.payment_status,
                pi.id intent_db_id,pi.public_id payment_intent_id,pi.provider_intent_reference,
                pi.status intent_status,pi.application_fee_cents,pi.destination_account_reference,
                cs.id session_db_id,cs.public_id checkout_session_id,cs.provider_session_reference
         FROM commerce_orders o
         INNER JOIN payment_intents pi ON pi.order_id=o.id
         LEFT JOIN checkout_sessions cs ON cs.payment_intent_id=pi.id
         WHERE pi.provider_key=? AND (
            o.public_id=? OR pi.public_id=? OR cs.public_id=? OR
            pi.provider_intent_reference=? OR cs.provider_session_reference=?
         )
         ORDER BY pi.id DESC LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([
        $provider,
        $ids['order_id'],$ids['payment_intent_id'],$ids['checkout_session_id'],
        $ids['provider_intent_reference'],$ids['provider_session_reference'],
    ]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_payment_webhook_assert_amount(array $row,array $ids): void
{
    if((int)$ids['amount_cents']>0&&(int)$ids['amount_cents']!==(int)$row['total_cents']){
        throw new MgPaymentWebhookException('Provider payment amount does not match the order.',409);
    }
    if((string)$ids['currency']!==''&&!hash_equals((string)$row['currency'],(string)$ids['currency'])){
        throw new MgPaymentWebhookException('Provider payment currency does not match the order.',409);
    }
    if((int)$ids['application_fee_cents']>0&&(int)$ids['application_fee_cents']!==(int)$row['platform_fee_cents']){
        throw new MgPaymentWebhookException('Provider application fee does not match the order fee snapshot.',409);
    }
}

function mg_payment_process_webhook_event(PDO $pdo,string $provider,array $event,string $payload): array
{
    $eventId=trim((string)($event['id']??''));
    $type=trim((string)($event['type']??''));
    if($provider===''||$eventId===''||$type==='')throw new MgPaymentWebhookException('Invalid webhook event.',422);
    $payloadHash=hash('sha256',$payload);

    $existingStmt=$pdo->prepare('SELECT signature_valid,status,payload_hash,event_type FROM payment_webhook_events WHERE provider_key=? AND provider_event_id=? LIMIT 1 FOR UPDATE');
    $existingStmt->execute([$provider,$eventId]);
    $existing=$existingStmt->fetch(PDO::FETCH_ASSOC);
    if($existing){
        $same=(int)$existing['signature_valid']===1
            &&hash_equals((string)$existing['payload_hash'],$payloadHash)
            &&hash_equals((string)$existing['event_type'],$type);
        if(!$same){
            mg_security_log('critical','payment.webhook_idempotency_conflict','Provider event ID was replayed with a different signed payload.',[
                'provider'=>$provider,
                'provider_event_id'=>$eventId,
                'event_type'=>$type,
            ]);
            throw new MgPaymentWebhookException('Webhook event conflicts with an existing provider event.',409);
        }
        if(in_array((string)$existing['status'],['processed','ignored'],true)){
            return ['duplicate'=>true,'status'=>(string)$existing['status'],'processed'=>(string)$existing['status']==='processed'];
        }
        $pdo->prepare("UPDATE payment_webhook_events SET status='processing',failure_message=NULL,received_at=NOW() WHERE provider_key=? AND provider_event_id=?")
            ->execute([$provider,$eventId]);
    }else{
        $pdo->prepare("INSERT INTO payment_webhook_events (public_id,provider_key,provider_event_id,event_type,signature_valid,status,payload_hash,payload_json,received_at) VALUES (?,?,?,?,1,'processing',?,?,NOW())")
            ->execute([mg_public_uuid(),$provider,$eventId,$type,$payloadHash,$payload]);
    }

    $successTypes=['payment.succeeded','payment_intent.succeeded','checkout.session.completed','checkout.session.async_payment_succeeded'];
    $failureTypes=['payment.failed','payment_intent.payment_failed','checkout.session.failed','checkout.session.async_payment_failed'];
    $processed=false;
    $ids=mg_payment_webhook_identifiers($provider,$event);
    $row=null;

    if(in_array($type,$successTypes,true)){
        if($provider==='stripe'&&$type==='checkout.session.completed'&&$ids['payment_status']!==''&&$ids['payment_status']!=='paid'){
            $processed=false;
        }else{
            $row=mg_payment_webhook_find_order($pdo,$provider,$ids);
            if(!$row)throw new MgPaymentWebhookException('Webhook payment could not be matched to an internal order.',404);
            mg_payment_webhook_assert_amount($row,$ids);
            $providerReference=trim((string)$ids['provider_intent_reference']);
            if($providerReference==='')throw new MgPaymentWebhookException('Provider payment reference is missing.',422);
            if($ids['provider_intent_reference']!==''){
                $pdo->prepare("UPDATE payment_intents SET provider_intent_reference=?,updated_at=NOW() WHERE id=? AND (provider_intent_reference IS NULL OR provider_intent_reference='' OR provider_intent_reference=?)")
                    ->execute([$ids['provider_intent_reference'],(int)$row['intent_db_id'],$ids['provider_intent_reference']]);
            }
            if($ids['provider_session_reference']!==''&&!empty($row['session_db_id'])){
                $pdo->prepare("UPDATE checkout_sessions SET provider_session_reference=?,status='completed',completed_at=COALESCE(completed_at,NOW()),updated_at=NOW() WHERE id=?")
                    ->execute([$ids['provider_session_reference'],(int)$row['session_db_id']]);
            }
            mg_finance_record_paid_order($pdo,(int)$row['order_db_id'],(int)$row['intent_db_id'],$providerReference,null);
            $processed=true;
        }
    }elseif(in_array($type,$failureTypes,true)){
        $row=mg_payment_webhook_find_order($pdo,$provider,$ids);
        if($row){
            if((string)$row['payment_status']==='paid'||(string)$row['intent_status']==='succeeded'){
                mg_security_log('warning','payment.webhook_stale_failure','Ignored payment failure received after successful capture.',[
                    'provider'=>$provider,
                    'provider_event_id'=>$eventId,
                    'order_id'=>(string)$row['order_id'],
                ]);
                $processed=true;
            }else{
                $pdo->prepare("UPDATE payment_intents SET status='failed',failure_code=?,failure_message=?,updated_at=NOW() WHERE id=? AND status<>'succeeded'")
                    ->execute([$ids['failure_code']?:null,mb_substr((string)$ids['failure_message'],0,500),(int)$row['intent_db_id']]);
                $pdo->prepare("UPDATE commerce_orders SET payment_status='failed',updated_at=NOW() WHERE id=? AND payment_status<>'paid'")
                    ->execute([(int)$row['order_db_id']]);
                if(!empty($row['session_db_id'])){
                    $pdo->prepare("UPDATE checkout_sessions SET status='failed',updated_at=NOW() WHERE id=? AND status<>'completed'")
                        ->execute([(int)$row['session_db_id']]);
                }
                mg_payment_alert($pdo,(int)$row['merchant_user_id'],'payment_failed','high','Payment failed','A checkout payment failed and requires review.','/merchant-payments.php',['merchant_user_id'=>(int)$row['merchant_user_id'],'order_id'=>(string)$row['order_id']]);
                $processed=true;
            }
        }
    }

    $status=$processed?'processed':'ignored';
    $pdo->prepare('UPDATE payment_webhook_events SET status=?,processed_at=NOW(),failure_message=NULL WHERE provider_key=? AND provider_event_id=?')
        ->execute([$status,$provider,$eventId]);
    return [
        'duplicate'=>false,
        'status'=>$status,
        'processed'=>$processed,
        'order_id'=>$row['order_id']??null,
        'event_type'=>$type,
    ];
}
