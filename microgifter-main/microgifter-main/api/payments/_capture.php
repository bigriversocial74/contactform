<?php
declare(strict_types=1);
require_once __DIR__ . '/_payments.php';
require_once __DIR__ . '/_fulfillment.php';
require_once dirname(__DIR__) . '/finance/_posting.php';

final class MgCaptureWorkflowException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus=409)
    {
        parent::__construct($message);
    }
}

function mg_finance_record_paid_order(PDO $pdo,int $orderDbId,int $intentDbId,string $providerReference,?int $actorUserId=null,?callable $failureHook=null): array
{
    $providerReference=trim($providerReference);
    if($providerReference==='')throw new MgCaptureWorkflowException('Provider payment reference is required.',422);

    $stmt=$pdo->prepare('SELECT * FROM commerce_orders WHERE id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$orderDbId]);$order=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order)throw new MgCaptureWorkflowException('Commerce order not found.',404);

    $intentStmt=$pdo->prepare('SELECT * FROM payment_intents WHERE id=? LIMIT 1 FOR UPDATE');
    $intentStmt->execute([$intentDbId]);$intent=$intentStmt->fetch(PDO::FETCH_ASSOC);
    if(!$intent)throw new MgCaptureWorkflowException('Payment intent not found.',404);
    if((int)$intent['order_id']!==$orderDbId||(int)$intent['amount_cents']!==(int)$order['total_cents']||!hash_equals((string)$intent['currency'],(string)$order['currency'])){
        throw new MgCaptureWorkflowException('Payment intent does not match the commerce order.',409);
    }

    $paymentTransitioned=false;
    if((string)$order['payment_status']==='paid'){
        $recorded=trim((string)($intent['provider_intent_reference']??''));
        if($recorded===''||!hash_equals($recorded,$providerReference))throw new MgCaptureWorkflowException('Capture replay conflicts with the recorded provider payment.',409);
    }else{
        if(in_array((string)$intent['status'],['failed','cancelled'],true))throw new MgCaptureWorkflowException('Payment intent cannot be captured from its current state.',409);
        $pdo->prepare("UPDATE payment_intents SET provider_intent_reference=?,status='succeeded',authorized_at=COALESCE(authorized_at,NOW()),captured_at=COALESCE(captured_at,NOW()),failure_code=NULL,failure_message=NULL,updated_at=NOW() WHERE id=?")
            ->execute([$providerReference,$intentDbId]);
        $pdo->prepare("UPDATE commerce_orders SET payment_status='paid',paid_at=COALESCE(paid_at,NOW()),updated_at=NOW() WHERE id=?")
            ->execute([$orderDbId]);
        $pdo->prepare("INSERT INTO payment_transactions (public_id,payment_intent_id,transaction_type,provider_reference,amount_cents,currency,status,occurred_at,created_at) VALUES (?,?,'sale',?,?,?,'succeeded',NOW(),NOW())")
            ->execute([mg_public_uuid(),$intentDbId,$providerReference,(int)$order['total_cents'],(string)$order['currency']]);
        $group=mg_stage7_post_paid_order($pdo,$order,$actorUserId);
        if($failureHook)$failureHook('after_ledger',['order'=>$order,'intent'=>$intent,'group'=>$group]);
        $metadata=json_encode(['provider_reference'=>$providerReference],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $pdo->prepare("INSERT INTO order_status_history (public_id,order_id,status_domain,from_status,to_status,actor_type,actor_user_id,reason_code,metadata_json,created_at) VALUES (?,?,'payment','unpaid','paid','provider',?,'payment_captured',?,NOW())")
            ->execute([mg_public_uuid(),$orderDbId,$actorUserId,$metadata]);
        $pdo->prepare("INSERT INTO order_audit_events (public_id,order_id,event_type,actor_user_id,payload_json,created_at) VALUES (?,?,'payment.captured',?,?,NOW())")
            ->execute([mg_public_uuid(),$orderDbId,$actorUserId,$metadata]);
        $pdo->prepare("UPDATE receipts SET status='finalized',finalized_at=COALESCE(finalized_at,NOW()),updated_at=NOW() WHERE order_id=? AND status='pending'")
            ->execute([$orderDbId]);
        $paymentTransitioned=true;
    }

    $issued=mg_payment_issue_order_pppm($pdo,$orderDbId,$actorUserId ?: (int)$order['buyer_user_id']);
    $microgifts=mg_payment_issue_order_microgifts($pdo,$orderDbId,$actorUserId ?: (int)$order['buyer_user_id']);
    if($failureHook)$failureHook('after_fulfillment',['order'=>$order,'intent'=>$intent,'issued'=>$issued,'microgifts'=>$microgifts]);

    if($paymentTransitioned){
        $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,created_at) VALUES (?,?,?,?,?,?,NOW())')
            ->execute([mg_public_uuid(),(int)$order['buyer_user_id'],'payment_succeeded','Payment received','Your order was paid and gift items are being issued.','/checkout-success.php?order='.rawurlencode((string)$order['public_id'])]);
        $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,created_at) VALUES (?,?,?,?,?,?,NOW())')
            ->execute([mg_public_uuid(),(int)$order['merchant_user_id'],'merchant_payment_received','Payment received','A customer payment was captured and PPPM issuance was started.','/merchant-payments.php']);
    }

    return ['order_id'=>(string)$order['public_id'],'issued_count'=>(int)($issued['issued_count']??0),'microgift_issued_count'=>(int)($microgifts['issued_count']??0),'fulfillment_status'=>$issued['fulfillment_status']??null,'payment_transitioned'=>$paymentTransitioned,'provider_reference'=>$providerReference];
}
