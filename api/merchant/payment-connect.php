<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/payments/_connect.php';
mg_require_method('POST');
$user=mg_require_permission('merchant.payments.manage');
$input=mg_input();
mg_require_csrf_for_write($input);
$action=trim((string)($input['action']??'onboard'));
$pdo=mg_db();
try{
    $pdo->beginTransaction();
    if($action==='onboard'){
        $result=mg_payment_connect_start($pdo,(int)$user['id']);
        $message='Stripe Connect onboarding link created.';
    }elseif($action==='sync'){
        $result=mg_payment_connect_status($pdo,(int)$user['id'],true);
        $message=$result['ready']?'Stripe Connect account is ready.':'Stripe Connect account status refreshed.';
    }else{
        throw new InvalidArgumentException('Unknown payment-account action.');
    }
    $pdo->commit();
    mg_audit('merchant.payment_account_'.$action,'payment_provider_account',[
        'provider'=>'stripe','mode'=>mg_payment_mode(),
        'account_id'=>$result['account_id']??null,'ready'=>$result['ready']??false,
    ],(int)$user['id']);
    mg_ok(['account'=>$result],$message,$action==='onboard'?201:200);
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail('Unable to update the Stripe Connect account.',500);
}
