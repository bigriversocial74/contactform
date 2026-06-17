<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/commerce/_foundation.php';
require_once __DIR__ . '/_capture.php';

final class MgCheckoutSessionException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus=409)
    {
        parent::__construct($message);
    }
}

function mg_payment_create_checkout_session(PDO $pdo,int $buyerUserId,string $orderPublicId,string $idempotencyKey,array $options=[]): array
{
    $orderPublicId=trim($orderPublicId);$idempotencyKey=trim($idempotencyKey);
    if($orderPublicId===''||$idempotencyKey===''||mb_strlen($idempotencyKey)>190)throw new MgCheckoutSessionException('Order and idempotency key are required.',422);

    $stmt=$pdo->prepare("SELECT * FROM commerce_orders WHERE public_id=? AND buyer_user_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$orderPublicId,$buyerUserId]);$order=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order)throw new MgCheckoutSessionException('Order not found.',404);
    if((string)$order['payment_status']!=='unpaid')throw new MgCheckoutSessionException('Order is not awaiting payment.',409);

    $existing=$pdo->prepare("SELECT cs.public_id checkout_session_id,pi.public_id payment_intent_id,pi.idempotency_key,cs.provider_key,cs.expires_at FROM checkout_sessions cs INNER JOIN payment_intents pi ON pi.order_id=cs.order_id WHERE cs.order_id=? AND cs.status IN ('created','open') AND cs.expires_at>NOW() ORDER BY cs.id DESC,pi.id DESC LIMIT 1 FOR UPDATE");
    $existing->execute([(int)$order['id']]);
    if($session=$existing->fetch(PDO::FETCH_ASSOC)){
        if(!hash_equals((string)$session['idempotency_key'],$idempotencyKey))throw new MgCheckoutSessionException('Payment session idempotency key is already bound to this order.',409);
        unset($session['idempotency_key']);
        return $session+['checkout_url'=>'/checkout.php?session='.rawurlencode((string)$session['checkout_session_id']),'duplicate'=>true,'order_id'=>$orderPublicId];
    }

    $provider=mg_payment_provider_key();$intentPublicId=mg_public_uuid();$sessionPublicId=mg_public_uuid();
    $expires=(new DateTimeImmutable('+30 minutes'))->format('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO payment_intents (public_id,order_id,provider_key,amount_cents,currency,status,capture_method,idempotency_key,created_at,updated_at) VALUES (?,?,?,?,?,'created','automatic',?,NOW(),NOW())")
        ->execute([$intentPublicId,(int)$order['id'],$provider,(int)$order['total_cents'],$order['currency'],$idempotencyKey]);
    $pdo->prepare("INSERT INTO checkout_sessions (public_id,order_id,provider_key,status,success_url,cancel_url,expires_at,created_at,updated_at) VALUES (?,?,?,'open',?,?,?,NOW(),NOW())")
        ->execute([$sessionPublicId,(int)$order['id'],$provider,$options['success_url']??'/checkout-success.php',$options['cancel_url']??'/cart.php',$expires]);
    mg_order_event($pdo,(int)$order['id'],'payment.checkout_session_created',$buyerUserId,['checkout_session_id'=>$sessionPublicId,'payment_intent_id'=>$intentPublicId,'provider'=>$provider]);
    return ['order_id'=>$orderPublicId,'checkout_session_id'=>$sessionPublicId,'payment_intent_id'=>$intentPublicId,'provider'=>$provider,'mode'=>mg_payment_is_live()?'live':'test','checkout_url'=>'/checkout.php?session='.rawurlencode($sessionPublicId),'expires_at'=>$expires,'duplicate'=>false];
}
