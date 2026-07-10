<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/_issuance_reconciliation.php';

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$orderId=trim((string)($input['order_id']??''));
if($orderId==='')mg_fail('Order is required.',422);

$pdo=mg_db();
$lookup=$pdo->prepare('SELECT id,payment_status FROM commerce_orders WHERE public_id=? AND buyer_user_id=? LIMIT 1');
$lookup->execute([$orderId,(int)$user['id']]);
$order=$lookup->fetch(PDO::FETCH_ASSOC);
if(!$order)mg_fail('Order not found.',404);
if((string)$order['payment_status']!=='paid')mg_fail('Only paid orders can reconcile delivery.',409);

try{
    $pdo->beginTransaction();
    $result=mg_payment_reconcile_paid_order($pdo,(int)$order['id'],(int)$user['id'],'buyer_requested_reconciliation');
    $pdo->commit();
    mg_audit('commerce.order_issuance_reconciled','commerce_order',[
        'order_id'=>$orderId,
        'complete'=>$result['complete']??false,
        'fulfillment_status'=>$result['fulfillment_status']??null,
    ],(int)$user['id']);
    mg_ok($result,!empty($result['complete'])?'Delivery verified.':'Delivery reconciliation completed with items still pending.');
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','commerce.order_issuance_reconcile_failed','Order issuance reconciliation failed.',[
        'order_id'=>$orderId,
        'exception_type'=>get_class($error),
    ],(int)$user['id']);
    mg_fail('Unable to reconcile order delivery.',500);
}
