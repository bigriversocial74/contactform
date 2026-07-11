<?php
declare(strict_types=1);

require_once __DIR__ . '/_stripe.php';

function mg_stripe_connect_oauth_request(PDO $pdo,string $endpoint,array $params,string $mode): array
{
    $mode=$mode==='live'?'live':'test';
    if(mg_stripe_stub_enabled()){
        $seed=substr(hash('sha256',$endpoint.'|'.json_encode($params)),0,24);
        if($endpoint==='token'){
            return [
                'token_type'=>'bearer',
                'scope'=>'read_write',
                'livemode'=>$mode==='live',
                'stripe_user_id'=>'acct_test_oauth_'.$seed,
            ];
        }
        if($endpoint==='deauthorize'){
            return ['stripe_user_id'=>(string)($params['stripe_user_id']??'')];
        }
    }

    $config=mg_payment_platform_config($pdo,'stripe',$mode);
    $secret=trim((string)$config['secret_key']);
    if($secret==='')throw new MgStripeProviderException('Stripe API credentials are not configured for '.$mode.' mode.',503);
    if(!mg_payment_secret_matches_mode($secret,$mode))throw new MgStripeProviderException('The Stripe API key does not match '.$mode.' mode.',422);
    if(mg_payment_secret_key_type($secret)!=='secret'){
        throw new MgStripeProviderException('Stripe Connect OAuth requires the platform standard secret key (sk_'.$mode.'_…). A restricted rk_'.$mode.'_ key cannot complete OAuth authorization.',422);
    }
    if(!function_exists('curl_init'))throw new MgStripeProviderException('PHP cURL is required for Stripe Connect.',500);

    $url='https://connect.stripe.com/oauth/'.$endpoint;
    $curl=curl_init($url);
    curl_setopt_array($curl,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>30,
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>http_build_query($params,'','&',PHP_QUERY_RFC3986),
        CURLOPT_HTTPHEADER=>[
            'Authorization: Basic '.base64_encode($secret.':'),
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);
    $body=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    $error=curl_error($curl);
    curl_close($curl);

    if(!is_string($body))throw new MgStripeProviderException('Stripe Connect request failed: '.$error,502);
    try{$decoded=json_decode($body,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){throw new MgStripeProviderException('Stripe Connect returned an invalid response.',502);}
    if($status<200||$status>=300){
        $errorValue=$decoded['error']??'';
        $message=(string)($decoded['error_description']??(is_array($errorValue)?($errorValue['message']??''):''));
        if($message==='')$message='Stripe Connect request failed.';
        $code=is_string($errorValue)?$errorValue:(is_array($errorValue)?(string)($errorValue['code']??''):'');
        throw new MgStripeProviderException($message,$status>=400&&$status<500?422:502,$code!==''?$code:null);
    }
    return $decoded;
}

function mg_stripe_connect_exchange_code(PDO $pdo,string $code,string $mode): array
{
    $code=trim($code);
    if($code===''||!str_starts_with($code,'ac_'))throw new InvalidArgumentException('Stripe authorization code is missing or invalid.');
    $result=mg_stripe_connect_oauth_request($pdo,'token',[
        'grant_type'=>'authorization_code',
        'code'=>$code,
    ],$mode);
    $accountId=trim((string)($result['stripe_user_id']??''));
    if($accountId===''||!str_starts_with($accountId,'acct_'))throw new MgStripeProviderException('Stripe did not return a connected account ID.',502);
    $livemode=!empty($result['livemode']);
    if(($mode==='live')!==$livemode)throw new MgStripeProviderException('Stripe returned an account connection for the wrong payment mode.',422);
    return $result;
}

function mg_stripe_connect_deauthorize(PDO $pdo,string $clientId,string $accountId,string $mode): array
{
    $clientId=trim($clientId);
    $accountId=trim($accountId);
    if(!str_starts_with($clientId,'ca_'))throw new InvalidArgumentException('Stripe Connect client ID is not configured.');
    if(!str_starts_with($accountId,'acct_'))throw new InvalidArgumentException('Stripe connected account ID is invalid.');
    return mg_stripe_connect_oauth_request($pdo,'deauthorize',[
        'client_id'=>$clientId,
        'stripe_user_id'=>$accountId,
    ],$mode);
}
