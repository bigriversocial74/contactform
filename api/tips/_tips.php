<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/finance/_money.php';
require_once dirname(__DIR__) . '/payments/_payments.php';

const MG_TIP_TARGET_TYPES=['profile','creator','merchant','location','product','post','gift','claim'];

function mg_tip_fee_snapshot(int $amountCents): array
{
    if($amountCents<100||$amountCents>100000)throw new InvalidArgumentException('Tip amount must be between $1.00 and $1,000.00.');
    $basisPoints=max(0,min((int)(getenv('MG_TIP_FEE_BPS')?:500),5000));
    $fee=(int)floor(($amountCents*$basisPoints)/10000);
    return ['basis_points'=>$basisPoints,'fee_cents'=>$fee,'net_cents'=>$amountCents-$fee,'calculated_at'=>gmdate('c')];
}

function mg_tip_wallet_owner_type(string $targetType): string
{
    return match($targetType){
        'creator'=>'creator',
        'merchant','location','product','post','gift','claim'=>'merchant',
        default=>'user',
    };
}

function mg_tip_resolve_target(PDO $pdo,string $type,string $reference): array
{
    if(!in_array($type,MG_TIP_TARGET_TYPES,true)||$reference==='')throw new InvalidArgumentException('Invalid tip target.');
    $queries=[
        'profile'=>['SELECT id recipient_user_id,CAST(id AS CHAR) target_reference FROM users WHERE id=? AND status=\'active\' LIMIT 1',[ctype_digit($reference)?(int)$reference:0]],
        'creator'=>['SELECT id recipient_user_id,CAST(id AS CHAR) target_reference FROM users WHERE id=? AND status=\'active\' LIMIT 1',[ctype_digit($reference)?(int)$reference:0]],
        'merchant'=>['SELECT merchant_user_id recipient_user_id,public_id target_reference FROM merchant_workspaces WHERE public_id=? AND status=\'active\' LIMIT 1',[$reference]],
        'location'=>['SELECT mw.merchant_user_id recipient_user_id,ml.public_id target_reference FROM merchant_locations ml JOIN merchant_workspaces mw ON mw.id=ml.workspace_id WHERE ml.public_id=? AND ml.status=\'active\' AND mw.status=\'active\' LIMIT 1',[$reference]],
        'product'=>['SELECT merchant_user_id recipient_user_id,public_id target_reference FROM catalog_products WHERE public_id=? AND status=\'published\' LIMIT 1',[$reference]],
        'post'=>['SELECT merchant_user_id recipient_user_id,public_id target_reference FROM feed_posts WHERE public_id=? AND status IN (\'published\',\'promoted\') LIMIT 1',[$reference]],
        'gift'=>['SELECT r.merchant_user_id recipient_user_id,i.public_id target_reference FROM microgift_instances i JOIN microgift_redemptions r ON r.instance_id=i.id AND r.status=\'completed\' WHERE i.public_id=? LIMIT 1',[$reference]],
        'claim'=>['SELECT r.merchant_user_id recipient_user_id,c.public_id target_reference FROM microgift_claims c JOIN microgift_instances i ON i.id=c.instance_id JOIN microgift_redemptions r ON r.instance_id=i.id AND r.status=\'completed\' WHERE c.public_id=? LIMIT 1',[$reference]],
    ];
    [$sql,$params]=$queries[$type];
    $stmt=$pdo->prepare($sql);$stmt->execute($params);$target=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$target)throw new RuntimeException('Tip target is not available.');
    $recipientUserId=(int)$target['recipient_user_id'];
    $walletOwnerType=mg_tip_wallet_owner_type($type);
    return [
        'target_type'=>$type,
        'target_reference'=>(string)$target['target_reference'],
        'recipient_user_id'=>$recipientUserId,
        'recipient_wallet_owner_type'=>$walletOwnerType,
        'recipient_wallet_owner_user_id'=>$recipientUserId,
        'snapshot'=>[
            'target_type'=>$type,
            'target_reference'=>(string)$target['target_reference'],
            'recipient_user_id'=>$recipientUserId,
            'recipient_wallet_owner_type'=>$walletOwnerType,
            'recipient_wallet_owner_user_id'=>$recipientUserId,
            'resolved_at'=>gmdate('c'),
        ],
    ];
}

