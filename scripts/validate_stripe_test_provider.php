<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__).'/api/payments/_readiness.php';

function mg_stripe_provider_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

$pdo=mg_db();
$runId='stripe_provider_'.bin2hex(random_bytes(6));
$connectedAccount=trim((string)(getenv('MG_STRIPE_TEST_CONNECTED_ACCOUNT_ID')?:''));
$summary=[
    'suite'=>'stripe_test_provider_boundary',
    'run_id'=>$runId,
    'platform_ready'=>false,
    'platform_api_reachable'=>false,
    'connected_account_ready'=>false,
    'checkout_session_created'=>false,
    'destination_charge_bound'=>false,
    'checkout_session_expired'=>false,
];

try{
    mg_stripe_provider_assert(mg_payment_provider_key()==='stripe','MG_PAYMENT_PROVIDER must be stripe.');
    mg_stripe_provider_assert(mg_payment_mode()==='test','Provider validation is restricted to Stripe test mode.');
    mg_stripe_provider_assert(!mg_stripe_stub_enabled(),'MG_STRIPE_TEST_STUB must be disabled for provider validation.');
    mg_stripe_provider_assert($connectedAccount!=='','MG_STRIPE_TEST_CONNECTED_ACCOUNT_ID is required.');

    $readiness=mg_payment_readiness($pdo,'stripe','test');
    mg_stripe_provider_assert($readiness['ready']===true,'Stripe test platform readiness did not pass.');
    $summary['platform_ready']=true;

    $platform=mg_stripe_api_request($pdo,'GET','/v1/account');
    mg_stripe_provider_assert(str_starts_with((string)($platform['id']??''),'acct_'),'Stripe platform account could not be retrieved.');
    $summary['platform_api_reachable']=true;

    $account=mg_stripe_retrieve_account($pdo,$connectedAccount);
    mg_stripe_provider_assert((string)($account['id']??'')===$connectedAccount,'Stripe returned a different connected account.');
    mg_stripe_provider_assert(($account['charges_enabled']??false)===true,'Connected account does not have charges enabled.');
    mg_stripe_provider_assert(($account['payouts_enabled']??false)===true,'Connected account does not have payouts enabled.');
    $summary['connected_account_ready']=true;

    $params=[
        'mode'=>'payment',
        'success_url'=>mg_payment_absolute_url('/checkout-success.php?provider_validation=1'),
        'cancel_url'=>mg_payment_absolute_url('/cart.php?provider_validation=1'),
        'client_reference_id'=>'provider-validation-'.$runId,
        'expires_at'=>time()+1800,
        'metadata'=>[
            'validation_run_id'=>$runId,
            'purpose'=>'release_provider_boundary',
        ],
        'payment_intent_data'=>[
            'metadata'=>[
                'validation_run_id'=>$runId,
                'purpose'=>'release_provider_boundary',
            ],
            'application_fee_amount'=>150,
            'transfer_data'=>['destination'=>$connectedAccount],
        ],
        'line_items'=>[[
            'quantity'=>1,
            'price_data'=>[
                'currency'=>'usd',
                'unit_amount'=>1000,
                'product_data'=>['name'=>'Microgifter Stripe provider validation'],
            ],
        ]],
    ];
    $session=mg_stripe_api_request($pdo,'POST','/v1/checkout/sessions',$params,'provider-validation:'.$runId);
    $sessionId=(string)($session['id']??'');
    mg_stripe_provider_assert(str_starts_with($sessionId,'cs_test_'),'Stripe did not create a test Checkout Session.');
    mg_stripe_provider_assert(str_starts_with((string)($session['url']??''),'https://checkout.stripe.com/'),'Stripe did not return a hosted Checkout URL.');
    $summary['checkout_session_created']=true;
    $summary['destination_charge_bound']=true;

    $expired=mg_stripe_api_request($pdo,'POST','/v1/checkout/sessions/'.rawurlencode($sessionId).'/expire',[],'provider-validation-expire:'.$runId);
    mg_stripe_provider_assert((string)($expired['status']??'')==='expired','Stripe Checkout Session was not expired after validation.');
    $summary['checkout_session_expired']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(1);
}
