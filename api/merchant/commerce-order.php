<?php
declare(strict_types=1);

require_once __DIR__ . '/_orders.php';

mg_require_method('GET');
$user = mg_merchant_require_permission('merchant.payments.view');
$orderId = mg_merchant_orders_text($_GET['order_id'] ?? '', 64);
if ($orderId === '') mg_fail('Order is required.', 422);

$pdo = mg_db();
mg_ok(mg_merchant_order_detail($pdo, (int) $user['id'], $orderId));
