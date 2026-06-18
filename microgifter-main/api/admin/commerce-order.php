<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/commerce/_foundation.php';
mg_require_method('GET');
$user=mg_require_api_user();
if(!in_array('super_admin',$user['roles']??[],true))mg_fail('Permission denied.',403);
$orderId=trim((string)($_GET['order_id']??''));if($orderId==='')mg_fail('Order is required.',422);
$pdo=mg_db();$stmt=$pdo->prepare('SELECT id,public_id,buyer_user_id,merchant_user_id,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,payment_status,fulfillment_status,source_type,source_reference,paid_at,cancelled_at,created_at,updated_at FROM commerce_orders WHERE public_id=? LIMIT 1');$stmt->execute([$orderId]);$order=$stmt->fetch();if(!$order)mg_fail('Order not found.',404);
$events=$pdo->prepare('SELECT public_id event_id,event_type,actor_user_id,payload_json,created_at FROM order_audit_events WHERE order_id=? ORDER BY created_at,id');$events->execute([(int)$order['id']]);$payload=mg_order_payload($pdo,$order);$payload['audit_events']=$events->fetchAll();mg_ok($payload);
