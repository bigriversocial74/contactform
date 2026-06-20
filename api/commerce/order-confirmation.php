<?php
declare(strict_types=1);
require_once __DIR__ . '/_checkout.php';
require_once __DIR__ . '/_order_issuance_summary.php';
mg_require_method('GET');
$user=mg_require_api_user();
$orderId=trim((string)($_GET['order_id']??$_GET['order']??''));
if($orderId==='')mg_fail('Order is required.',422);
$pdo=mg_db();
$stmt=$pdo->prepare('SELECT id,public_id,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,payment_status,fulfillment_status,source_type,source_reference,paid_at,cancelled_at,created_at,updated_at FROM commerce_orders WHERE public_id=? AND buyer_user_id=? LIMIT 1');
$stmt->execute([$orderId,(int)$user['id']]);
$order=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$order)mg_fail('Order not found.',404);
$orderPayload=mg_order_payload($pdo,$order);
$receiptStmt=$pdo->prepare('SELECT public_id receipt_id,receipt_number,status,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,items_snapshot_json,finalized_at,created_at,updated_at FROM receipts WHERE order_id=? LIMIT 1');
$receiptStmt->execute([(int)$order['id']]);
$receipt=$receiptStmt->fetch(PDO::FETCH_ASSOC)?:null;
if($receipt)$receipt['items_snapshot_json']=json_decode((string)$receipt['items_snapshot_json'],true)?:[];
$events=$pdo->prepare('SELECT event_type,actor_user_id,metadata_json,created_at FROM order_events WHERE order_id=? ORDER BY id DESC LIMIT 12');
$events->execute([(int)$order['id']]);
$history=$pdo->prepare('SELECT domain,old_status,new_status,reason,created_at FROM order_status_history WHERE order_id=? ORDER BY id DESC LIMIT 12');
$history->execute([(int)$order['id']]);
mg_ok([
    'order'=>$orderPayload,
    'receipt'=>$receipt,
    'issuance'=>mg_order_issuance_summary($pdo,$order,(int)$user['id']),
    'events'=>$events->fetchAll(PDO::FETCH_ASSOC),
    'history'=>$history->fetchAll(PDO::FETCH_ASSOC),
    'links'=>[
        'commerce_center'=>'/account-commerce.php',
        'orders'=>'/account/orders.php',
        'action_center'=>'/inbox.php',
        'cart'=>'/cart.php',
    ],
]);
