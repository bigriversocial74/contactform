<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/payments/_connect.php';

function mg_stripe_connect_callback_redirect(string $result,string $detail=''): never
{
    $query=['stripe_connect'=>$result];
    if($detail!=='')$query['detail']=$detail;
    header('Cache-Control: no-store, private, max-age=0');
    header('Location: /merchant-payments.php?'.http_build_query($query,'','&',PHP_QUERY_RFC3986).'#payments-methods',true,303);
    exit;
}

$user=mg_current_user();
if(!$user){
    $return=(string)($_SERVER['REQUEST_URI']??'/merchant-payments.php');
    header('Location: /signin.php?return='.rawurlencode($return),true,302);
    exit;
}
$user=mg_require_permission('merchant.payments.manage');
$merchantId=(int)$user['id'];
$state=trim((string)($_GET['state']??''));
$errorCode=trim((string)($_GET['error']??''));
$code=trim((string)($_GET['code']??''));
$scope=trim((string)($_GET['scope']??'read_write'))?:'read_write';
$pdo=mg_db();

if($state==='')mg_stripe_connect_callback_redirect('error','missing_state');

try{
    $pdo->beginTransaction();
    mg_payment_connect_oauth_consume_state($pdo,$merchantId,$state);
    $pdo->commit();

    if($errorCode!==''){
        mg_audit('merchant.stripe_connect_denied','payment_provider_account',[
            'provider'=>'stripe','mode'=>mg_payment_mode(),'error_code'=>$errorCode,
        ],$merchantId);
        mg_stripe_connect_callback_redirect($errorCode==='access_denied'?'denied':'error','stripe_'.$errorCode);
    }
    if($code==='')mg_stripe_connect_callback_redirect('error','missing_code');

    $pdo->beginTransaction();
    $account=mg_payment_connect_oauth_complete($pdo,$merchantId,$code,$scope);
    $pdo->commit();

    mg_audit('merchant.stripe_connect_completed','payment_provider_account',[
        'provider'=>'stripe',
        'mode'=>mg_payment_mode(),
        'account_id'=>$account['account_id']??null,
        'connection_method'=>$account['connection_method']??null,
        'ready'=>$account['ready']??false,
    ],$merchantId);
    mg_stripe_connect_callback_redirect(!empty($account['ready'])?'ready':'connected');
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('warning','merchant.stripe_connect_callback_invalid','Stripe Connect callback was rejected.',[
        'merchant_user_id'=>$merchantId,'reason'=>$error->getMessage(),
    ],$merchantId);
    mg_stripe_connect_callback_redirect('error','invalid_or_expired');
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','merchant.stripe_connect_callback_failed','Stripe Connect callback failed.',[
        'merchant_user_id'=>$merchantId,'exception_class'=>$error::class,
    ],$merchantId);
    mg_stripe_connect_callback_redirect('error','connection_failed');
}
