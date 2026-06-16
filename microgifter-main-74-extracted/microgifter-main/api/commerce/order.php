<?php
declare(strict_types=1);
require_once __DIR__ . '/_foundation.php';
mg_require_method('GET');
$user=mg_require_api_user();
$orderId=trim((string)($_GET['order_id']??''));
if($orderId==='')mg_fail('Order is required.',422);
$pdo=mg_db();
$stmt=$pdo->prepare('SELECT id,public_id,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,payment_status,fulfillment_status,source_type,source_reference,paid_at,cancelled_at,created_at,updated_at FROM commerce_orders WHERE public_id=? AND buyer_user_id=? LIMIT 1');
$stmt->execute([$orderId,(int)$user['id']]);$order=$stmt->fetch();if(!$order)mg_fail('Order not found.',404);
mg_ok(mg_order_payload($pdo,$order));
