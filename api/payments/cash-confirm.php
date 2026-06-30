<?php
declare(strict_types=1);

require_once __DIR__ . '/_capture.php';

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);

$sessionId=trim((string)($input['session_id']??''));
if($sessionId==='')mg_fail('Checkout session is required.',422);

$pdo=mg_db();
$pdo->beginTransaction();
try{
    $stmt=$pdo->prepare(
        "SELECT cs.id session_db_id,cs.public_id session_id,cs.status session_status,
                cs.provider_key session_provider,cs.expires_at,
                o.id order_db_id,o.public_id order_id,o.payment_status,o.total_cents,o.currency,
                pi.id intent_db_id,pi.public_id payment_intent_id,pi.provider_key intent_provider,
                pi.status intent_status,pi.amount_cents intent_amount_cents,pi.currency intent_currency,
                pi.provider_intent_reference
         FROM checkout_sessions cs
         INNER JOIN commerce_orders o ON o.id=cs.order_id
         INNER JOIN payment_intents pi ON pi.id=cs.payment_intent_id AND pi.order_id=o.id
         WHERE cs.public_id=? AND o.buyer_user_id=?
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$sessionId,(int)$user['id']]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row){
        $pdo->rollBack();
        mg_fail('Checkout session not found.',404);
    }
    if((string)$row['session_provider']!=='cash'||(string)$row['intent_provider']!=='cash'){
        $pdo->rollBack();
        mg_fail('Checkout session is not a cash session.',403);
    }
    if((int)$row['intent_amount_cents']!==(int)$row['total_cents']||!hash_equals((string)$row['intent_currency'],(string)$row['currency'])){
        $pdo->rollBack();
        mg_fail('Payment intent does not match the checkout order.',409);
    }

    if((string)$row['payment_status']==='paid'){
        $pdo->commit();
        mg_ok([
            'order_id'=>$row['order_id'],
            'paid'=>true,
            'payment_intent_id'=>$row['payment_intent_id'],
            'provider_reference'=>$row['provider_intent_reference'],
            'reused'=>true,
        ],'Order already recorded.');
    }

    if(!in_array((string)$row['session_status'],['created','open'],true)){
        $pdo->rollBack();
        mg_fail('Checkout session is not open.',409);
    }
    if(!empty($row['expires_at'])&&strtotime((string)$row['expires_at'])<=time()){
        $pdo->prepare(
            "UPDATE payment_intents
             SET status='cancelled',failure_code='checkout_session_expired',
                 failure_message='The linked checkout session expired before payment.',updated_at=NOW()
             WHERE id=? AND status IN ('created','requires_payment_method','requires_action')"
        )->execute([(int)$row['intent_db_id']]);
        $pdo->prepare("UPDATE checkout_sessions SET status='expired',updated_at=NOW() WHERE id=?")
            ->execute([(int)$row['session_db_id']]);
        $pdo->commit();
        mg_fail('Checkout session has expired. Create a new payment session for this unpaid order.',409);
    }
    if(in_array((string)$row['intent_status'],['failed','cancelled','succeeded'],true)){
        $pdo->rollBack();
        mg_fail('Payment intent cannot be confirmed from its current state.',409);
    }

    $providerRef='cash_'.bin2hex(random_bytes(8));
    $pdo->prepare(
        "UPDATE checkout_sessions
         SET provider_session_reference=?,status='completed',completed_at=NOW(),updated_at=NOW()
         WHERE id=? AND status IN ('created','open')"
    )->execute([$providerRef,(int)$row['session_db_id']]);

    $result=mg_finance_record_paid_order(
        $pdo,
        (int)$row['order_db_id'],
        (int)$row['intent_db_id'],
        $providerRef,
        (int)$user['id']
    );
    $pdo->commit();

    mg_audit('commerce.payment_succeeded','commerce_order',[
        'order_id'=>$row['order_id'],
        'payment_intent_id'=>$row['payment_intent_id'],
        'provider'=>'cash',
        'issued_count'=>$result['issued_count'],
        'microgift_issued_count'=>$result['microgift_issued_count'],
    ],(int)$user['id']);

    mg_ok([
        'order_id'=>$row['order_id'],
        'paid'=>true,
        'payment_intent_id'=>$row['payment_intent_id'],
        'provider_reference'=>$providerRef,
        'issued_count'=>$result['issued_count'],
        'microgift_issued_count'=>$result['microgift_issued_count'],
        'fulfillment_status'=>$result['fulfillment_status'],
        'reused'=>false,
    ],'Cash payment recorded.');
}catch(MgCaptureWorkflowException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),$e->httpStatus);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','commerce.cash_confirmation_failed','Cash payment confirmation failed.',[
        'session_id'=>$sessionId,
        'exception_type'=>get_class($e),
    ],(int)$user['id']);
    mg_fail('Unable to confirm cash payment.',500);
}
