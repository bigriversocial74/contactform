<?php
declare(strict_types=1);
require_once __DIR__ . '/_checkout.php';
$user=mg_require_api_user();
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$pdo=mg_db();
if($method==='GET'){
    $stmt=$pdo->prepare('SELECT id,public_id,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,payment_status,fulfillment_status,source_type,source_reference,paid_at,cancelled_at,created_at,updated_at FROM commerce_orders WHERE buyer_user_id=? ORDER BY created_at DESC,id DESC LIMIT 200');
    $stmt->execute([(int)$user['id']]);$orders=[];
    foreach($stmt->fetchAll() as $order)$orders[]=mg_order_payload($pdo,$order);
    mg_ok(['orders'=>$orders]);
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);
$draftId=trim((string)($input['checkout_draft_id']??''));$idempotency=trim((string)($input['idempotency_key']??''));
$pdo->beginTransaction();
try{
    $result=mg_checkout_create_order($pdo,(int)$user['id'],$draftId,$idempotency);
    $pdo->commit();
    if(!$result['duplicate'])mg_audit('commerce.order_created','commerce_order',['order_id'=>$result['order']['order_id'],'checkout_draft_id'=>$draftId],(int)$user['id']);
    mg_ok($result['order'],$result['duplicate']?'Order already created.':'Order created.',$result['duplicate']?200:201);
}catch(MgCheckoutWorkflowException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),$e->httpStatus);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail('Unable to create order.',500);
}
