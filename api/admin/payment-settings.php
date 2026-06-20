<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/_readiness.php';

$user=mg_require_permission('admin.settings.manage');
$pdo=mg_db();

function mg_admin_payment_settings_payload(PDO $pdo,string $mode): array
{
    $payload=mg_payment_readiness($pdo,'stripe',$mode);
    $config=mg_payment_platform_config($pdo,'stripe',$mode);
    $payload['provider']['publishable_key']=(string)$config['publishable_key'];
    $payload['provider']['connect_client_id']=(string)$config['connect_client_id'];
    return $payload;
}

if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    $mode=(string)($_GET['mode']??mg_payment_mode())==='live'?'live':'test';
    mg_ok(mg_admin_payment_settings_payload($pdo,$mode));
}

mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
try{
    $pdo->beginTransaction();
    $mode=(string)($input['mode']??'test')==='live'?'live':'test';
    $existing=mg_payment_platform_credential_row($pdo,'stripe',$mode,true);
    if(trim((string)($input['publishable_key']??''))===''&&$existing){
        $input['publishable_key']=(string)($existing['publishable_key']??'');
    }
    if(trim((string)($input['connect_client_id']??''))===''&&$existing){
        $input['connect_client_id']=(string)($existing['connect_client_id']??'');
    }
    $input['provider_key']='stripe';
    $saved=mg_payment_save_platform_config($pdo,$input,(int)$user['id']);
    $pdo->commit();
    $readiness=mg_admin_payment_settings_payload($pdo,(string)$saved['mode']);
    mg_audit('admin.payment_settings_updated','payment_platform_credentials',[
        'provider'=>'stripe',
        'mode'=>$saved['mode'],
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
    mg_fail('Unable to save payment settings.',500);
}
