<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$paths=[
    'page'=>$root.'/merchant-payments.php',
    'view'=>$root.'/includes/merchant-payments-view.php',
    'controller'=>$root.'/assets/js/merchant-stripe-connect.js',
    'style'=>$root.'/assets/css/merchant-stripe-connect.css',
    'connect'=>$root.'/api/payments/_connect.php',
    'oauth'=>$root.'/api/payments/_stripe_connect_oauth.php',
    'callback'=>$root.'/api/merchant/stripe-connect-callback.php',
    'action'=>$root.'/api/merchant/payment-connect.php',
    'account'=>$root.'/api/merchant/payment-account.php',
    'methods'=>$root.'/api/merchant/payment-methods.php',
    'connectWebhook'=>$root.'/api/payments/_connect_webhook.php',
    'webhook'=>$root.'/api/payments/webhook.php',
    'migration'=>$root.'/database/stage_v1g_stripe_connect_oauth.sql',
    'docs'=>$root.'/docs/payments/stripe-connect-oauth-onboarding.md',
];

$source=[];
foreach($paths as $key=>$path){
    if(!is_file($path)){
        fwrite(STDERR,"Missing required file: {$path}\n");
        exit(1);
    }
    $source[$key]=(string)file_get_contents($path);
}

$checks=[
    'merchant payments loads the dedicated Stripe Connect UI assets' =>
        str_contains($source['page'],'merchant-stripe-connect.css')
        && str_contains($source['page'],'merchant-stripe-connect.js'),
    'merchant UI offers one official connect-or-create flow and readiness controls' =>
        str_contains($source['view'],'Connect or create Stripe account')
        && str_contains($source['view'],'data-stripe-connect-start')
        && str_contains($source['view'],'data-stripe-connect-sync')
        && str_contains($source['view'],'data-stripe-connect-disconnect')
        && str_contains($source['view'],'data-stripe-requirements-list'),
    'browser controller starts OAuth only through the server and accepts only Stripe authorization URLs' =>
        str_contains($source['controller'],"Microgifter.post('/api/merchant/payment-connect.php'")
        && str_contains($source['controller'],"postAction('oauth_start'")
        && str_contains($source['controller'],"https://connect.stripe.com/oauth/authorize?")
        && str_contains($source['controller'],'window.location.assign(url)')
        && !str_contains($source['controller'],'client_id='),
    'official Stripe authorize token and deauthorize endpoints are used' =>
        str_contains($source['connect'],"https://connect.stripe.com/oauth/authorize?")
        && str_contains($source['oauth'],"https://connect.stripe.com/oauth/")
        && str_contains($source['oauth'],"'token'")
        && str_contains($source['oauth'],"'deauthorize'"),
    'OAuth is bound to a mode-matching standard secret key and Connect client ID' =>
        str_contains($source['connect'],'mg_payment_secret_key_type($secret)!==\'secret\'')
        && str_contains($source['connect'],"str_starts_with(trim((string)\$config['connect_client_id']),'ca_')")
        && str_contains($source['oauth'],'A restricted rk_')
        && str_contains($source['account'],'standard_secret_configured'),
    'OAuth state is random hashed expiring single-use and merchant-bound' =>
        str_contains($source['connect'],'random_bytes(32)')
        && str_contains($source['connect'],"hash('sha256',\$state)")
        && str_contains($source['connect'],'time()+600')
        && str_contains($source['connect'],'consumed_at IS NULL')
        && str_contains($source['connect'],'merchant_user_id=?')
        && str_contains($source['callback'],'mg_payment_connect_oauth_consume_state'),
    'callback requires merchant management permission and never exposes provider credentials' =>
        str_contains($source['callback'],"mg_require_permission('merchant.payments.manage')")
        && str_contains($source['callback'],'mg_payment_connect_oauth_complete')
        && str_contains($source['callback'],"http_build_query(\$query")
        && !str_contains($source['callback'],'access_token')
        && !str_contains($source['callback'],'refresh_token'),
    'only Stripe account ID and readiness metadata are persisted' =>
        str_contains($source['connect'],'provider_account_reference')
        && str_contains($source['connect'],"connection_method='standard_oauth'")
        && str_contains($source['connect'],'This Stripe account is already connected to another Microgifter merchant account.')
        && !str_contains($source['connect'],'access_token')
        && !str_contains($source['connect'],'refresh_token')
        && !str_contains($source['migration'],'access_token')
        && !str_contains($source['migration'],'refresh_token'),
    'connection actions use manage permission while account status remains read-only' =>
        str_contains($source['action'],"mg_require_permission('merchant.payments.manage')")
        && str_contains($source['action'],"\$action==='oauth_start'")
        && str_contains($source['action'],"\$action==='sync'")
        && str_contains($source['action'],"\$action==='disconnect'")
        && str_contains($source['account'],"mg_require_permission('merchant.payments.view')"),
    'merchant payment methods report actual connected and ready state' =>
        str_contains($source['methods'],'mg_payment_connect_status')
        && str_contains($source['methods'],"'connected' => !empty(\$stripeAccount['connected'])")
        && str_contains($source['methods'],"'ready' => !empty(\$stripeAccount['ready'])")
        && str_contains($source['methods'],"'stripe_onboarding_connected' => !empty(\$stripeAccount['connected'])"),
    'signed account lifecycle webhooks update or disable the merchant connection' =>
        str_contains($source['webhook'],"'account.updated','account.application.deauthorized'")
        && str_contains($source['webhook'],'mg_payment_connect_process_webhook')
        && str_contains($source['connectWebhook'],"status='disabled'")
        && str_contains($source['connectWebhook'],'mg_payment_connect_update_readiness')
        && str_contains($source['connectWebhook'],'payment_webhook_events'),
    'migration creates OAuth state storage connection metadata and explicit permission' =>
        str_contains($source['migration'],'CREATE TABLE IF NOT EXISTS payment_connect_oauth_states')
        && str_contains($source['migration'],'state_hash CHAR(64)')
        && str_contains($source['migration'],'connection_method')
        && str_contains($source['migration'],'connected_at')
        && str_contains($source['migration'],"'merchant.payments.manage'")
        && str_contains($source['migration'],"'stage_v1g_stripe_connect_oauth'"),
    'legacy Account Link flow remains available for existing integrations' =>
        str_contains($source['connect'],'function mg_payment_connect_start')
        && str_contains($source['connect'],'mg_stripe_create_connected_account')
        && str_contains($source['connect'],'mg_stripe_create_account_link'),
    'deployment guide documents exact callback configuration and staging QA' =>
        str_contains($source['docs'],'/api/merchant/stripe-connect-callback.php')
        && str_contains($source['docs'],'stage_v1g_stripe_connect_oauth.sql')
        && str_contains($source['docs'],'Connect OAuth client ID')
        && str_contains($source['docs'],'Staging QA'),
];

$failed=[];
foreach($checks as $name=>$passed){
    echo ($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$passed)$failed[]=$name;
}

if($failed!==[]){
    fwrite(STDERR,"\nStripe Connect OAuth onboarding validation failed: ".implode('; ',$failed).PHP_EOL);
    exit(1);
}

echo "\nStripe Connect OAuth onboarding contract: 10/10.".PHP_EOL;
