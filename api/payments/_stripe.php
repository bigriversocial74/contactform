<?php
declare(strict_types=1);

require_once __DIR__ . '/_provider_credentials.php';

final class MgStripeProviderException extends RuntimeException
{
    public function __construct(string $message,public readonly int $httpStatus=502,public readonly ?string $stripeCode=null)
    {
        parent::__construct($message);
    }
}

function mg_stripe_stub_enabled(): bool
{
    return mg_payment_mode()==='test'&&filter_var((string)(getenv('MG_STRIPE_TEST_STUB')?:''),FILTER_VALIDATE_BOOL);
}

function mg_payment_absolute_url(string $path): string
{
    if(preg_match('#^https://#i',$path)===1)return $path;
    if(preg_match('#^http://#i',$path)===1&&mg_payment_mode()==='test')return $path;
    $base=rtrim(trim((string)(getenv('MG_APP_URL')?:'')),'/');
    if($base==='')throw new MgStripeProviderException('MG_APP_URL is required for Stripe hosted checkout and Connect onboarding.',500);
    if(mg_payment_mode()==='live'&&stripos($base,'https://')!==0)throw new MgStripeProviderException('Live Stripe mode requires an HTTPS MG_APP_URL.',500);
    return $base.'/'.ltrim($path,'/');
}

function mg_stripe_api_request(PDO $pdo,string $method,string $path,array $params=[],?string $idempotencyKey=null): array
{
    if(mg_stripe_stub_enabled()){
        $seed=substr(hash('sha256',$path.'|'.json_encode($params).'|'.(string)$idempotencyKey),0,24);
        if($path==='/v1/checkout/sessions'){
            return [
                'id'=>'cs_test_stub_'.$seed,
                'object'=>'checkout.session',
                'url'=>'https://checkout.stripe.test/c/pay/'.$seed,
                'payment_intent'=>'pi_test_stub_'.$seed,
                'payment_status'=>'unpaid',
                'status'=>'open',
                'expires_at'=>(int)($params['expires_at']??time()+1800),
                'amount_total'=>(int)($params['metadata']['order_total_cents']??0),
                'currency'=>(string)($params['metadata']['currency']??'usd'),
                'metadata'=>$params['metadata']??[],
            ];
        }
        if($path==='/v1/accounts'){
            return [
                'id'=>'acct_test_stub_'.$seed,
                'object'=>'account',
                'charges_enabled'=>false,
                'payouts_enabled'=>false,
                'details_submitted'=>false,
                'capabilities'=>['card_payments'=>'pending','transfers'=>'pending'],
                'requirements'=>['currently_due'=>['business_profile.url','external_account']],
            ];
        }
        if(str_starts_with($path,'/v1/account_links')){
            return ['object'=>'account_link','url'=>'https://connect.stripe.test/onboarding/'.$seed,'expires_at'=>time()+1800];
        }
        if(str_starts_with($path,'/v1/accounts/')){
            $id=rawurldecode(substr($path,strlen('/v1/accounts/')));
            $active=filter_var((string)(getenv('MG_STRIPE_STUB_ACCOUNT_ACTIVE')?:'1'),FILTER_VALIDATE_BOOL);
            return [
                'id'=>$id,
                'object'=>'account',
                'charges_enabled'=>$active,
                'payouts_enabled'=>$active,
                'details_submitted'=>$active,
                'capabilities'=>['card_payments'=>$active?'active':'pending','transfers'=>$active?'active':'pending'],
                'requirements'=>['currently_due'=>$active?[]:['business_profile.url','external_account']],
            ];
        }
        if($path==='/v1/payment_intents'){
            return [
                'id'=>'pi_test_stub_'.$seed,
                'object'=>'payment_intent',
                'client_secret'=>'pi_test_stub_'.$seed.'_secret_stub',
                'status'=>'requires_action',
                'amount'=>(int)($params['amount']??0),
                'currency'=>(string)($params['currency']??'usd'),
                'metadata'=>$params['metadata']??[],
            ];
        }
        throw new MgStripeProviderException('Unsupported Stripe test-stub request: '.$path,500);
    }

    $config=mg_payment_platform_config($pdo,'stripe',mg_payment_mode());
    $secret=trim((string)$config['secret_key']);
    if($secret==='')throw new MgStripeProviderException('Stripe secret key is not configured.',503);
    if(!function_exists('curl_init'))throw new MgStripeProviderException('PHP cURL is required for Stripe.',500);

    $url='https://api.stripe.com'.$path;
    $method=strtoupper($method);
    if($method==='GET'&&$params!==[])$url.='?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);
    $headers=['Authorization: Bearer '.$secret,'Stripe-Version: 2024-06-20'];
    if($idempotencyKey!==null&&$idempotencyKey!=='')$headers[]='Idempotency-Key: '.$idempotencyKey;
    $curl=curl_init($url);
    curl_setopt_array($curl,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>30,
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_CUSTOMREQUEST=>$method,
    ]);
    if($method!=='GET'){
        curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($params,'','&',PHP_QUERY_RFC3986));
        $headers[]='Content-Type: application/x-www-form-urlencoded';
        curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);
    }
    $body=curl_exec($curl);
    $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    $error=curl_error($curl);
    curl_close($curl);
    if(!is_string($body))throw new MgStripeProviderException('Stripe request failed: '.$error,502);
    try{$decoded=json_decode($body,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){throw new MgStripeProviderException('Stripe returned an invalid response.',502);}
    if($status<200||$status>=300){
        $message=(string)($decoded['error']['message']??'Stripe request failed.');
        $code=isset($decoded['error']['code'])?(string)$decoded['error']['code']:null;
        throw new MgStripeProviderException($message,$status>=400&&$status<500?422:502,$code);
    }
    return $decoded;
}

