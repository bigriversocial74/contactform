<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/commerce/_foundation.php';
require_once __DIR__ . '/_capture.php';
require_once __DIR__ . '/_stripe.php';

final class MgCheckoutSessionException extends RuntimeException
{
    public function __construct(string $message,public readonly int $httpStatus=409)
    {
        parent::__construct($message);
    }
}

function mg_payment_expire_checkout_sessions(PDO $pdo,int $orderId): void
{
    $pdo->prepare(
        "UPDATE payment_intents pi
         INNER JOIN checkout_sessions cs ON cs.payment_intent_id=pi.id
         SET pi.status='cancelled',
             pi.failure_code='checkout_session_expired',
             pi.failure_message='The linked checkout session expired before payment.',
             pi.updated_at=NOW()
         WHERE cs.order_id=?
           AND cs.status IN ('created','open')
           AND cs.expires_at IS NOT NULL AND cs.expires_at<=NOW()
           AND pi.status IN ('created','requires_payment_method','requires_action')"
    )->execute([$orderId]);
    $pdo->prepare(
        "UPDATE checkout_sessions
         SET status='expired',updated_at=NOW()
         WHERE order_id=? AND status IN ('created','open')
           AND expires_at IS NOT NULL AND expires_at<=NOW()"
    )->execute([$orderId]);
}

function mg_payment_checkout_session_payload(array $session,string $orderPublicId,bool $duplicate): array
{
    $external=trim((string)($session['provider_checkout_url']??''));
    return [
        'order_id'=>$orderPublicId,
        'checkout_session_id'=>(string)$session['checkout_session_id'],
        'payment_intent_id'=>(string)$session['payment_intent_id'],
        'provider'=>(string)$session['provider_key'],
        'mode'=>mg_payment_is_live()?'live':'test',
        'checkout_url'=>$external!==''?$external:'/checkout.php?session='.rawurlencode((string)$session['checkout_session_id']),
        'provider_session_reference'=>(string)($session['provider_session_reference']??''),
        'expires_at'=>$session['expires_at'],
        'duplicate'=>$duplicate,
    ];
}

