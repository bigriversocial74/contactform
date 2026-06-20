<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/_connect.php';

mg_require_method('GET');
$user=mg_require_permission('merchant.payments.view');
$pdo=mg_db();

try{
    $status=mg_payment_connect_status($pdo,(int)$user['id'],false);
    $platform=mg_payment_config_public_status($pdo,'stripe',mg_payment_mode());
    mg_ok([
        'account'=>$status,
        'platform'=>[
            'provider_key'=>'stripe',
            'mode'=>$platform['mode'],
            'enabled'=>$platform['enabled'],
            'secret_configured'=>$platform['secret_configured'],
            'webhook_configured'=>$platform['webhook_configured'],
            'platform_fee_bps'=>$platform['platform_fee_bps'],
        ],
    ]);
}catch(Throwable $error){
    mg_fail('Unable to load payment account status.',500);
}
