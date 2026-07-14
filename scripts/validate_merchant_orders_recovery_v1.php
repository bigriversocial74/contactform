<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'page' => $root . '/merchant-orders.php',
    'view' => $root . '/includes/merchant-orders-view.php',
    'workspace' => $root . '/includes/merchant-workspace.php',
    'navigation' => $root . '/includes/merchant-navigation.php',
    'router' => $root . '/includes/merchant-view.php',
    'foundation' => $root . '/api/merchant/_orders.php',
    'list' => $root . '/api/merchant/commerce-orders.php',
    'detail' => $root . '/api/merchant/commerce-order.php',
    'reconcile' => $root . '/api/merchant/commerce-order-reconcile.php',
    'js' => $root . '/assets/js/merchant-orders.js',
    'css' => $root . '/assets/css/merchant-orders.css',
];

$content = [];
foreach ($files as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n");
        exit(1);
    }
    $content[$key] = (string) file_get_contents($path);
}

$hasCanonicalIssuanceTruth = str_contains($content['foundation'], 'mg_order_issuance_summary($pdo, $order')
    || (
        str_contains($content['foundation'], 'mg_merchant_order_issuance_from_items')
        && str_contains($content['foundation'], "'query_shape' => 'bulk_detail_v2'")
        && str_contains($content['foundation'], "'expected_units' => \$expectedUnits")
        && str_contains($content['foundation'], "'action_center_items' => \$projectionItems")
    );

$hasEscapeDialogControl = preg_match('/event\.key\s*={2,3}\s*[\'\"]Escape[\'\"]/', $content['js']) === 1;

$checks = [
    'merchant page loads the scoped orders view and assets' =>
        str_contains($content['page'], '$merchantView = \'orders\'')
        && str_contains($content['page'], '/assets/js/merchant-orders.js')
        && str_contains($content['page'], '/assets/css/merchant-orders.css'),
    'merchant navigation and view router expose Orders and Microgift Totals' =>
        str_contains($content['navigation'], "'orders' => ['Orders'")
        && str_contains($content['navigation'], "'pppm' => ['Microgift Totals'")
        && str_contains($content['workspace'], "require_once __DIR__ . '/merchant-navigation.php'")
        && str_contains($content['router'], '$merchantView===\'orders\''),
    'list endpoint is permission and merchant scoped' =>
        str_contains($content['list'], "mg_merchant_require_permission('merchant.payments.view')")
        && str_contains($content['foundation'], 'WHERE o.merchant_user_id=?'),
    'filters are allowlisted and pagination is bounded' =>
        str_contains($content['foundation'], 'MG_MERCHANT_ORDERS_MAX_LIMIT = 50')
        && str_contains($content['foundation'], '$allowedPayment')
        && str_contains($content['foundation'], '$allowedFulfillment')
        && str_contains($content['foundation'], ' LIMIT ')
        && str_contains($content['foundation'], ' OFFSET '),
    'customer identity is masked and internal IDs are omitted from payloads' =>
        str_contains($content['foundation'], 'mg_merchant_orders_email_mask')
        && str_contains($content['foundation'], "'email_masked'")
        && !str_contains($content['js'], 'customer.user_id'),
    'detail endpoint enforces merchant ownership and canonical issuance truth' =>
        str_contains($content['foundation'], 'WHERE o.public_id=? AND o.merchant_user_id=?')
        && $hasCanonicalIssuanceTruth,
    'delivery recovery uses CSRF and the canonical transactional reconciler' =>
        str_contains($content['reconcile'], 'mg_require_csrf_for_write($input)')
        && str_contains($content['reconcile'], 'mg_payment_reconcile_paid_order')
        && str_contains($content['reconcile'], "payment_status'] !== 'paid'"),
    'recovery action is audited and does not expose refund or cancellation mutation' =>
        str_contains($content['reconcile'], 'merchant.commerce_order_delivery_reconciled')
        && !str_contains($content['reconcile'], 'UPDATE commerce_orders SET payment_status')
        && !str_contains($content['view'], 'Cancel order'),
    'browser runtime uses stable request keys and accessible dialog controls' =>
        str_contains($content['js'], 'mg-commerce-order-reconcile:')
        && $hasEscapeDialogControl
        && str_contains($content['view'], 'aria-modal="true"')
        && str_contains($content['view'], 'aria-live="polite"'),
    'UI exposes payment, issuance, item, timeline, loading, empty, and error states' =>
        str_contains($content['view'], 'data-orders-payments')
        && str_contains($content['view'], 'data-orders-issuance')
        && str_contains($content['view'], 'data-orders-items')
        && str_contains($content['view'], 'data-orders-timeline')
        && str_contains($content['view'], 'data-orders-loading')
        && str_contains($content['view'], 'data-orders-empty')
        && str_contains($content['view'], 'data-orders-error'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

if ($failed !== []) {
    fwrite(STDERR, PHP_EOL . 'Merchant Orders Recovery v1 validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo PHP_EOL . 'Merchant Orders Recovery v1 contract: 10/10.' . PHP_EOL;
