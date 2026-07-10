<?php
declare(strict_types=1);

require_once __DIR__ . '/_capture.php';

function mg_local_checkout_column_exists(PDO $pdo,string $table,string $column): bool
{
    try{
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table,$column]);
        return (int)$stmt->fetchColumn()>0;
    }catch(Throwable){
        return false;
    }
}

function mg_payment_confirm_local_session(PDO $pdo,int $buyerUserId,string $sessionId,string $provider,int $actorUserId): array
{
    $sessionId=trim($sessionId);
    if($sessionId==='')throw new MgCaptureWorkflowException('Checkout session is required.',422);
    if(!in_array($provider,['cash','sandbox'],true))throw new MgCaptureWorkflowException('Unsupported local payment provider.',422);

    $hasPaymentIntentId=mg_local_checkout_column_exists($pdo,'checkout_sessions','payment_intent_id');
    $paymentJoin=$hasPaymentIntentId
        ? 'INNER JOIN payment_intents pi ON pi.id=cs.payment_intent_id AND pi.order_id=o.id'
        : 'INNER JOIN payment_intents pi ON pi.order_id=o.id AND pi.provider_key=cs.provider_key';
    $stmt=$pdo->prepare(
        "SELECT cs.id session_db_id,cs.public_id session_id,cs.status session_status,
                cs.provider_key session_provider,cs.expires_at,
                o.id order_db_id,o.public_id order_id,o.payment_status,o.total_cents,o.currency,
                pi.id intent_db_id,pi.public_id payment_intent_id,pi.provider_key intent_provider,
                pi.status intent_status,pi.amount_cents intent_amount_cents,pi.currency intent_currency,
                pi.provider_intent_reference
         FROM checkout_sessions cs
         INNER JOIN commerce_orders o ON o.id=cs.order_id
         {$paymentJoin}
         WHERE cs.public_id=? AND o.buyer_user_id=?
         ORDER BY pi.id DESC LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$sessionId,$buyerUserId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new MgCaptureWorkflowException('Checkout session not found.',404);
    if((string)$row['session_provider']!==$provider||(string)$row['intent_provider']!==$provider){
        throw new MgCaptureWorkflowException('Checkout session does not match the selected payment method.',403);
    }
    if((int)$row['intent_amount_cents']!==(int)$row['total_cents']||!hash_equals((string)$row['intent_currency'],(string)$row['currency'])){
        throw new MgCaptureWorkflowException('Payment intent does not match the checkout order.',409);
    }

    if((string)$row['payment_status']==='paid'){
        $providerReference=trim((string)($row['provider_intent_reference']??''));
        if($providerReference==='')throw new MgCaptureWorkflowException('Paid order is missing its provider payment reference.',409);
        $capture=mg_finance_record_paid_order($pdo,(int)$row['order_db_id'],(int)$row['intent_db_id'],$providerReference,$actorUserId);
        return $capture+[
            'payment_intent_id'=>(string)$row['payment_intent_id'],
            'paid'=>true,
            'reused'=>true,
        ];
    }

    if(!in_array((string)$row['session_status'],['created','open'],true)){
        throw new MgCaptureWorkflowException('Checkout session is not open.',409);
    }
    if(!empty($row['expires_at'])&&strtotime((string)$row['expires_at'])<=time()){
        $pdo->prepare("UPDATE payment_intents SET status='cancelled',failure_code='checkout_session_expired',failure_message='The linked checkout session expired before payment.',updated_at=NOW() WHERE id=? AND status IN ('created','requires_payment_method','requires_action')")
            ->execute([(int)$row['intent_db_id']]);
        $pdo->prepare("UPDATE checkout_sessions SET status='expired',updated_at=NOW() WHERE id=?")
            ->execute([(int)$row['session_db_id']]);
        throw new MgCaptureWorkflowException('Checkout session has expired. Create a new payment session for this unpaid order.',409);
    }
    if(in_array((string)$row['intent_status'],['failed','cancelled','succeeded'],true)){
        throw new MgCaptureWorkflowException('Payment intent cannot be confirmed from its current state.',409);
    }

    $providerReference=$provider.'_'.bin2hex(random_bytes(8));
    $pdo->prepare("UPDATE checkout_sessions SET provider_session_reference=?,status='completed',completed_at=NOW(),updated_at=NOW() WHERE id=? AND status IN ('created','open')")
        ->execute([$providerReference,(int)$row['session_db_id']]);
    $capture=mg_finance_record_paid_order($pdo,(int)$row['order_db_id'],(int)$row['intent_db_id'],$providerReference,$actorUserId);
    return $capture+[
        'payment_intent_id'=>(string)$row['payment_intent_id'],
        'paid'=>true,
        'reused'=>false,
    ];
}
