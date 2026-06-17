<?php
declare(strict_types=1);
require_once __DIR__ . '/_engagement.php';
require_once __DIR__ . '/_notifications.php';
mg_require_method('POST');
$user=mg_require_permission('tips.create');
$input=mg_input();
mg_require_csrf_for_write($input);
$actorId=(int)$user['id'];
mg_rate_limit('tips.create','user:'.$actorId,40,60);
$pdo=mg_db();
$pdo->beginTransaction();
try{
    $input=mg_tip_engagement_input($pdo,$input);
    $tip=mg_tip_create($pdo,$actorId,$input);
    if((string)$tip['status']==='posted' && empty($tip['duplicate'])){
        $tip['alert_id']=mg_tip_notify_recipient($pdo,$tip);
    }
    $pdo->commit();
    mg_audit('tip.created','tip',[
        'tip_id'=>$tip['public_id'],
        'status'=>$tip['status'],
        'target_type'=>$tip['target_type'],
        'target_reference'=>$tip['target_reference'],
        'amount_cents'=>(int)$tip['amount_cents'],
        'funding_type'=>$tip['funding_type'],
        'payment_intent_id'=>$tip['payment_intent_public_id']??null,
        'duplicate'=>(bool)$tip['duplicate'],
    ],$actorId);
    mg_ok([
        'tip_id'=>$tip['public_id'],
        'status'=>$tip['status'],
        'payment_intent_id'=>$tip['payment_intent_public_id']??null,
        'provider_payment_id'=>$tip['provider_payment_id']??null,
        'client_secret'=>$tip['client_secret']??null,
        'amount_cents'=>(int)$tip['amount_cents'],
        'fee_cents'=>(int)$tip['fee_cents'],
        'net_cents'=>(int)$tip['net_cents'],
        'currency'=>$tip['currency'],
        'alert_id'=>$tip['alert_id']??null,
        'duplicate'=>(bool)$tip['duplicate'],
    ],$tip['duplicate']?'Existing tip returned.':'Tip created.',$tip['duplicate']?200:201);
}catch(InvalidArgumentException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),422);
}catch(RuntimeException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),409);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','tip.create_failed','Tip creation failed.',['exception_class'=>$e::class],$actorId);
    mg_fail('Unable to create tip.',500);
}
