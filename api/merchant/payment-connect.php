<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/payments/_connect.php';
mg_require_method('POST');
$user=mg_require_permission('merchant.payments.manage');
$input=mg_input();
mg_require_csrf_for_write($input);
$action=trim((string)($input['action']??'oauth_start'));
$pdo=mg_db();
$merchantId=(int)$user['id'];

try{
    if($action==='oauth_start'){
        $pdo->beginTransaction();
        $result=mg_payment_connect_oauth_start($pdo,$merchantId,'/merchant-payments.php');
        $pdo->commit();
        $message='Stripe connection is ready to begin.';
        $status=201;
    }elseif($action==='sync'){
        $pdo->beginTransaction();
        $result=mg_payment_connect_status($pdo,$merchantId,true);
        $pdo->commit();
        $message=$result['ready']?'Stripe account is ready for payments.':'Stripe account status refreshed.';
        $status=200;
    }elseif($action==='disconnect'){
        $pdo->beginTransaction();
        $result=mg_payment_connect_oauth_disconnect($pdo,$merchantId);
        $pdo->commit();
        $message='Stripe account disconnected from Microgifter.';
        $status=200;
    }elseif($action==='onboard'){
        // Legacy Express Account Link action retained for backwards compatibility only.
        $pdo->beginTransaction();
        $result=mg_payment_connect_start($pdo,$merchantId);
        $pdo->commit();
        $message='Stripe Connect onboarding link created.';
        $status=201;
    }else{
        throw new InvalidArgumentException('Unknown payment-account action.');
    }

    mg_audit('merchant.payment_account_'.$action,'payment_provider_account',[
        'provider'=>'stripe',
        'mode'=>mg_payment_mode(),
        'account_id'=>$result['account_id']??null,
        'connection_method'=>$result['connection_method']??($result['flow']??null),
        'ready'=>$result['ready']??false,
    ],$merchantId);
    mg_ok(['account'=>$result],$message,$status);
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(MgStripeProviderException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('warning','merchant.stripe_connect_provider_error','Stripe Connect provider request failed.',[
        'merchant_user_id'=>$merchantId,
        'action'=>$action,
        'stripe_code'=>$error->stripeCode,
    ],$merchantId);
    mg_fail($error->getMessage(),$error->httpStatus);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','merchant.stripe_connect_failed','Unable to update Stripe Connect account.',[
        'merchant_user_id'=>$merchantId,'action'=>$action,'exception_class'=>$error::class,
    ],$merchantId);
    mg_fail('Unable to update the Stripe Connect account.',500);
}
