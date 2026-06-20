<?php
declare(strict_types=1);

require_once __DIR__ . '/_payments.php';

function mg_payment_readiness(PDO $pdo,string $provider='stripe',?string $mode=null): array
{
    $mode=$mode??mg_payment_mode();
    $status=mg_payment_config_public_status($pdo,$provider,$mode);
    $config=mg_payment_platform_config($pdo,$provider,$mode);
    $appUrl=rtrim(trim((string)(getenv('MG_APP_URL')?:'')),'/');
    $runtimeProvider=mg_payment_provider_key();
    $prefix=$mode==='live'?'live':'test';
    $checks=[
        'runtime_provider'=>[
            'ok'=>$runtimeProvider===$provider,
            'label'=>'Runtime provider',
            'detail'=>$runtimeProvider===$provider?'MG_PAYMENT_PROVIDER selects Stripe.':'Set MG_PAYMENT_PROVIDER=stripe before enabling customer payments.',
        ],
        'provider_enabled'=>[
            'ok'=>(bool)$status['enabled'],
            'label'=>'Provider enabled',
            'detail'=>$status['enabled']?'Enabled for '.$mode.' mode.':'Enable Stripe for '.$mode.' mode.',
        ],
        'runtime_mode'=>[
            'ok'=>mg_payment_mode()===$mode,
            'label'=>'Runtime mode',
            'detail'=>mg_payment_mode()===$mode?'MG_PAYMENT_MODE selects '.$mode.'.':'Set MG_PAYMENT_MODE='.$mode.' before using this configuration.',
        ],
        'publishable_key'=>[
            'ok'=>str_starts_with((string)$config['publishable_key'],'pk_'.$prefix.'_'),
            'label'=>'Publishable key',
            'detail'=>$status['publishable_configured']?'Configured with the expected mode prefix.':'Missing Stripe publishable key.',
        ],
        'secret_key'=>[
            'ok'=>str_starts_with((string)$config['secret_key'],'sk_'.$prefix.'_')||mg_stripe_stub_enabled(),
            'label'=>'Secret key',
            'detail'=>$status['secret_configured']||mg_stripe_stub_enabled()?'Configured with the expected mode prefix.':'Missing Stripe secret key.',
        ],
        'webhook_secret'=>[
            'ok'=>str_starts_with((string)$config['webhook_secret'],'whsec_')||mg_stripe_stub_enabled(),
            'label'=>'Webhook secret',
            'detail'=>$status['webhook_configured']||mg_stripe_stub_enabled()?'Configured.':'Missing Stripe webhook signing secret.',
        ],
        'application_url'=>[
            'ok'=>$appUrl!==''&&($mode==='test'||str_starts_with($appUrl,'https://')),
            'label'=>'Application URL',
            'detail'=>$appUrl!==''?$appUrl:'MG_APP_URL is missing.',
        ],
        'php_curl'=>[
            'ok'=>function_exists('curl_init')||mg_stripe_stub_enabled(),
            'label'=>'PHP cURL',
            'detail'=>function_exists('curl_init')?'Available.':'Required for Stripe API calls.',
        ],
        'credential_encryption'=>[
            'ok'=>$status['credential_source']==='environment'||$status['database_encryption_ready'],
            'label'=>'Credential encryption',
            'detail'=>$status['credential_source']==='environment'?'Secrets are supplied by the server environment.':($status['database_encryption_ready']?'Database secrets are encrypted.':'MG_PAYMENT_CREDENTIAL_KEY is required for database secrets.'),
        ],
        'platform_fee'=>[
            'ok'=>(int)$status['platform_fee_bps']>=0&&(int)$status['platform_fee_bps']<=10000,
            'label'=>'Platform fee policy',
            'detail'=>number_format((int)$status['platform_fee_bps']/100,2).'% plus '.(int)$status['fixed_fee_cents'].' cents.',
        ],
    ];
    $accounts=$pdo->prepare("SELECT COUNT(*) total,SUM(status='active' AND charges_enabled=1 AND payouts_enabled=1) ready FROM payment_provider_accounts WHERE provider_key=? AND mode=?");
    $accounts->execute([$provider,$mode]);
    $accountCounts=$accounts->fetch(PDO::FETCH_ASSOC)?:['total'=>0,'ready'=>0];
    $allReady=!in_array(false,array_map(static fn(array $check): bool=>(bool)$check['ok'],$checks),true);
    return [
        'provider'=>$status,
        'checks'=>$checks,
        'ready'=>$allReady,
        'connected_accounts'=>[
            'total'=>(int)($accountCounts['total']??0),
            'ready'=>(int)($accountCounts['ready']??0),
        ],
        'webhook_url'=>$appUrl!==''?$appUrl.'/api/payments/webhook.php?provider=stripe':'/api/payments/webhook.php?provider=stripe',
    ];
}
