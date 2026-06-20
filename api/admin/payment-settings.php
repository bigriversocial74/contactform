<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/_readiness.php';

$user=mg_require_permission('admin.settings.manage');
$pdo=mg_db();

if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    $mode=(string)($_GET['mode']??mg_payment_mode())==='live'?'live':'test';
    mg_ok(mg_payment_readiness($pdo,'stripe',$mode));
}

mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
try{
    $pdo->beginTransaction();
    $saved=mg_payment_save_platform_config($pdo,$input+(array)['provider_key'=>'stripe'],(int)$user['id']);
    $mode=(string)$saved['mode'];
    $pdo->commit();
    $readiness=mg_payment_readiness($pdo,'stripe',$mode);
    mg_audit('admin.payment_settings_updated','payment_platform_credentials',[
        'provider'=>'stripe',
        'mode'=>$mode,
        'enabled'=>(bool)$saved['enabled'],
        'platform_fee_bps'=>(int)$saved['platform_fee_bps'],
        'credential_source'=>$saved['credential_source'],
        'ready'=>$readiness['ready'],
    ],(int)$user['id']);
    mg_ok($readiness,'Stripe payment settings saved.');
}catch(InvalidArgumentException|MgPaymentCredentialException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','admin.payment_settings_failed','Payment settings update failed.',[
        'exception_class'=>$error::class,
    ],(int)$user['id']);
    mg_fail('Unable to save payment settings.',500);
}
