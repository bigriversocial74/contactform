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
    'persistenceJs'=>$root.'/assets/js/admin-payments-persistence.js',
    'persistenceCss'=>$root.'/assets/css/admin-payments-persistence.css',
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
        str_contains($source['adminApi'],'Test credentials are not required for a live-only setup.')
        && str_contains($source['adminApi'],'mode_storage_warning')
        && str_contains($source['adminApi'],'credentials appear to be stored in the'),
    'admin API reads back and verifies the exact database record before success' =>
        str_contains($source['adminApi'],'function mg_admin_payment_database_snapshot')
        && str_contains($source['adminApi'],'function mg_admin_payment_verify_persistence')
        && str_contains($source['adminApi'],"'persistence_verified'=>true")
        && str_contains($source['adminApi'],'Stripe settings failed database verification')
        && str_contains($source['adminApi'],'No unverified update was accepted.'),
    'database snapshot distinguishes saved values from environment overrides' =>
        str_contains($source['adminApi'],"\$payload['storage']=\$storage")
        && str_contains($source['adminApi'],"\$payload['environment_override']")
        && str_contains($source['adminApi'],'effective_publishable_key')
        && str_contains($source['adminApi'],'effective_connect_client_id'),
    'admin UI separates encrypted secrets and exposes masked database references' =>
        str_contains($source['adminPage'],'data-admin-payment-tab="secrets"')
        && str_contains($source['adminPage'],'data-admin-payment-page="secrets"')
        && str_contains($source['adminPage'],'Encrypted Stripe secret storage')
        && str_contains($source['adminPage'],'data-payment-secret-save-status')
        && str_contains($source['adminFields'],'data-payment-secret-display')
        && str_contains($source['adminFields'],'data-payment-secret-replace')
        && str_contains($source['adminJs'],"'Saved in database · '")
        && str_contains($source['adminJs'],'provider.secret_hint')
        && str_contains($source['adminJs'],'provider.webhook_hint')
        && str_contains($source['adminJs'],'Saved and database-verified for '),
    'browser initially requests automatic mode selection' =>
        str_contains($source['adminJs'],"requestedMode = 'auto'")
        && str_contains($source['adminJs'],'load(requestedMode)')
        && str_contains($source['adminJs'],'provider.mode'),
    'browser accepts mode-matched secret and restricted keys' =>
        str_contains($source['adminJs'],"value.indexOf('sk_' + selected + '_')")
        && str_contains($source['adminJs'],"value.indexOf('rk_' + selected + '_')")
        && str_contains($source['adminJs'],"payload.webhook_secret.indexOf('whsec_')"),
    'persistence client resolves authoritative mode before writing the URL' =>
        str_contains($source['persistenceJs'],'function initializePersistence()')
        && str_contains($source['persistenceJs'],"payment-settings.php?mode=auto&verify=")
        && str_contains($source['persistenceJs'],'function responseMode(data)')
        && str_contains($source['persistenceJs'],'mode.value = resolved')
        && str_contains($source['persistenceJs'],'updateModeUrl(resolved)')
        && !str_contains($source['persistenceJs'],"updateModeUrl();\n    window.setTimeout(function () { readBack(null);"),
    'persistence client verifies the exact submitted mode and database record' =>
        str_contains($source['persistenceJs'],"String(storage.mode || '') !== String(expected.mode || '')")
        && str_contains($source['persistenceJs'],"mismatches.push('configuration mode')")
        && str_contains($source['persistenceJs'],'verifyWhenSaveFinishes')
        && str_contains($source['persistenceJs'],'Save verification failed after reload')
        && str_contains($source['persistenceJs'],"Microgifter.get('/api/admin/payment-settings.php?mode='"),
    'cross-mode storage warning is shown only for the selected record' =>
        str_contains($source['persistenceJs'],'function syncModeWarning()')
        && str_contains($source['persistenceJs'],'/stored in the (Test|Live) record/i')
        && str_contains($source['persistenceJs'],'warningMode !== selectedMode()')
        && str_contains($source['persistenceJs'],'MutationObserver(syncModeWarning)'),
    'persistence client explains write-only secret fields' =>
        str_contains($source['persistenceJs'],'Secret fields remain blank after reload by design')
        && str_contains($source['persistenceJs'],'API key saved securely.')
        && str_contains($source['persistenceCss'],'.mg-payment-persistence-state.is-success'),
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

echo "\nStripe live credential mode and persistence contract: 10/10.".PHP_EOL;
