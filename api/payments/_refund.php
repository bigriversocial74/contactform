<?php
declare(strict_types=1);
require_once __DIR__ . '/_payments.php';
require_once dirname(__DIR__) . '/finance/_posting.php';
require_once dirname(__DIR__) . '/entitlements/_entitlements.php';
require_once dirname(__DIR__) . '/microgifts/_payment_reconciliation.php';

final class MgRefundException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 409)
    {
        parent::__construct($message);
    }
}

function mg_finance_refund_order(PDO $pdo, int $merchantUserId, int $actorUserId, array $input, ?callable $failureHook = null): array
{
    $orderPublicId=trim((string)($input['order_id']??''));
    $amountCents=(int)($input['amount_cents']??0);
    $reason=trim((string)($input['reason']??'requested_by_customer'));
    $idempotencyKey=trim((string)($input['idempotency_key']??''));
    $reasons=['requested_by_customer','duplicate','fraudulent','product_unavailable','merchant_error','other'];
    if($orderPublicId===''||$amountCents<1||$idempotencyKey===''||!in_array($reason,$reasons,true)){
        throw new MgRefundException('Invalid refund request.',422);
    }

    $stmt=$pdo->prepare("SELECT o.id order_db_id,o.public_id,o.buyer_user_id,o.merchant_user_id,o.total_cents,o.currency,o.payment_status,pi.id intent_db_id,pi.status intent_status FROM commerce_orders o INNER JOIN payment_intents pi ON pi.order_id=o.id AND pi.status='succeeded' WHERE o.public_id=? AND o.merchant_user_id=? LIMIT 1 FOR UPDATE");
    $stmt->execute([$orderPublicId,$merchantUserId]);
    $order=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order)throw new MgRefundException('Paid order not found.',404);

    $existing=$pdo->prepare('SELECT * FROM payment_refunds WHERE merchant_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([$merchantUserId,$idempotencyKey]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC)){
        $exact=(int)$row['order_id']===(int)$order['order_db_id']&&(int)$row['amount_cents']===$amountCents&&(string)$row['reason']===$reason;
        if(!$exact)throw new MgRefundException('Idempotency key conflicts with an existing refund.',409);
        return ['refund_id'=>(string)$row['public_id'],'status'=>(string)$row['status'],'duplicate'=>true,'entitlement_policy'=>null,'microgift_reconciliation'=>null];
    }

    $sum=$pdo->prepare("SELECT COALESCE(SUM(amount_cents),0) FROM payment_refunds WHERE order_id=? AND status IN ('pending','processing','succeeded')");
    $sum->execute([(int)$order['order_db_id']]);
    $already=(int)$sum->fetchColumn();
    if($amountCents>(int)$order['total_cents']-$already)throw new MgRefundException('Refund exceeds the remaining paid amount.',409);

    $refundPublicId=mg_public_uuid();
    $status=mg_payment_provider_key()==='sandbox'&&!mg_payment_is_live()?'succeeded':'pending';
    $pdo->prepare("INSERT INTO payment_refunds (public_id,order_id,payment_intent_id,merchant_user_id,amount_cents,currency,reason,status,idempotency_key,requested_by_user_id,processed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,CASE WHEN ?='succeeded' THEN NOW() ELSE NULL END,NOW(),NOW())")
        ->execute([$refundPublicId,(int)$order['order_db_id'],(int)$order['intent_db_id'],$merchantUserId,$amountCents,(string)$order['currency'],$reason,$status,$idempotencyKey,$actorUserId,$status]);

    $policy=null;$microgifts=null;$newOrderStatus=(string)$order['payment_status'];
    if($status==='succeeded'){
        $newTotal=$already+$amountCents;
        $newOrderStatus=$newTotal>=(int)$order['total_cents']?'refunded':'partially_refunded';
        $pdo->prepare('UPDATE commerce_orders SET payment_status=?,updated_at=NOW() WHERE id=?')->execute([$newOrderStatus,(int)$order['order_db_id']]);
        $ledger=mg_stage7_post_refund($pdo,$order,$refundPublicId,$amountCents,$actorUserId);
        if($failureHook)$failureHook('after_ledger',['order'=>$order,'refund_id'=>$refundPublicId,'ledger'=>$ledger]);
        $policy=mg_entitlements_apply_refund_policy($pdo,$order,$amountCents,$newTotal,$actorUserId);
        $microgifts=mg_microgift_payment_reconcile_order($pdo,$order,$newOrderStatus==='refunded'?'full_refund':'partial_refund',$refundPublicId,$actorUserId,[
            'amount_cents'=>$amountCents,
            'total_refunded_cents'=>$newTotal,
            'reason'=>$reason,
        ]);
        if($failureHook)$failureHook('after_microgift_reconciliation',['order'=>$order,'refund_id'=>$refundPublicId,'microgifts'=>$microgifts]);
        $metadata=json_encode(['refund_id'=>$refundPublicId,'amount_cents'=>$amountCents,'reason'=>$reason,'entitlement_policy'=>$policy,'microgift_reconciliation'=>$microgifts],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $pdo->prepare("INSERT INTO order_status_history (public_id,order_id,status_domain,from_status,to_status,actor_type,actor_user_id,reason_code,metadata_json,created_at) VALUES (?,?,'payment',?,?,'merchant',?,'refund_succeeded',?,NOW())")
            ->execute([mg_public_uuid(),(int)$order['order_db_id'],(string)$order['payment_status'],$newOrderStatus,$actorUserId,$metadata]);
        $pdo->prepare("INSERT INTO order_audit_events (public_id,order_id,event_type,actor_user_id,payload_json,created_at) VALUES (?,?,'payment.refunded',?,?,NOW())")
            ->execute([mg_public_uuid(),(int)$order['order_db_id'],$actorUserId,$metadata]);
        $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,created_at) VALUES (?,?,?,?,?,?,NOW())')
            ->execute([mg_public_uuid(),(int)$order['buyer_user_id'],'payment_refunded','Refund processed','A refund was processed for your order.','/account-orders.php']);
        $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,created_at) VALUES (?,?,?,?,?,?,NOW())')
            ->execute([mg_public_uuid(),$merchantUserId,'merchant_refund_processed','Refund processed','A customer order refund was processed.','/merchant-payments.php']);
        if($failureHook)$failureHook('before_complete',['order'=>$order,'refund_id'=>$refundPublicId]);
    }

    return ['refund_id'=>$refundPublicId,'status'=>$status,'duplicate'=>false,'order_status'=>$newOrderStatus,'entitlement_policy'=>$policy,'microgift_reconciliation'=>$microgifts];
}