function mg_tip_request_fingerprint(int $senderUserId,array $target,int $amount,string $currency,string $funding,?string $provider): string
{
    return hash('sha256',json_encode([
        'sender_user_id'=>$senderUserId,
        'target_type'=>$target['target_type'],
        'target_reference'=>$target['target_reference'],
        'recipient_user_id'=>$target['recipient_user_id'],
        'recipient_wallet_owner_type'=>$target['recipient_wallet_owner_type'],
        'recipient_wallet_owner_user_id'=>$target['recipient_wallet_owner_user_id'],
        'amount_cents'=>$amount,
        'currency'=>$currency,
        'funding_type'=>$funding,
        'provider_key'=>$provider,
    ],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
}

function mg_tip_event(PDO $pdo,int $tipId,string $eventType,?int $actorUserId,string $sourceType,?string $sourceReference,string $idempotencyKey,array $payload=[]): array
{
    $existing=$pdo->prepare('SELECT * FROM tip_events WHERE tip_id=? AND idempotency_key=? LIMIT 1');
    $existing->execute([$tipId,$idempotencyKey]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC))return $row+['duplicate'=>true];
    $public=mg_public_uuid();
    $pdo->prepare('INSERT INTO tip_events (public_id,tip_id,event_type,actor_user_id,source_type,source_reference,idempotency_key,payload_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([$public,$tipId,$eventType,$actorUserId,$sourceType,$sourceReference,$idempotencyKey,json_encode($payload,JSON_THROW_ON_ERROR)]);
    return ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$public,'event_type'=>$eventType,'duplicate'=>false];
}

function mg_tip_assert_velocity(PDO $pdo,int $senderUserId,int $amountCents): void
{
    $window=gmdate('YmdH');
    $stmt=$pdo->prepare('SELECT tip_count,amount_cents FROM tip_velocity_counters WHERE sender_user_id=? AND window_key=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$senderUserId,$window]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    $count=(int)($row['tip_count']??0);$amount=(int)($row['amount_cents']??0);
    if($count>=20||$amount+$amountCents>250000)throw new RuntimeException('Tip velocity limit exceeded.');
    $pdo->prepare('INSERT INTO tip_velocity_counters (sender_user_id,window_key,tip_count,amount_cents,updated_at) VALUES (?,?,1,?,NOW()) ON DUPLICATE KEY UPDATE tip_count=tip_count+1,amount_cents=amount_cents+VALUES(amount_cents),updated_at=NOW()')->execute([$senderUserId,$window,$amountCents]);
}

function mg_tip_post_ledger(PDO $pdo,array $tip): array
{
    $currency=(string)$tip['currency'];
    $recipient=mg_wallet_resolve($pdo,(string)$tip['recipient_wallet_owner_type'],(int)$tip['recipient_wallet_owner_user_id'],$currency);
    $recipientAvailable=mg_wallet_account_id($pdo,(int)$recipient['id'],'available',$currency);
    $feeAccount=mg_ledger_platform_account($pdo,'tip_fee_revenue','revenue','credit',$currency);
    if($tip['funding_type']==='wallet'){
        $sender=mg_wallet_resolve($pdo,'user',(int)$tip['sender_user_id'],$currency);
        $balances=mg_wallet_balances($pdo,(int)$sender['id']);
        if((int)$balances['available_cents']<(int)$tip['amount_cents'])throw new RuntimeException('Insufficient wallet balance.');
        $fundingAccount=mg_wallet_account_id($pdo,(int)$sender['id'],'available',$currency);
        $fundingEntry=['ledger_account_id'=>$fundingAccount,'entry_type'=>'debit','amount_cents'=>(int)$tip['amount_cents'],'description'=>'Wallet-funded tip'];
    }else{
        $fundingAccount=mg_ledger_platform_account($pdo,'processor_clearing','asset','debit',$currency);
        $fundingEntry=['ledger_account_id'=>$fundingAccount,'entry_type'=>'debit','amount_cents'=>(int)$tip['amount_cents'],'description'=>'Card-funded tip'];
    }
    $entries=[$fundingEntry,['ledger_account_id'=>$recipientAvailable,'entry_type'=>'credit','amount_cents'=>(int)$tip['net_cents'],'description'=>'Tip recipient proceeds']];
    if((int)$tip['fee_cents']>0)$entries[]=['ledger_account_id'=>$feeAccount,'entry_type'=>'credit','amount_cents'=>(int)$tip['fee_cents'],'description'=>'Tip platform fee'];
    return mg_ledger_post($pdo,['transaction_type'=>'tip','source_type'=>'tip','source_reference'=>(string)$tip['public_id'],'idempotency_key'=>'tip:'.(string)$tip['public_id'],'currency'=>$currency,'description'=>'Universal tip','metadata'=>['tip_id'=>$tip['public_id'],'target_type'=>$tip['target_type'],'target_reference'=>$tip['target_reference'],'recipient_wallet_owner_type'=>$tip['recipient_wallet_owner_type'],'recipient_wallet_owner_user_id'=>(int)$tip['recipient_wallet_owner_user_id']]],$entries,(int)$tip['sender_user_id']);
}

function mg_tip_payment_payload(PDO $pdo,array $tip): array
{
    if((string)$tip['funding_type']!=='stripe'||empty($tip['payment_intent_id']))return $tip;
    $stmt=$pdo->prepare('SELECT * FROM payment_intents WHERE id=? LIMIT 1');
    $stmt->execute([(int)$tip['payment_intent_id']]);
    $intent=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$intent)throw new RuntimeException('Tip payment intent is missing.');
    $providerIntent=mg_payment_provider_retrieve_intent((string)$intent['provider_key'],(string)$intent['provider_intent_reference']);
    return $tip+[
        'payment_intent_public_id'=>(string)$intent['public_id'],
        'provider_payment_id'=>(string)$intent['provider_intent_reference'],
        'client_secret'=>$providerIntent['client_secret']??null,
    ];
}

function mg_tip_create(PDO $pdo,int $senderUserId,array $input): array
{
    $type=trim((string)($input['target_type']??''));$reference=trim((string)($input['target_reference']??''));
    $funding=trim((string)($input['funding_type']??'wallet'));$key=trim((string)($input['idempotency_key']??''));
    $amount=(int)($input['amount_cents']??0);$currency=mg_money_currency((string)($input['currency']??'USD'));
    if(!in_array($funding,['wallet','stripe'],true)||$key==='')throw new InvalidArgumentException('Funding type and idempotency key are required.');
    $existing=$pdo->prepare('SELECT * FROM tips WHERE sender_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([$senderUserId,$key]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC)){
        $same=(int)$row['amount_cents']===$amount
            &&(string)$row['currency']===$currency
            &&(string)$row['target_type']===$type
            &&(string)$row['target_reference']===$reference
            &&(string)$row['funding_type']===$funding;
        if(!$same)throw new RuntimeException('Idempotency key is already bound to a different tip request.');
        $row=mg_tip_payment_payload($pdo,$row);
        $row['duplicate']=true;return $row;
    }
    $target=mg_tip_resolve_target($pdo,$type,$reference);
    if($target['recipient_user_id']===$senderUserId)throw new RuntimeException('You cannot tip yourself.');
    $fee=mg_tip_fee_snapshot($amount);mg_tip_assert_velocity($pdo,$senderUserId,$amount);
    $public=mg_public_uuid();
    $status=$funding==='wallet'?'funded':'pending';
    $provider=$funding==='stripe'?mg_payment_provider_key():null;
    $fingerprint=mg_tip_request_fingerprint($senderUserId,$target,$amount,$currency,$funding,$provider);
    $pdo->prepare('INSERT INTO tips (public_id,sender_user_id,recipient_user_id,recipient_wallet_owner_type,recipient_wallet_owner_user_id,target_type,target_reference,amount_cents,fee_cents,net_cents,currency,funding_type,provider_key,status,idempotency_key,request_fingerprint,provider_payment_id,fee_snapshot_json,target_snapshot_json,metadata_json,funded_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,?,?,?,IF(?=\'funded\',NOW(),NULL),NOW(),NOW())')->execute([$public,$senderUserId,$target['recipient_user_id'],$target['recipient_wallet_owner_type'],$target['recipient_wallet_owner_user_id'],$type,$target['target_reference'],$amount,$fee['fee_cents'],$fee['net_cents'],$currency,$funding,$provider,$status,$key,$fingerprint,json_encode($fee,JSON_THROW_ON_ERROR),json_encode($target['snapshot'],JSON_THROW_ON_ERROR),json_encode($input['metadata']??[],JSON_THROW_ON_ERROR),$status]);
    $tipId=(int)$pdo->lastInsertId();
    $stmt=$pdo->prepare('SELECT * FROM tips WHERE id=?');$stmt->execute([$tipId]);$tip=$stmt->fetch(PDO::FETCH_ASSOC);
    mg_tip_event($pdo,$tipId,$status==='funded'?'funded':'pending',$senderUserId,'tip',$public,'tip-created:'.$public,['funding_type'=>$funding,'amount_cents'=>$amount,'currency'=>$currency]);
    if($funding==='wallet'){
        $group=mg_tip_post_ledger($pdo,$tip);
        $pdo->prepare("UPDATE tips SET status='posted',ledger_group_id=?,posted_at=NOW(),settled_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$group['id'],$tipId]);
        mg_tip_event($pdo,$tipId,'posted',$senderUserId,'ledger_group',(string)$group['public_id'],'tip-posted:'.$public,['ledger_group_id'=>$group['public_id']]);
    }else{
        $intent=mg_payment_create_source_intent($pdo,[
            'provider_key'=>$provider,
            'source_type'=>'tip',
            'source_reference'=>$public,
            'amount_cents'=>$amount,
            'currency'=>$currency,
            'idempotency_key'=>'tip:'.$key,
            'metadata'=>['tip_id'=>$public,'sender_user_id'=>$senderUserId,'recipient_user_id'=>$target['recipient_user_id']],
        ]);
        $tipStatus=in_array((string)$intent['status'],['requires_action','processing'],true)?(string)$intent['status']:'pending';
        $pdo->prepare('UPDATE tips SET payment_intent_id=?,provider_payment_id=?,status=?,updated_at=NOW() WHERE id=?')
            ->execute([(int)$intent['id'],(string)$intent['provider_intent_reference'],$tipStatus,$tipId]);
        mg_tip_event($pdo,$tipId,'payment_intent_created',$senderUserId,'payment_intent',(string)$intent['public_id'],'tip-payment-intent:'.$public,['provider_key'=>$provider,'provider_payment_id'=>$intent['provider_intent_reference'],'status'=>$intent['status']]);
    }
    $stmt->execute([$tipId]);$tip=$stmt->fetch(PDO::FETCH_ASSOC);
    $tip=mg_tip_payment_payload($pdo,$tip);
    mg_event('tip.'.($funding==='wallet'?'posted':'pending'),['tip_id'=>$public,'recipient_user_id'=>$target['recipient_user_id'],'target_type'=>$type,'target_reference'=>$target['target_reference'],'amount_cents'=>$amount,'currency'=>$currency],$senderUserId);
    return $tip+['duplicate'=>false];
}

function mg_tip_finalize_stripe(PDO $pdo,string $providerPaymentId,string $status,?string $failure=null): array
{
    $stmt=$pdo->prepare("SELECT * FROM tips WHERE provider_payment_id=? AND funding_type='stripe' LIMIT 1 FOR UPDATE");$stmt->execute([$providerPaymentId]);$tip=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$tip)throw new RuntimeException('Tip payment not found.');
    if($tip['status']==='posted')return $tip+['duplicate'=>true];
    if($status==='failed'){
        $pdo->prepare("UPDATE tips SET status='failed',failure_message=?,failed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$failure,(int)$tip['id']]);
        mg_tip_event($pdo,(int)$tip['id'],'failed',null,'payment_provider',$providerPaymentId,'tip-payment-failed:'.$providerPaymentId,['failure_message'=>$failure]);
        return $tip+['status'=>'failed','duplicate'=>false];
    }
    if(!in_array((string)$tip['status'],['pending','requires_action','processing','failed'],true))throw new RuntimeException('Tip is not awaiting payment.');
    $pdo->prepare("UPDATE tips SET status='funded',failure_message=NULL,funded_at=NOW(),failed_at=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$tip['id']]);$tip['status']='funded';
    mg_tip_event($pdo,(int)$tip['id'],'funded',null,'payment_provider',$providerPaymentId,'tip-payment-funded:'.$providerPaymentId,[]);
    $group=mg_tip_post_ledger($pdo,$tip);
    $pdo->prepare("UPDATE tips SET status='posted',ledger_group_id=?,posted_at=NOW(),settled_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$group['id'],(int)$tip['id']]);
    mg_tip_event($pdo,(int)$tip['id'],'posted',null,'ledger_group',(string)$group['public_id'],'tip-payment-posted:'.$providerPaymentId,['ledger_group_id'=>$group['public_id']]);
    mg_event('tip.posted',['tip_id'=>$tip['public_id'],'recipient_user_id'=>(int)$tip['recipient_user_id'],'amount_cents'=>(int)$tip['amount_cents'],'currency'=>$tip['currency']],(int)$tip['sender_user_id']);
    return $tip+['status'=>'posted','ledger_group_id'=>$group['id'],'settled_at'=>gmdate('Y-m-d H:i:s'),'duplicate'=>false];
}

function mg_tip_process_payment_event(PDO $pdo,string $provider,array $event): array
{
    $type=trim((string)($event['type']??''));
    $object=is_array($event['data']['object']??null)?$event['data']['object']:(is_array($event['data']??null)?$event['data']:[]);
    $providerPaymentId=trim((string)($object['id']??$object['payment_intent']??$object['payment_id']??''));
    $metadata=is_array($object['metadata']??null)?$object['metadata']:[];
    $tipPublicId=trim((string)($metadata['tip_id']??''));
    $amount=(int)($object['amount_received']??$object['amount']??0);
    $currency=strtoupper(trim((string)($object['currency']??'')));
    if($provider===''||$type===''||$providerPaymentId===''||$tipPublicId===''||$amount<1||!preg_match('/^[A-Z]{3}$/',$currency)){
        throw new InvalidArgumentException('Tip payment event is missing canonical provider fields.');
    }
    $stmt=$pdo->prepare('SELECT t.*,pi.provider_key intent_provider,pi.source_type intent_source_type,pi.source_reference intent_source_reference,pi.amount_cents intent_amount_cents,pi.currency intent_currency,pi.status intent_status FROM tips t INNER JOIN payment_intents pi ON pi.id=t.payment_intent_id WHERE t.provider_payment_id=? AND t.public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$providerPaymentId,$tipPublicId]);$tip=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$tip)throw new RuntimeException('Tip payment event does not match a known tip.');
    if((string)$tip['intent_provider']!==$provider||(string)$tip['provider_key']!==$provider)throw new RuntimeException('Tip payment provider does not match.');
    if((string)$tip['intent_source_type']!=='tip'||(string)$tip['intent_source_reference']!==(string)$tip['public_id'])throw new RuntimeException('Tip payment intent source does not match.');
    if((int)$tip['intent_amount_cents']!==$amount||(int)$tip['amount_cents']!==$amount||(string)$tip['intent_currency']!==$currency||(string)$tip['currency']!==$currency){
        throw new RuntimeException('Tip payment amount or currency does not match.');
    }
    $successTypes=['payment_intent.succeeded','charge.succeeded','tip.payment_succeeded'];
    $failureTypes=['payment_intent.payment_failed','charge.failed','tip.payment_failed'];
    $processingTypes=['payment_intent.processing','tip.payment_processing'];
    $actionTypes=['payment_intent.requires_action','tip.payment_requires_action'];
    if(in_array($type,$successTypes,true)){
        if((string)$tip['status']==='posted')return $tip+['duplicate'=>true,'ignored'=>false];
        $pdo->prepare("UPDATE payment_intents SET status='succeeded',captured_at=COALESCE(captured_at,NOW()),failure_code=NULL,failure_message=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$tip['payment_intent_id']]);
        mg_payment_record_intent_transaction($pdo,(int)$tip['payment_intent_id'],$providerPaymentId,$amount,$currency,'sale');
        return mg_tip_finalize_stripe($pdo,$providerPaymentId,'succeeded');
    }
    if(in_array($type,$failureTypes,true)){
        if((string)$tip['status']==='posted')return $tip+['duplicate'=>true,'ignored'=>true];
        $failure=(string)($object['last_payment_error']['message']??$object['failure_message']??'Provider reported payment failure.');
        $pdo->prepare("UPDATE payment_intents SET status='failed',failure_code=?,failure_message=?,updated_at=NOW() WHERE id=?")
            ->execute([(string)($object['last_payment_error']['code']??$object['failure_code']??'payment_failed'),mb_substr($failure,0,500),(int)$tip['payment_intent_id']]);
        return mg_tip_finalize_stripe($pdo,$providerPaymentId,'failed',$failure);
    }
    if(in_array($type,$processingTypes,true)||in_array($type,$actionTypes,true)){
        if((string)$tip['status']==='posted')return $tip+['duplicate'=>true,'ignored'=>true];
        $state=in_array($type,$processingTypes,true)?'processing':'requires_action';
        $pdo->prepare('UPDATE payment_intents SET status=?,updated_at=NOW() WHERE id=?')->execute([$state,(int)$tip['payment_intent_id']]);
        $pdo->prepare('UPDATE tips SET status=?,updated_at=NOW() WHERE id=?')->execute([$state,(int)$tip['id']]);
        mg_tip_event($pdo,(int)$tip['id'],$state,null,'payment_provider',$providerPaymentId,'tip-payment-'.$state.':'.$providerPaymentId,[]);
        return $tip+['status'=>$state,'duplicate'=>false,'ignored'=>false];
    }
    return $tip+['ignored'=>true,'duplicate'=>false];
}

function mg_tip_reverse(PDO $pdo,int $actorUserId,string $tipPublicId,string $idempotencyKey,string $reason): array
{
    $tipPublicId=trim($tipPublicId);
    $idempotencyKey=trim($idempotencyKey);
    $reason=trim($reason);
    if($tipPublicId===''||$idempotencyKey===''||$reason==='')throw new InvalidArgumentException('Tip, idempotency key, and reason are required.');

    $stmt=$pdo->prepare("SELECT t.*,g.public_id ledger_public_id FROM tips t LEFT JOIN ledger_transaction_groups g ON g.id=t.ledger_group_id WHERE t.public_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$tipPublicId]);
    $tip=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$tip)throw new RuntimeException('Tip not found.');

    if((string)$tip['status']==='reversed'){
        $existing=$pdo->prepare('SELECT tr.*,g.public_id reversal_group_public_id FROM tip_reversals tr LEFT JOIN ledger_transaction_groups g ON g.id=tr.ledger_group_id WHERE tr.tip_id=? LIMIT 1');
        $existing->execute([(int)$tip['id']]);
        $reversal=$existing->fetch(PDO::FETCH_ASSOC);
        if(!$reversal)throw new RuntimeException('Tip reversal record is missing.');
        if(!hash_equals((string)$reversal['idempotency_key'],$idempotencyKey)||(string)$reversal['reason']!==$reason){
            throw new RuntimeException('Tip is already reversed by a different request.');
        }
        return ['tip'=>$tip,'reversal'=>$reversal,'reversal_group_id'=>$reversal['reversal_group_public_id']??null,'duplicate'=>true];
    }

    if((string)$tip['status']!=='posted'||empty($tip['ledger_public_id']))throw new RuntimeException('Only posted tips can be reversed.');
    $group=mg_ledger_reverse($pdo,(string)$tip['ledger_public_id'],'tip-reversal:'.$idempotencyKey,$reason,$actorUserId);
    $public=mg_public_uuid();
    $metadata=['tip_public_id'=>$tipPublicId,'original_ledger_group_id'=>$tip['ledger_public_id'],'reversal_ledger_group_id'=>$group['public_id']??null];
    $pdo->prepare('INSERT INTO tip_reversals (public_id,tip_id,amount_cents,currency,reason,idempotency_key,reversed_by_user_id,ledger_group_id,metadata_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([$public,(int)$tip['id'],(int)$tip['amount_cents'],(string)$tip['currency'],$reason,$idempotencyKey,$actorUserId,(int)$group['id'],json_encode($metadata,JSON_THROW_ON_ERROR)]);
    $reversalId=(int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE tips SET status='reversed',reversal_group_id=?,reversed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$group['id'],(int)$tip['id']]);
    mg_tip_event($pdo,(int)$tip['id'],'reversed',$actorUserId,'ledger_group',(string)($group['public_id']??''),'tip-reversed:'.$idempotencyKey,['reversal_id'=>$public,'reason'=>$reason,'amount_cents'=>(int)$tip['amount_cents'],'currency'=>(string)$tip['currency']]);
    $reversal=['id'=>$reversalId,'public_id'=>$public,'tip_id'=>(int)$tip['id'],'amount_cents'=>(int)$tip['amount_cents'],'currency'=>(string)$tip['currency'],'reason'=>$reason,'idempotency_key'=>$idempotencyKey,'ledger_group_id'=>(int)$group['id']];
    return ['tip'=>$tip,'reversal'=>$reversal,'reversal_group_id'=>$group['public_id']??null,'duplicate'=>false];
}
