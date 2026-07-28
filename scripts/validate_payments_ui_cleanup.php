<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$files=[
    'merchantPage'=>$root.'/merchant-payments.php',
    'merchantView'=>$root.'/includes/merchant-payments-view.php',
    'merchantCss'=>$root.'/assets/css/merchant-payments.css',
    'merchantJs'=>$root.'/assets/js/merchant-payments.js',
    'merchantApi'=>$root.'/api/merchant/payment-methods.php',
    'adminPage'=>$root.'/admin-payments.php',
    'adminCss'=>$root.'/assets/css/admin-payments.css',
    'adminJs'=>$root.'/assets/js/admin-payments.js',
];

$content=[];
foreach($files as $key=>$path){
    if(!is_file($path)){
        fwrite(STDERR,"Missing required file: {$path}\n");
        exit(1);
    }
    $content[$key]=(string)file_get_contents($path);
}

$checks=[
    'merchant hero and right sidebar are removed' =>
        !str_contains($content['merchantView'],'mg-payments-hero')
        && !str_contains($content['merchantView'],'Checkout readiness center')
        && !str_contains($content['merchantView'],'mg-payments-side')
        && !str_contains($content['merchantView'],'<aside'),
    'merchant uses one six-tab payments navigation' =>
        substr_count($content['merchantView'],'data-payments-tab=')===6
        && str_contains($content['merchantView'],'data-payments-tab="methods"')
        && str_contains($content['merchantView'],'data-payments-tab="reconciliation"')
        && !str_contains($content['merchantView'],'data-financial-tab='),
    'five payment metrics stay on one horizontal row' =>
        str_contains($content['merchantCss'],'grid-template-columns:repeat(5,minmax(180px,1fr))!important')
        && str_contains($content['merchantCss'],'overflow-x:auto')
        && str_contains($content['merchantCss'],'min-width:180px'),
    'merchant Cash and Stripe toggles are present' =>
        str_contains($content['merchantView'],'data-cash-payment-toggle')
        && str_contains($content['merchantView'],'data-stripe-payment-toggle')
        && str_contains($content['merchantJs'],'cash_enabled')
        && str_contains($content['merchantJs'],'stripe_enabled'),
    'merchant payment preferences persist and report actual Stripe onboarding state' =>
        str_contains($content['merchantApi'],"'cash' => [")
        && str_contains($content['merchantApi'],"'stripe' => [")
        && str_contains($content['merchantApi'],'mg_payment_connect_status')
        && str_contains($content['merchantApi'],"? 'ready'")
        && str_contains($content['merchantApi'],"? 'pending_onboarding' : 'not_connected'")
        && str_contains($content['merchantApi'],"'stripe_onboarding_connected' => !empty(\$stripeAccount['connected'])"),
    'legacy unfinished connect client is not loaded on merchant page' =>
        !str_contains($content['merchantPage'],'merchant-connect.js'),
    'admin page is split into four focused tabs' =>
        substr_count($content['adminPage'],'data-admin-payment-tab=')===4
        && str_contains($content['adminPage'],'data-admin-payment-tab="methods"')
        && str_contains($content['adminPage'],'data-admin-payment-tab="stripe"')
        && str_contains($content['adminPage'],'data-admin-payment-tab="secrets"')
        && str_contains($content['adminPage'],'data-admin-payment-tab="readiness"')
        && str_contains($content['adminPage'],'data-admin-payment-page="secrets"'),
    'admin Cash and Stripe method toggles are visible' =>
        str_contains($content['adminPage'],'data-admin-cash-payment-toggle')
        && str_contains($content['adminPage'],'data-admin-stripe-payment-toggle')
        && str_contains($content['adminJs'],"saveSettings('method')"),
    'admin hero is removed without removing secure credential controls' =>
        !str_contains($content['adminPage'],'mg-payment-hero')
        && str_contains($content['adminPage'],'data-payment-key-generate')
        && str_contains($content['adminPage'],'data-payment-settings-form')
        && str_contains($content['adminPage'],'data-payment-checks')
        && str_contains($content['adminPage'],'Encrypted Stripe secret storage'),
    'tab controllers use scoped page switching' =>
        str_contains($content['merchantJs'],'activatePage')
        && str_contains($content['merchantJs'],'[data-payments-page]')
        && str_contains($content['adminJs'],'activatePage')
        && str_contains($content['adminJs'],'[data-admin-payment-page]'),
];

$failed=[];
foreach($checks as $name=>$passed){
    echo ($passed?'[PASS] ':'[FAIL] ').$name.PHP_EOL;
    if(!$passed)$failed[]=$name;
}

if($failed!==[]){
    fwrite(STDERR,"\nPayments UI cleanup validation failed: ".implode('; ',$failed).PHP_EOL);
    exit(1);
}

echo "\nPayments UI cleanup contract: 10/10.".PHP_EOL;
