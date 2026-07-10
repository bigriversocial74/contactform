<?php
declare(strict_types=1);

require_once __DIR__ . '/_orders.php';
require_once dirname(__DIR__) . '/payments/_issuance_reconciliation.php';

mg_require_method('POST');
$user = mg_merchant_require_permission('merchant.payments.view');
$input = mg_input();
mg_require_csrf_for_write($input);
$orderId = mg_merchant_orders_text($input['order_id'] ?? '', 64);
$requestKey = mg_merchant_orders_text($input['request_key'] ?? '', 190);
if ($orderId === '') mg_fail('Order is required.', 422);
if ($requestKey !== '' && preg_match('/^[a-zA-Z0-9:._-]{8,190}$/', $requestKey) !== 1) mg_fail('Invalid reconciliation request key.', 422);

$pdo = mg_db();
$lookup = $pdo->prepare('SELECT id,payment_status FROM commerce_orders WHERE public_id=? AND merchant_user_id=? LIMIT 1');
$lookup->execute([$orderId, (int) $user['id']]);
$order = $lookup->fetch(PDO::FETCH_ASSOC);
if (!$order) mg_fail('Order not found.', 404);
if ((string) $order['payment_status'] !== 'paid') mg_fail('Only paid orders can verify or repair delivery.', 409);

try {
    $pdo->beginTransaction();
    $result = mg_payment_reconcile_paid_order(
        $pdo,
        (int) $order['id'],
        (int) $user['id'],
        'merchant_order_operations_reconciliation'
    );
    $pdo->commit();

    mg_audit('merchant.commerce_order_delivery_reconciled', 'commerce_order', [
        'order_id' => $orderId,
        'request_key' => $requestKey !== '' ? hash('sha256', $requestKey) : null,
        'complete' => $result['complete'] ?? false,
        'fulfillment_status' => $result['fulfillment_status'] ?? null,
        'changed' => $result['changed'] ?? false,
    ], (int) $user['id']);

    mg_ok(
        $result,
        !empty($result['complete'])
            ? 'Order delivery verified.'
            : 'Delivery reconciliation completed with items still pending.'
    );
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.commerce_order_reconcile_failed', 'Merchant order delivery reconciliation failed.', [
        'order_id' => $orderId,
        'exception_type' => get_class($error),
    ], (int) $user['id']);
    mg_fail('Unable to reconcile order delivery.', 500);
}
