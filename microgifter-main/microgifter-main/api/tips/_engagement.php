<?php
declare(strict_types=1);

require_once __DIR__ . '/_tips.php';

function mg_tip_engagement_input(PDO $pdo,array $input): array
{
    $type=trim((string)($input['target_type']??''));
    $reference=trim((string)($input['target_reference']??''));
    if(!in_array($type,['profile','creator'],true)||$reference==='')return $input;
    if(preg_match('/^[1-9][0-9]*$/',$reference)===1)return $input;

    $stmt=$pdo->prepare(
        "SELECT pp.user_id,pp.public_id,pp.profile_type
         FROM public_profiles pp INNER JOIN users u ON u.id=pp.user_id
         WHERE pp.public_id=? AND pp.status='active'
           AND pp.visibility IN ('public','unlisted') AND u.status='active'
         LIMIT 1"
    );
    $stmt->execute([$reference]);
    $profile=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$profile)throw new RuntimeException('Tip target is not available.');
    if($type==='creator'&&(string)$profile['profile_type']!=='creator')throw new RuntimeException('Tip target is not available.');

    $input['target_reference']=(string)(int)$profile['user_id'];
    $metadata=is_array($input['metadata']??null)?$input['metadata']:[];
    $metadata['public_profile_id']=(string)$profile['public_id'];
    $input['metadata']=$metadata;
    return $input;
}

function mg_tip_confirmation_key(array $input): string
{
    $key=trim((string)($input['idempotency_key']??''));
    if($key===''||strlen($key)>190||preg_match('/^[A-Za-z0-9._:-]{8,190}$/',$key)!==1){
        throw new InvalidArgumentException('A valid idempotency key is required.');
    }
    return $key;
}

function mg_tip_confirmation_payload(PDO $pdo,array $tip): array
{
    $intent=null;
    if(!empty($tip['payment_intent_id'])){
        $stmt=$pdo->prepare('SELECT public_id,provider_key,provider_intent_reference,status,amount_cents,currency FROM payment_intents WHERE id=? LIMIT 1');
        $stmt->execute([(int)$tip['payment_intent_id']]);
        $intent=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
    }
    $clientSecret=null;
    if($intent&&in_array((string)$tip['status'],['pending','requires_action','processing'],true)){
        $providerIntent=mg_payment_provider_retrieve_intent((string)$intent['provider_key'],(string)$intent['provider_intent_reference']);
        $clientSecret=$providerIntent['client_secret']??null;
    }
    return [
        'tip_id'=>(string)$tip['public_id'],
        'status'=>(string)$tip['status'],
        'amount_cents'=>(int)$tip['amount_cents'],
        'currency'=>(string)$tip['currency'],
        'payment_intent_id'=>$intent?(string)$intent['public_id']:null,
        'provider_payment_id'=>$intent?(string)$intent['provider_intent_reference']:null,
        'client_secret'=>$clientSecret,
        'posted'=>(string)$tip['status']==='posted',
    ];
}

function mg_tip_confirm_card(PDO $pdo,int $senderUserId,string $tipPublicId,string $idempotencyKey): array
{
    $tipPublicId=trim($tipPublicId);
    if($tipPublicId===''||preg_match('/^[a-f0-9-]{36}$/i',$tipPublicId)!==1)throw new InvalidArgumentException('Tip is required.');

    $stmt=$pdo->prepare(
        "SELECT t.*,pi.public_id intent_public_id,pi.provider_key intent_provider,
                pi.provider_intent_reference,pi.status intent_status,pi.amount_cents intent_amount,pi.currency intent_currency
         FROM tips t INNER JOIN payment_intents pi ON pi.id=t.payment_intent_id
         WHERE t.public_id=? AND t.sender_user_id=? AND t.funding_type='stripe'
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$tipPublicId,$senderUserId]);
    $tip=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$tip)throw new RuntimeException('Tip payment is not available.');
    if((int)$tip['amount_cents']!==(int)$tip['intent_amount']||(string)$tip['currency']!==(string)$tip['intent_currency']){
        throw new RuntimeException('Tip payment amount or currency does not match.');
    }

    $eventKey='tip-confirm-request:'.$idempotencyKey;
    $existing=$pdo->prepare('SELECT payload_json FROM tip_events WHERE tip_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([(int)$tip['id'],$eventKey]);
    if($raw=$existing->fetchColumn()){
        $payload=json_decode((string)$raw,true);
        if(!is_array($payload)||!hash_equals((string)($payload['tip_id']??''),$tipPublicId)){
            throw new RuntimeException('Idempotency key is already bound to a different confirmation request.');
        }
        $fresh=$pdo->prepare('SELECT * FROM tips WHERE id=? LIMIT 1');
        $fresh->execute([(int)$tip['id']]);
        return mg_tip_confirmation_payload($pdo,$fresh->fetch(PDO::FETCH_ASSOC))+['duplicate'=>true];
    }

    mg_tip_event($pdo,(int)$tip['id'],'confirmation_requested',$senderUserId,'tip',$tipPublicId,$eventKey,['tip_id'=>$tipPublicId]);
    if((string)$tip['status']==='posted')return mg_tip_confirmation_payload($pdo,$tip)+['duplicate'=>false];

    $providerIntent=mg_payment_provider_retrieve_intent((string)$tip['intent_provider'],(string)$tip['provider_intent_reference']);
    $providerStatus=mg_payment_normalize_intent_status((string)($providerIntent['status']??''));
    $intentStatus=mg_payment_normalize_intent_status((string)$tip['intent_status']);
    $effective=$providerStatus;
    if($intentStatus==='succeeded')$effective='succeeded';
    if($intentStatus==='failed'&&$providerStatus!=='succeeded')$effective='failed';

    if($effective==='succeeded'){
        $pdo->prepare("UPDATE payment_intents SET status='succeeded',captured_at=COALESCE(captured_at,NOW()),failure_code=NULL,failure_message=NULL,updated_at=NOW() WHERE id=?")
            ->execute([(int)$tip['payment_intent_id']]);
        mg_payment_record_intent_transaction($pdo,(int)$tip['payment_intent_id'],(string)$tip['provider_intent_reference'],(int)$tip['amount_cents'],(string)$tip['currency'],'sale');
        $tip=mg_tip_finalize_stripe($pdo,(string)$tip['provider_intent_reference'],'succeeded');
    }elseif(in_array($effective,['failed','cancelled'],true)){
        $tip=mg_tip_finalize_stripe($pdo,(string)$tip['provider_intent_reference'],'failed','Payment confirmation failed.');
    }else{
        $state=$effective==='processing'?'processing':'requires_action';
        $pdo->prepare('UPDATE payment_intents SET status=?,updated_at=NOW() WHERE id=?')->execute([$state,(int)$tip['payment_intent_id']]);
        $pdo->prepare('UPDATE tips SET status=?,updated_at=NOW() WHERE id=?')->execute([$state,(int)$tip['id']]);
        $tip['status']=$state;
        mg_tip_event($pdo,(int)$tip['id'],$state,$senderUserId,'payment_intent',(string)$tip['intent_public_id'],'tip-confirm-state:'.$idempotencyKey,['status'=>$state]);
    }

    $fresh=$pdo->prepare('SELECT * FROM tips WHERE id=? LIMIT 1');
    $fresh->execute([(int)$tip['id']]);
    return mg_tip_confirmation_payload($pdo,$fresh->fetch(PDO::FETCH_ASSOC))+['duplicate'=>false];
}