function mg_stripe_verify_signature(string $payload,string $header,string $secret,int $tolerance=300): bool
{
    if($payload===''||$header===''||$secret==='')return false;
    $timestamp=0;$signatures=[];
    foreach(explode(',',$header) as $part){
        [$key,$value]=array_pad(explode('=',trim($part),2),2,'');
        if($key==='t')$timestamp=(int)$value;
        if($key==='v1'&&$value!=='')$signatures[]=$value;
    }
    if($timestamp<1||abs(time()-$timestamp)>$tolerance||$signatures===[])return false;
    $expected=hash_hmac('sha256',$timestamp.'.'.$payload,$secret);
    foreach($signatures as $signature){if(hash_equals($expected,$signature))return true;}
    return false;
}

function mg_stripe_checkout_session(PDO $pdo,array $order,array $items,array $account,array $internal,array $options=[]): array
{
    $success=mg_payment_absolute_url((string)($options['success_url']??'/checkout-success.php'));
    $cancel=mg_payment_absolute_url((string)($options['cancel_url']??'/cart.php'));
    $separator=str_contains($success,'?')?'&':'?';
    $success.=$separator.'order='.rawurlencode((string)$order['public_id']).'&stripe_session_id={CHECKOUT_SESSION_ID}';
    $params=[
        'mode'=>'payment',
        'success_url'=>$success,
        'cancel_url'=>$cancel,
        'client_reference_id'=>(string)$order['public_id'],
        'expires_at'=>time()+1800,
        'metadata'=>[
            'order_id'=>(string)$order['public_id'],
            'payment_intent_id'=>(string)$internal['payment_intent_id'],
            'checkout_session_id'=>(string)$internal['checkout_session_id'],
            'merchant_user_id'=>(string)$order['merchant_user_id'],
            'order_total_cents'=>(string)$order['total_cents'],
            'currency'=>strtolower((string)$order['currency']),
        ],
        'payment_intent_data'=>[
            'metadata'=>[
                'order_id'=>(string)$order['public_id'],
                'payment_intent_id'=>(string)$internal['payment_intent_id'],
                'checkout_session_id'=>(string)$internal['checkout_session_id'],
            ],
            'application_fee_amount'=>(int)$order['platform_fee_cents'],
            'transfer_data'=>['destination'=>(string)$account['provider_account_reference']],
        ],
        'line_items'=>[],
    ];
    foreach(array_values($items) as $index=>$item){
        $params['line_items'][$index]=[
            'quantity'=>(int)$item['quantity'],
            'price_data'=>[
                'currency'=>strtolower((string)$item['currency']),
                'unit_amount'=>(int)$item['unit_amount_cents'],
                'product_data'=>['name'=>mb_substr((string)$item['title_snapshot'],0,240)],
            ],
        ];
    }
    $session=mg_stripe_api_request($pdo,'POST','/v1/checkout/sessions',$params,'checkout:'.(string)$internal['idempotency_key']);
    if(empty($session['id'])||empty($session['url']))throw new MgStripeProviderException('Stripe did not return a hosted checkout URL.',502);
    return [
        'provider_session_reference'=>(string)$session['id'],
        'provider_intent_reference'=>trim((string)($session['payment_intent']??'')),
        'checkout_url'=>(string)$session['url'],
        'expires_at'=>date('Y-m-d H:i:s',(int)($session['expires_at']??time()+1800)),
        'status'=>(string)($session['status']??'open'),
    ];
}

function mg_stripe_create_connected_account(PDO $pdo,array $merchant,string $idempotencyKey): array
{
    return mg_stripe_api_request($pdo,'POST','/v1/accounts',[
        'type'=>'express',
        'country'=>(string)(getenv('MG_STRIPE_CONNECT_COUNTRY')?:'US'),
        'email'=>(string)($merchant['email']??''),
        'business_type'=>'company',
        'capabilities'=>[
            'card_payments'=>['requested'=>true],
            'transfers'=>['requested'=>true],
        ],
        'metadata'=>['merchant_user_id'=>(string)$merchant['id']],
    ],$idempotencyKey);
}

function mg_stripe_create_account_link(PDO $pdo,string $accountReference,string $refreshPath,string $returnPath): array
{
    return mg_stripe_api_request($pdo,'POST','/v1/account_links',[
        'account'=>$accountReference,
        'refresh_url'=>mg_payment_absolute_url($refreshPath),
        'return_url'=>mg_payment_absolute_url($returnPath),
        'type'=>'account_onboarding',
    ],null);
}

function mg_stripe_retrieve_account(PDO $pdo,string $accountReference): array
{
    return mg_stripe_api_request($pdo,'GET','/v1/accounts/'.rawurlencode($accountReference));
}

function mg_stripe_create_payment_intent(PDO $pdo,array $request): array
{
    $params=[
        'amount'=>(int)$request['amount_cents'],
        'currency'=>strtolower((string)$request['currency']),
        'automatic_payment_methods'=>['enabled'=>true],
        'metadata'=>$request['metadata']??[],
    ];
    $intent=mg_stripe_api_request($pdo,'POST','/v1/payment_intents',$params,'intent:'.(string)$request['idempotency_key']);
    return [
        'provider_intent_reference'=>(string)$intent['id'],
        'client_secret'=>$intent['client_secret']??null,
        'status'=>(string)($intent['status']??'created'),
        'amount_cents'=>(int)($intent['amount']??$request['amount_cents']),
        'currency'=>strtoupper((string)($intent['currency']??$request['currency'])),
        'metadata'=>$intent['metadata']??[],
    ];
}
