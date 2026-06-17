<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

function mg_payment_provider_key(): string
{
    return trim((string)(getenv('MG_PAYMENT_PROVIDER') ?: 'sandbox'));
}

function mg_payment_is_live(): bool
{
    return strtolower((string)(getenv('MG_PAYMENT_MODE') ?: 'test')) === 'live';
}

function mg_payment_webhook_secret(string $provider): string
{
    $key = 'MG_PAYMENT_WEBHOOK_SECRET_' . strtoupper(preg_replace('/[^A-Z0-9]+/i','_',$provider));
    return (string)(getenv($key) ?: getenv('MG_PAYMENT_WEBHOOK_SECRET') ?: '');
}

function mg_payment_verify_signature(string $provider, string $payload, string $signature): bool
{
    $secret = mg_payment_webhook_secret($provider);
    if ($secret === '' || $signature === '') return false;
    return hash_equals(hash_hmac('sha256',$payload,$secret),$signature);
}

function mg_payment_order_totals(array $items): array
{
    $subtotal = 0;
    foreach ($items as $item) $subtotal += (int)$item['line_total_cents'];
    return ['subtotal_cents'=>$subtotal,'discount_cents'=>0,'tax_cents'=>0,'platform_fee_cents'=>0,'total_cents'=>$subtotal];
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

function mg_payment_provider_create_intent(array $request): array
{
    $provider=(string)$request['provider_key'];
    if(mg_payment_is_live() && $provider!=='sandbox'){
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

function mg_payment_provider_retrieve_intent(string $provider,string $providerReference): array
{
    if(mg_payment_is_live() && $provider!=='sandbox'){
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
        $providerIntent=mg_payment_provider_retrieve_intent($provider,(string)$row['provider_intent_reference']);
        return $row+['client_secret'=>$providerIntent['client_secret']??null,'duplicate'=>true];
    }
    $providerIntent=mg_payment_provider_create_intent([
        'provider_key'=>$provider,
        'amount_cents'=>$amount,
        'currency'=>$currency,
        'idempotency_key'=>$idempotencyKey,
        'metadata'=>array_merge($request['metadata']??[],['source_type'=>$sourceType,'source_reference'=>$sourceReference]),
    ]);
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
        ->execute([$publicId,$paymentIntentId,$type,$providerReference,$amount,$currency,json_encode(['source'=>'tip_webhook'],JSON_THROW_ON_ERROR)]);
    return ['id'=>(int)$pdo->lastInsertId(),'public_id'=>$publicId,'duplicate'=>false];
}

/**
 * Legacy Stage 5 ledger writer retained only as a fail-closed compatibility symbol.
 *
 * All financial posting must use the canonical Stage 7 transaction-group authority
 * (`mg_ledger_post()` and the domain adapters in `api/finance/_posting.php`).
 */
function mg_ledger_pair(PDO $pdo, ?int $merchantUserId, ?int $orderId, string $debit, string $credit, int $amount, string $currency, string $description, ?int $refundId=null, ?int $payoutId=null): void
{
    throw new LogicException('Legacy financial_ledger_entries posting is disabled. Use the canonical Stage 7 ledger posting authority.');
}
