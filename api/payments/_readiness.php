<?php
declare(strict_types=1);

require_once __DIR__ . '/_payments.php';

function mg_payment_selling_merchant_readiness(PDO $pdo,string $provider='stripe',?string $mode=null): array
{
    $mode=$mode??mg_payment_mode();
    $stmt=$pdo->prepare(
        "SELECT cp.merchant_user_id,
                MAX(CASE WHEN ppa.id IS NOT NULL
                          AND ppa.status='active'
                          AND ppa.charges_enabled=1
                          AND ppa.payouts_enabled=1
                         THEN 1 ELSE 0 END) AS ready
         FROM catalog_products cp
         LEFT JOIN payment_provider_accounts ppa
           ON ppa.merchant_user_id=cp.merchant_user_id
          AND ppa.provider_key=?
          AND ppa.mode=?
         WHERE cp.status='published'
         GROUP BY cp.merchant_user_id
         ORDER BY cp.merchant_user_id"
    );
    $stmt->execute([$provider,$mode]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $blocked=[];
    $ready=0;
    foreach($rows as $row){
        if((int)($row['ready']??0)===1)$ready++;
        else $blocked[]=(int)$row['merchant_user_id'];
    }
    return [
        'total'=>count($rows),
        'ready'=>$ready,
        'blocked'=>count($blocked),
        'blocked_merchant_user_ids'=>$blocked,
    ];
}

function mg_payment_readiness(PDO $pdo,string $provider='stripe',?string $mode=null): array
{
    $mode=$mode??mg_payment_mode();
    $status=mg_payment_config_public_status($pdo,$provider,$mode);
    $config=mg_payment_platform_config($pdo,$provider,$mode);
    $appUrl=rtrim(trim((string)(getenv('MG_APP_URL')?:'')),'/');
    $runtimeProvider=mg_payment_provider_key();
    $prefix=$mode==='live'?'live':'test';
    $publishable=(string)$config['publishable_key'];
    $secret=(string)$config['secret_key'];
    $webhookSecret=(string)$config['webhook_secret'];
    $publishablePrefix='pk_'.$prefix.'_';
    $secretPrefix='sk_'.$prefix.'_';
    $publishableOk=str_starts_with($publishable,$publishablePrefix);
    $secretOk=str_starts_with($secret,$secretPrefix)||mg_stripe_stub_enabled();
    $webhookOk=str_starts_with($webhookSecret,'whsec_')||mg_stripe_stub_enabled();
    $publishableDetail=!$status['publishable_configured']
        ? 'Missing Stripe publishable key.'
        : ($publishableOk
            ? 'Configured with '.$publishablePrefix.' for '.$mode.' mode.'
            : 'Configured key does not match '.$mode.' mode. Expected prefix '.$publishablePrefix.'.');
    $secretDetail=!$status['secret_configured']&&!mg_stripe_stub_enabled()
        ? 'Missing Stripe secret key.'
        : ($secretOk
            ? (mg_stripe_stub_enabled()?'Stripe stub mode is enabled.':'Configured with '.$secretPrefix.' for '.$mode.' mode.')
            : 'Configured secret key does not match '.$mode.' mode. Expected prefix '.$secretPrefix.'.');
    $webhookDetail=!$status['webhook_configured']&&!mg_stripe_stub_enabled()
        ? 'Missing Stripe webhook signing secret.'
        : ($webhookOk
            ? (mg_stripe_stub_enabled()?'Stripe stub mode is enabled.':'Configured as a Stripe webhook signing secret.')
            : 'Configured webhook value does not look like a Stripe webhook signing secret. Expected prefix whsec_.');
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
            'ok'=>$publishableOk,
            'label'=>'Publishable key',
            'detail'=>$publishableDetail,
        ],
        'secret_key'=>[
            'ok'=>$secretOk,
            'label'=>'Secret key',
            'detail'=>$secretDetail,
        ],
        'webhook_secret'=>[
            'ok'=>$webhookOk,
            'label'=>'Webhook secret',
            'detail'=>$webhookDetail,
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
    $platformReady=!in_array(false,array_map(static fn(array $check): bool=>(bool)$check['ok'],$checks),true);
    $sellingMerchants=mg_payment_selling_merchant_readiness($pdo,$provider,$mode);
    return [
        'provider'=>$status,
        'checks'=>$checks,
        'ready'=>$platformReady,
        'launch_ready'=>$platformReady&&(int)$sellingMerchants['blocked']===0,
        'connected_accounts'=>[
            'total'=>(int)($accountCounts['total']??0),
            'ready'=>(int)($accountCounts['ready']??0),
        ],
        'selling_merchants'=>$sellingMerchants,
        'webhook_url'=>$appUrl!==''?$appUrl.'/api/payments/webhook.php?provider=stripe':'/api/payments/webhook.php?provider=stripe',
    ];
}