function mg_payment_create_checkout_session(PDO $pdo,int $buyerUserId,string $orderPublicId,string $idempotencyKey,array $options=[]): array
{
    $orderPublicId=trim($orderPublicId);
    $idempotencyKey=trim($idempotencyKey);
    if($orderPublicId===''||$idempotencyKey===''||mb_strlen($idempotencyKey)>190){
        throw new MgCheckoutSessionException('Order and idempotency key are required.',422);
    }

    $stmt=$pdo->prepare('SELECT * FROM commerce_orders WHERE public_id=? AND buyer_user_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$orderPublicId,$buyerUserId]);
    $order=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order)throw new MgCheckoutSessionException('Order not found.',404);
    if((string)$order['payment_status']!=='unpaid')throw new MgCheckoutSessionException('Order is not awaiting payment.',409);

    try{
        $provider=mg_payment_checkout_provider_key($pdo,(string)($options['provider_key']??''));
    }catch(Throwable $error){
        throw new MgCheckoutSessionException($error->getMessage(),409);
    }
    mg_payment_expire_checkout_sessions($pdo,(int)$order['id']);

    $idempotent=$pdo->prepare(
        "SELECT pi.id,pi.public_id payment_intent_id,pi.order_id,pi.idempotency_key,
                cs.public_id checkout_session_id,cs.provider_key,cs.provider_session_reference,
                cs.provider_checkout_url,cs.status session_status,cs.expires_at
         FROM payment_intents pi
         LEFT JOIN checkout_sessions cs ON cs.payment_intent_id=pi.id
         WHERE pi.provider_key=? AND pi.idempotency_key=?
         LIMIT 1 FOR UPDATE"
    );
    $idempotent->execute([$provider,$idempotencyKey]);
    if($existing=$idempotent->fetch(PDO::FETCH_ASSOC)){
        if((int)$existing['order_id']!==(int)$order['id']){
            throw new MgCheckoutSessionException('Payment idempotency key is already bound to another order.',409);
        }
        if(empty($existing['checkout_session_id'])){
            throw new MgCheckoutSessionException('Payment intent exists without a checkout session.',409);
        }
        if(!in_array((string)$existing['session_status'],['created','open'],true)){
            throw new MgCheckoutSessionException('Payment idempotency key is bound to a closed checkout session.',409);
        }
        return mg_payment_checkout_session_payload($existing,$orderPublicId,true);
    }

    $active=$pdo->prepare(
        "SELECT cs.public_id checkout_session_id,cs.provider_key,cs.provider_session_reference,
                cs.provider_checkout_url,cs.expires_at,pi.public_id payment_intent_id,pi.idempotency_key
         FROM checkout_sessions cs
         INNER JOIN payment_intents pi ON pi.id=cs.payment_intent_id
         WHERE cs.order_id=? AND cs.provider_key=? AND cs.status IN ('created','open')
           AND cs.expires_at>NOW()
         ORDER BY cs.id DESC LIMIT 1 FOR UPDATE"
    );
    $active->execute([(int)$order['id'],$provider]);
    if($activeSession=$active->fetch(PDO::FETCH_ASSOC)){
        return mg_payment_checkout_session_payload($activeSession,$orderPublicId,true);
    }

    try{$account=mg_payment_assert_checkout_ready($pdo,$order,$provider);}catch(Throwable $error){
        throw new MgCheckoutSessionException($error->getMessage(),409);
    }
    $itemsStmt=$pdo->prepare('SELECT title_snapshot,quantity,unit_amount_cents,line_total_cents,currency FROM commerce_order_items WHERE order_id=? ORDER BY id');
    $itemsStmt->execute([(int)$order['id']]);
    $items=$itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    if($items===[])throw new MgCheckoutSessionException('Order has no checkout items.',409);

    $intentPublicId=mg_public_uuid();
    $sessionPublicId=mg_public_uuid();
    $expires=(new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');
    $destination=$account?(string)$account['provider_account_reference']:null;

    $pdo->prepare(
        "INSERT INTO payment_intents
         (public_id,order_id,provider_key,amount_cents,currency,application_fee_cents,destination_account_reference,status,capture_method,idempotency_key,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,'created','automatic',?,NOW(),NOW())"
    )->execute([
        $intentPublicId,
        (int)$order['id'],
        $provider,
        (int)$order['total_cents'],
        (string)$order['currency'],
        (int)$order['platform_fee_cents'],
        $destination,
        $idempotencyKey,
    ]);
    $intentDbId=(int)$pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO checkout_sessions
         (public_id,order_id,payment_intent_id,provider_key,status,success_url,cancel_url,expires_at,created_at,updated_at)
         VALUES (?,?,?,?,'open',?,?,?,NOW(),NOW())"
    )->execute([
        $sessionPublicId,
        (int)$order['id'],
        $intentDbId,
        $provider,
        $options['success_url']??'/checkout-success.php',
        $options['cancel_url']??'/cart.php',
        $expires,
    ]);

    $providerSession=[
        'provider_session_reference'=>'',
        'provider_intent_reference'=>'',
        'checkout_url'=>'',
        'expires_at'=>$expires,
    ];
    if($provider==='stripe'){
        try{
            $providerSession=mg_stripe_checkout_session($pdo,$order,$items,$account??[],[
                'payment_intent_id'=>$intentPublicId,
                'checkout_session_id'=>$sessionPublicId,
                'idempotency_key'=>$idempotencyKey,
            ],$options);
        }catch(MgStripeProviderException $error){
            throw new MgCheckoutSessionException($error->getMessage(),$error->httpStatus);
        }
        $pdo->prepare("UPDATE checkout_sessions SET provider_session_reference=?,provider_checkout_url=?,expires_at=?,updated_at=NOW() WHERE public_id=?")
            ->execute([$providerSession['provider_session_reference'],$providerSession['checkout_url'],$providerSession['expires_at'],$sessionPublicId]);
        $pdo->prepare("UPDATE payment_intents SET provider_intent_reference=NULLIF(?,''),status='requires_action',updated_at=NOW() WHERE id=?")
            ->execute([$providerSession['provider_intent_reference'],$intentDbId]);
        $expires=(string)$providerSession['expires_at'];
    }

    mg_order_event($pdo,(int)$order['id'],'payment.checkout_session_created',$buyerUserId,[
        'checkout_session_id'=>$sessionPublicId,
        'payment_intent_id'=>$intentPublicId,
        'provider'=>$provider,
        'provider_session_reference'=>$providerSession['provider_session_reference'],
        'application_fee_cents'=>(int)$order['platform_fee_cents'],
        'destination_account_reference'=>$destination,
    ]);

    return mg_payment_checkout_session_payload([
        'checkout_session_id'=>$sessionPublicId,
        'payment_intent_id'=>$intentPublicId,
        'provider_key'=>$provider,
        'provider_session_reference'=>$providerSession['provider_session_reference'],
        'provider_checkout_url'=>$providerSession['checkout_url'],
        'expires_at'=>$expires,
    ],$orderPublicId,false);
}
