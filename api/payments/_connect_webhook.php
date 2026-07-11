<?php
declare(strict_types=1);

require_once __DIR__ . '/_connect.php';

function mg_payment_connect_webhook_account_id(array $event): string
{
    $eventAccount=trim((string)($event['account']??''));
    if(str_starts_with($eventAccount,'acct_'))return $eventAccount;
    $object=mg_payment_webhook_object($event);
    $objectId=trim((string)($object['id']??''));
    return str_starts_with($objectId,'acct_')?$objectId:'';
}

function mg_payment_connect_process_webhook(PDO $pdo,array $event,string $payload): array
{
    $eventId=trim((string)($event['id']??''));
    $type=trim((string)($event['type']??''));
    if($eventId===''||!in_array($type,['account.updated','account.application.deauthorized'],true)){
        throw new MgPaymentWebhookException('Invalid Stripe Connect webhook event.',422);
    }

    $payloadHash=hash('sha256',$payload);
    $existingStmt=$pdo->prepare('SELECT signature_valid,status,payload_hash,event_type FROM payment_webhook_events WHERE provider_key=? AND provider_event_id=? LIMIT 1 FOR UPDATE');
    $existingStmt->execute(['stripe',$eventId]);
    $existing=$existingStmt->fetch(PDO::FETCH_ASSOC);
    if($existing){
        $same=(int)$existing['signature_valid']===1
            &&hash_equals((string)$existing['payload_hash'],$payloadHash)
            &&hash_equals((string)$existing['event_type'],$type);
        if(!$same)throw new MgPaymentWebhookException('Stripe Connect webhook conflicts with an existing event.',409);
        if(in_array((string)$existing['status'],['processed','ignored'],true)){
            return ['duplicate'=>true,'status'=>(string)$existing['status'],'processed'=>(string)$existing['status']==='processed','event_type'=>$type];
        }
        $pdo->prepare("UPDATE payment_webhook_events SET status='processing',failure_message=NULL,received_at=NOW() WHERE provider_key='stripe' AND provider_event_id=?")->execute([$eventId]);
    }else{
        $pdo->prepare("INSERT INTO payment_webhook_events (public_id,provider_key,provider_event_id,event_type,signature_valid,status,payload_hash,payload_json,received_at) VALUES (?,'stripe',?,?,1,'processing',?,?,NOW())")
            ->execute([mg_public_uuid(),$eventId,$type,$payloadHash,$payload]);
    }

    $accountId=mg_payment_connect_webhook_account_id($event);
    $processed=false;
    $merchantUserId=null;
    if($accountId!==''){
        $accountStmt=$pdo->prepare("SELECT * FROM payment_provider_accounts WHERE provider_key='stripe' AND provider_account_reference=? ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $accountStmt->execute([$accountId]);
        $account=$accountStmt->fetch(PDO::FETCH_ASSOC);
        if($account){
            $merchantUserId=(int)$account['merchant_user_id'];
            if($type==='account.application.deauthorized'){
                $pdo->prepare("UPDATE payment_provider_accounts SET status='disabled',charges_enabled=0,payouts_enabled=0,onboarding_status='disabled',disconnected_at=NOW(),updated_at=NOW() WHERE id=?")
                    ->execute([(int)$account['id']]);
            }else{
                $object=mg_payment_webhook_object($event);
                $charges=!empty($object['charges_enabled'])?1:0;
                $payouts=!empty($object['payouts_enabled'])?1:0;
                $details=!empty($object['details_submitted'])?1:0;
                $due=is_array($object['requirements']['currently_due']??null)?array_values($object['requirements']['currently_due']):[];
                $capabilities=is_array($object['capabilities']??null)?$object['capabilities']:[];
                $status=$charges&&$payouts?'active':($details?'restricted':'pending');
                $onboarding=$charges&&$payouts?'complete':($details?'restricted':'pending');
                $pdo->prepare("UPDATE payment_provider_accounts SET status=?,charges_enabled=?,payouts_enabled=?,details_submitted=?,onboarding_status=?,account_type=COALESCE(NULLIF(?,''),account_type),capabilities_json=?,requirements_due_json=?,last_synced_at=NOW(),updated_at=NOW() WHERE id=?")
                    ->execute([$status,$charges,$payouts,$details,$onboarding,(string)($object['type']??''),json_encode($capabilities,JSON_THROW_ON_ERROR),json_encode($due,JSON_THROW_ON_ERROR),(int)$account['id']]);
            }
            $updated=mg_payment_provider_account($pdo,$merchantUserId,'stripe',(string)$account['mode'],false);
            mg_payment_connect_update_readiness($pdo,$merchantUserId,mg_payment_connect_account_payload($updated,'stripe',(string)$account['mode']));
            $processed=true;
        }
    }

    $status=$processed?'processed':'ignored';
    $pdo->prepare("UPDATE payment_webhook_events SET status=?,processed_at=NOW(),failure_message=NULL WHERE provider_key='stripe' AND provider_event_id=?")
        ->execute([$status,$eventId]);

    return [
        'duplicate'=>false,
        'status'=>$status,
        'processed'=>$processed,
        'event_type'=>$type,
        'account_id'=>$accountId!==''?$accountId:null,
        'merchant_user_id'=>$merchantUserId,
    ];
}
