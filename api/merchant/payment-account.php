<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/_connect.php';

mg_require_method('GET');
$user=mg_require_permission('merchant.payments.view');
$pdo=mg_db();

try{
    $status=mg_payment_connect_status($pdo,(int)$user['id'],false);
    $platform=mg_payment_config_public_status($pdo,'stripe',mg_payment_mode());
    $appUrl=trim((string)(getenv('MG_APP_URL')?:''));
    $oauthReady=!empty($platform['enabled'])
        && !empty($platform['secret_configured'])
        && !empty($platform['connect_client_configured'])
        && $appUrl!==''
        && (mg_payment_mode()==='test'||str_starts_with($appUrl,'https://'))
        && function_exists('curl_init');
    $blockers=[];
    if(empty($platform['enabled']))$blockers[]='Stripe is disabled for '.mg_payment_mode().' mode.';
    if(empty($platform['secret_configured']))$blockers[]='Stripe API key is missing.';
    if(empty($platform['connect_client_configured']))$blockers[]='Stripe Connect client ID is missing.';
    if($appUrl==='')$blockers[]='MG_APP_URL is missing.';
    elseif(mg_payment_mode()==='live'&&!str_starts_with($appUrl,'https://'))$blockers[]='Live Stripe Connect requires an HTTPS MG_APP_URL.';
    if(!function_exists('curl_init'))$blockers[]='PHP cURL is unavailable.';

    mg_ok([
        'account'=>$status,
        'platform'=>[
            'provider_key'=>'stripe',
            'mode'=>$platform['mode'],
            'enabled'=>$platform['enabled'],
            'secret_configured'=>$platform['secret_configured'],
            'secret_key_type'=>$platform['secret_key_type'],
            'webhook_configured'=>$platform['webhook_configured'],
            'connect_client_configured'=>$platform['connect_client_configured'],
            'application_url_configured'=>$appUrl!=='',
            'oauth_ready'=>$oauthReady,
            'oauth_blockers'=>$blockers,
            'platform_fee_bps'=>$platform['platform_fee_bps'],
            'callback_url'=>$appUrl!==''?rtrim($appUrl,'/').'/api/merchant/stripe-connect-callback.php':'/api/merchant/stripe-connect-callback.php',
        ],
    ]);
}catch(Throwable $error){
    mg_fail('Unable to load payment account status.',500);
}
