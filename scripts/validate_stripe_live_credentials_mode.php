<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$paths=[
    'provider'=>$root.'/api/payments/_provider_credentials.php',
    'readiness'=>$root.'/api/payments/_readiness.php',
    'adminApi'=>$root.'/api/admin/payment-settings.php',
    'adminPage'=>$root.'/admin-payments.php',
    'adminFields'=>$root.'/includes/admin-payment-credential-fields.php',
    'adminJs'=>$root.'/assets/js/admin-payments.js',
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
    'generic Stripe publishable keys cannot leak across test and live modes' =>
        str_contains($source['provider'],"return str_starts_with(\$generic,'pk_'.\$mode.'_')?\$generic:''")
        && !str_contains($source['provider'],"return trim((string)(getenv('MG_'.strtoupper(\$provider).'_'.strtoupper(\$field)) ?: ''))"),
    'generic Stripe API keys are matched to their encoded mode' =>
        str_contains($source['provider'],'function mg_payment_secret_matches_mode')
        && str_contains($source['provider'],"str_starts_with(\$secret,'sk_'.\$mode.'_')")
        && str_contains($source['provider'],"str_starts_with(\$secret,'rk_'.\$mode.'_')"),
    'restricted keys are accepted and identified safely' =>
        str_contains($source['provider'],'function mg_payment_secret_key_type')
        && str_contains($source['provider'],"return 'restricted'")
        && str_contains($source['readiness'],'restricted key')
        && str_contains($source['adminFields'],'rk_live_…'),
    'readiness validates the selected mode rather than requiring test credentials' =>
        str_contains($source['readiness'],'mg_payment_secret_matches_mode($secret,$mode)')
        && str_contains($source['readiness'],'Missing Stripe publishable key for ')
        && str_contains($source['readiness'],"This '.\$mode.' configuration is saved"),
    'admin API auto-selects a relevant configured mode' =>
        str_contains($source['adminApi'],'function mg_admin_payment_default_mode')
        && str_contains($source['adminApi'],"\$_GET['mode']??'auto'")
        && str_contains($source['adminApi'],"\$payload['selected_mode']=\$mode")
        && str_contains($source['adminApi'],"\$payload['configured_modes']=mg_admin_payment_configured_modes(\$pdo)"),
    'admin API clearly allows live-only credential storage' =>
        str_contains($source['adminApi'],'Test credentials are not required for this ')
        && str_contains($source['adminApi'],'mode_storage_warning')
        && str_contains($source['adminApi'],'credentials appear to be stored in the'),
    'admin UI explains that Test and Live are independent' =>
        str_contains($source['adminPage'],'A live-only setup does not require test credentials.')
        && str_contains($source['adminPage'],'data-payment-mode-help')
        && str_contains($source['adminPage'],'data-payment-mode-warning'),
    'browser initially requests automatic mode selection' =>
        str_contains($source['adminJs'],"requestedMode = 'auto'")
        && str_contains($source['adminJs'],'load(requestedMode)')
        && str_contains($source['adminJs'],'provider.mode'),
    'browser accepts matching secret or restricted keys' =>
        str_contains($source['adminJs'],"value.indexOf('sk_' + selected + '_')")
        && str_contains($source['adminJs'],"value.indexOf('rk_' + selected + '_')")
        && str_contains($source['adminJs'],'Test credentials are not required when saving Live.'),
    'generated server config follows the selected mode and sets app URL' =>
        str_contains($source['adminJs'],"putenv('MG_PAYMENT_MODE=")
        && str_contains($source['adminJs'],"putenv('MG_APP_URL=")
        && !str_contains($source['adminJs'],"putenv('MG_PAYMENT_MODE=test')"),
];

$failed=[];
foreach($checks as $name=>$passed){
    echo ($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$passed)$failed[]=$name;
}

if($failed!==[]){
    fwrite(STDERR,"\nStripe live credential mode validation failed: ".implode('; ',$failed).PHP_EOL);
    exit(1);
}

echo "\nStripe live credential mode contract: 10/10.".PHP_EOL;
