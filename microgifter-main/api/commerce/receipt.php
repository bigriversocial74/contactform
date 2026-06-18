<?php
declare(strict_types=1);
require_once __DIR__ . '/_foundation.php';
mg_require_method('GET');
$user=mg_require_api_user();
$orderId=trim((string)($_GET['order_id']??''));
if($orderId==='')mg_fail('Order is required.',422);
$pdo=mg_db();
$stmt=$pdo->prepare('SELECT r.public_id receipt_id,r.receipt_number,r.status,r.currency,r.subtotal_cents,r.discount_cents,r.tax_cents,r.platform_fee_cents,r.total_cents,r.buyer_snapshot_json,r.merchant_snapshot_json,r.items_snapshot_json,r.finalized_at,r.created_at,r.updated_at FROM receipts r INNER JOIN commerce_orders o ON o.id=r.order_id WHERE o.public_id=? AND o.buyer_user_id=? LIMIT 1');
$stmt->execute([$orderId,(int)$user['id']]);$receipt=$stmt->fetch();if(!$receipt)mg_fail('Receipt not found.',404);
foreach(['buyer_snapshot_json','merchant_snapshot_json','items_snapshot_json'] as $field){$receipt[$field]=json_decode((string)$receipt[$field],true);}
mg_ok(['order_id'=>$orderId,'receipt'=>$receipt]);
