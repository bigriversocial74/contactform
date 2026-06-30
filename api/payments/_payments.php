<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_provider_credentials.php';
require_once __DIR__ . '/_stripe.php';

function mg_payment_provider_key(): string
{
    return strtolower(trim((string)(getenv('MG_PAYMENT_PROVIDER') ?: 'sandbox'))) ?: 'sandbox';
}

function mg_payment_is_live(): bool
{
    return mg_payment_mode()==='live';
}

function mg_payment_cash_enabled(PDO $pdo): bool
{
    $row=mg_payment_platform_credential_row($pdo,'cash','test',false);
    return $row?(bool)($row['enabled']??false):false;
}

function mg_payment_checkout_provider_key(PDO $pdo,?string $requestedProvider=null): string
{
    $requested=strtolower(trim((string)$requestedProvider));
    if($requested==='card')$requested='stripe';
    if($requested==='cash'){
        if(!mg_payment_cash_enabled($pdo))throw new RuntimeException('Cash payment is not enabled.');
        return 'cash';
    }
    if($requested==='stripe')return 'stripe';
    if($requested==='sandbox')return 'sandbox';
    if(mg_payment_cash_enabled($pdo))return 'cash';
    return mg_payment_provider_key();
}

function mg_payment_webhook_secret(string $provider,?PDO $pdo=null): string
{
    $provider=strtolower(trim($provider));
    if($provider==='stripe'){
        $pdo??=mg_db();
        return (string)mg_payment_platform_config($pdo,'stripe',mg_payment_mode())['webhook_secret'];
    }
    $key='MG_PAYMENT_WEBHOOK_SECRET_'.strtoupper(preg_replace('/[^A-Z0-9]+/i','_',$provider));
    return (string)(getenv($key) ?: getenv('MG_PAYMENT_WEBHOOK_SECRET') ?: '');
}

function mg_payment_verify_signature(string $provider,string $payload,string $signature,?PDO $pdo=null): bool
{
    $secret=mg_payment_webhook_secret($provider,$pdo);
    if($provider==='stripe')return mg_stripe_verify_signature($payload,$signature,$secret);
    if($secret===''||$signature==='')return false;
    return hash_equals(hash_hmac('sha256',$payload,$secret),$signature);
}

function mg_payment_platform_fee_cents(PDO $pdo,int $subtotalCents,?string $provider=null): int
{
    $subtotalCents=max(0,$subtotalCents);
    $config=mg_payment_platform_config($pdo,$provider??mg_payment_provider_key(),mg_payment_mode());
    $fee=(int)round($subtotalCents*((int)$config['platform_fee_bps']/10000))+(int)$config['fixed_fee_cents'];
    return max(0,min($subtotalCents,$fee));
}

function mg_payment_order_totals(array $items,?PDO $pdo=null): array
{
    $subtotal=0;
    foreach($items as $item)$subtotal+=(int)$item['line_total_cents'];
    $fee=$pdo?mg_payment_platform_fee_cents($pdo,$subtotal):0;
    return [
        'subtotal_cents'=>$subtotal,
        'discount_cents'=>0,
        'tax_cents'=>0,
        'platform_fee_cents'=>$fee,
        'total_cents'=>$subtotal,
    ];
}

function mg_payment_normalize_intent_status(string $status): string
{
    return match($status){
        'requires_payment_method'=>'requires_payment_method',
        'requires_action'=>'requires_action',
        'processing'=>'processing',
        'requires_capture','authorized'=>'authorized',
        'succeeded'=>'succeeded',
        'canceled','cancelled'=>'cancelled',
        'failed'=>'failed',
        default=>'created',
    };
}

function mg_payment_provider_account(PDO $pdo,int $merchantUserId,string $provider,?string $mode=null,bool $forUpdate=false): ?array
{
    $mode=$mode??mg_payment_mode();
    $stmt=$pdo->prepare('SELECT * FROM payment_provider_accounts WHERE merchant_user_id=? AND provider_key=? AND mode=? LIMIT 1'.($forUpdate?' FOR UPDATE':''));
    $stmt->execute([$merchantUserId,$provider,$mode]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_payment_assert_checkout_ready(PDO $pdo,array $order,string $provider): ?array
{
    if($provider==='sandbox'||$provider==='cash')return null;
    if($provider!=='stripe')throw new RuntimeException('Unsupported payment provider: '.$provider);
    $config=mg_payment_platform_config($pdo,'stripe',mg_payment_mode());
    if(!$config['enabled']||trim((string)$config['secret_key'])===''||trim((string)$config['webhook_secret'])===''){
        throw new RuntimeException('Stripe is not configured for '.mg_payment_mode().' mode.');
    }
    $account=mg_payment_provider_account($pdo,(int)$order['merchant_user_id'],'stripe',mg_payment_mode(),true);
    if(!$account||trim((string)$account['provider_account_reference'])===''||(int)$account['charges_enabled']!==1||(int)$account['payouts_enabled']!==1||(string)$account['status']!=='active'){
        throw new RuntimeException('The merchant must complete Stripe Connect onboarding before accepting payments.');
    }
    return $account;
}

function mg_payment_provider_create_intent(array $request,?PDO $pdo=null): array
{
    $provider=(string)$request['provider_key'];
    if($provider==='stripe'){
        $pdo??=mg_db();
        return mg_stripe_create_payment_intent($pdo,$request);
    }
    if(mg_payment_is_live()&&!in_array($provider,['sandbox','cash'],true)){
        throw new RuntimeException('Live provider intent creation requires the configured provider adapter.');
    }
    $reference='pi_test_'.str_replace('-','',mg_public_uuid());
    $secret=(string)(getenv('MG_PAYMENT_SANDBOX_SECRET')?:'microgifter-sandbox');
    return [
        'provider_intent_reference'=>$reference,
        'client_secret'=>$reference.'_secret_'.substr(hash_hmac('sha256',$reference,$secret),0,32),
        'status'=>'requires_action',
        'amount_cents'=>(int)$request['amount_cents'],
        'currency'=>(string)$request['currency'],
        'metadata'=>$request['metadata']??[],
    ];
}

function mg_payment_provider_retrieve_intent(string $provider,string $providerReference,?PDO $pdo=null): array
{
    if($provider==='stripe'){
        $pdo??=mg_db();
        $intent=mg_stripe_api_request($pdo,'GET','/v1/payment_intents/'.rawurlencode($providerReference));
        return [
            'provider_intent_reference'=>(string)$intent['id'],
            'client_secret'=>$intent['client_secret']??null,
            'status'=>(string)($intent['status']??'created'),
        ];
    }
    if(mg_payment_is_live()&&!in_array($provider,['sandbox','cash'],true)){
        throw new RuntimeException('Live provider intent retrieval requires the configured provider adapter.');
    }
    $secret=(string)(getenv('MG_PAYMENT_SANDBOX_SECRET')?:'microgifter-sandbox');
    return [
        'provider_intent_reference'=>$providerReference,
        'client_secret'=>$providerReference.'_secret_'.substr(hash_hmac('sha256',$providerReference,$secret),0,32),
        'status'=>'requires_action',
    ];
}

function mg_payment_create_source_intent(PDO $pdo,array $request): array
{
    $provider=trim((string)($request['provider_key']??mg_payment_provider_key()));
    $sourceType=trim((string)($request['source_type']??''));
    $sourceReference=trim((string)($request['source_reference']??''));
    $idempotencyKey=trim((string)($request['idempotency_key']??''));
    $amount=(int)($request['amount_cents']??0);
    $currency=strtoupper(trim((string)($request['currency']??'USD')));
    if($provider===''||$sourceType===''||$sourceReference===''||$idempotencyKey===''||$amount<1||!preg_match('/^[A-Z]{3}$/',$currency)){
        throw new InvalidArgumentException('Valid payment intent source, amount, currency, and idempotency key are required.');
    }
    $existing=$pdo->prepare('SELECT * FROM payment_intents WHERE provider_key=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([$provider,$idempotencyKey]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC)){
        $same=(string)$row['source_type']===$sourceType
            &&(string)$row['source_reference']===$sourceReference
            &&(int)$row['amount_cents']===$amount
            &&(string)$row['currency']===$currency;
        if(!$same)throw new RuntimeException('Payment intent idempotency key is already bound to a different request.');
        $providerIntent=mg_payment_provider_retrieve_intent($provider,(string)$row['provider_intent_reference'],$pdo);
        return $row+['client_secret'=>$providerIntent['client_secret']??null,'duplicate'=>true];
    }
    $providerIntent=mg_payment_provider_create_intent([
        'provider_key'=>$provider,
        'amount_cents'=>$amount,
        'currency'=>$currency,
        'idempotency_key'=>$idempotencyKey,
        'metadata'=>array_merge($request['metadata']??[],['source_type'=>$sourceType,'source_reference'=>$sourceReference]),
    ],$pdo);
    if((int)$providerIntent['amount_cents']!==$amount||(string)$providerIntent['currency']!==$currency){
        throw new RuntimeException('Payment provider returned conflicting amount or currency.');
    }
    $publicId=mg_public_uuid();
    $status=mg_payment_normalize_intent_status((string)$providerIntent['status']);
    $pdo->prepare('INSERT INTO payment_intents (public_id,order_id,source_type,source_reference,provider_key,provider_intent_reference,amount_cents,currency,status,capture_method,idempotency_key,created_at,updated_at) VALUES (?,NULL,?,?,?,?,?,?,?,\'automatic\',?,NOW(),NOW())')
        ->execute([$publicId,$sourceType,$sourceReference,$provider,(string)$providerIntent['provider_intent_reference'],$amount,$currency,$status,$idempotencyKey]);
    return [
        'id'=>(int)$pdo->lastInsertId(),
        'public_id'=>$publicId,
        'source_type'=>$sourceType,
        'source_reference'=>$sourceReference,
        'provider_key'=>$provider,
        'provider_intent_reference'=>(string)$providerIntent['provider_intent_reference'],
        'amount_cents'=>$amount,
        'currency'=>$currency,
        'status'=>$status,
        'client_secret'=>$providerIntent['client_secret']??null,
        'duplicate'=>false,
    ];
}

function mg_payment_record_intent_transaction(PDO $pdo,int $paymentIntentId,string $providerReference,int $amount,string $currency,string $type='sale'): array
{
    $existing=$pdo->prepare('SELECT * FROM payment_transactions WHERE payment_intent_id=? AND provider_reference=? AND transaction_type=? LIMIT 1 FOR UPDATE');
    $existing->execute([$paymentIntentId,$providerReference,$type]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC)){
        if((int)$row['amount_cents']!==$amount||(string)$row['currency']!==$currency)throw new RuntimeException('Payment transaction replay conflicts with the original amount or currency.');
        return $row+['duplicate'=>true];
    }
    $publicId=mg_public_uuid();
    $pdo->prepare('INSERT INTO payment_transactions (public_id,payment_intent_id,transaction_type,provider_reference,amount_cents,currency,status,occurred_at,metadata_json,created_at) VALUES (?,?,?,?,?,?,\'succeeded\',NOW(),?,NOW())')
        ->execute([$publicId,$paymentIntentId,$type,$providerReference,$amount,$currency,json_encode(['source'=>'payment_webhook'],JSON_THROW_ON_ERROR)]);
    return ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$publicId,'duplicate'=>false];
}

/**
 * Legacy Stage 5 ledger writer retained only as a fail-closed compatibility symbol.
 */
function mg_ledger_pair(PDO $pdo,?int $merchantUserId,?int $orderId,string $debit,string $credit,int $amount,string $currency,string $description,?int $refundId=null,?int $payoutId=null): void
{
    throw new LogicException('Legacy financial_ledger_entries posting is disabled. Use the canonical Stage 7 ledger posting authority.');
}
