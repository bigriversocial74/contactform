<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'page' => 'merchant-orders.php',
    'view' => 'includes/merchant-orders-view.php',
    'api' => 'api/merchant/_orders.php',
    'list_endpoint' => 'api/merchant/commerce-orders.php',
    'summary_endpoint' => 'api/merchant/commerce-orders-summary.php',
    'detail_endpoint' => 'api/merchant/commerce-order.php',
    'reconcile_endpoint' => 'api/merchant/commerce-order-reconcile.php',
    'js' => 'assets/js/merchant-orders.js',
    'sql' => 'database/merchant_orders_performance_hardening_v1.sql',
    'manifest' => 'config/migrations.php',
];

$files = [];
foreach ($paths as $key => $path) {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content) || trim($content) === '') {
        fwrite(STDERR, "Missing validation target: {$path}\n");
        exit(1);
    }
    $files[$key] = $content;
}

$checks = [
    'orders page no longer boots the unrelated merchant dashboard runtime' => !str_contains($files['page'], 'merchant-workspace.js'),
    'orders page loads the hardened runtime directly' => str_contains($files['page'], 'merchant-orders.js?v=2.0.0'),
    'orders list uses bounded pagination' => str_contains($files['api'], 'MG_MERCHANT_ORDERS_MAX_PAGE = 1000')
        && str_contains($files['api'], 'MG_MERCHANT_ORDERS_MAX_LIMIT = 50'),
    'orders list uses bulk aggregation instead of per-row count subqueries' => str_contains($files['api'], 'function mg_merchant_orders_aggregate')
        && str_contains($files['api'], "'query_shape' => 'bulk_aggregate_v2'")
        && !str_contains($files['api'], '(SELECT COUNT(*) FROM commerce_order_items oi WHERE oi.order_id=o.id)'),
    'bulk order metrics cover items refunds PPPM Microgifts and Action Center' => str_contains($files['api'], 'line_count')
        && str_contains($files['api'], 'refunded_cents')
        && str_contains($files['api'], 'pppm_count')
        && str_contains($files['api'], 'microgift_count')
        && str_contains($files['api'], 'action_center_count'),
    'order detail reuses bulk item counts and avoids duplicate issuance summary queries' => str_contains($files['api'], "'query_shape' => 'bulk_detail_v2'")
        && str_contains($files['api'], 'mg_merchant_order_issuance_from_items')
        && !str_contains($files['api'], 'mg_order_issuance_summary('),
    'summary is isolated behind its own merchant-scoped endpoint' => str_contains($files['summary_endpoint'], "mg_merchant_require_permission('merchant.payments.view')")
        && str_contains($files['summary_endpoint'], 'mg_merchant_orders_summary'),
    'list endpoint remains merchant scoped' => str_contains($files['list_endpoint'], "mg_merchant_require_permission('merchant.payments.view')"),
    'detail and reconciliation endpoints remain merchant scoped' => str_contains($files['detail_endpoint'], "mg_merchant_require_permission('merchant.payments.view')")
        && str_contains($files['reconcile_endpoint'], "mg_merchant_require_permission('merchant.payments.view')"),
    'browser runtime has abortable list summary detail and reconciliation requests' => str_contains($files['js'], 'AbortController')
        && str_contains($files['js'], "'list'")
        && str_contains($files['js'], "'summary'")
        && str_contains($files['js'], "'detail'")
        && str_contains($files['js'], "'reconcile'"),
    'browser runtime converts long requests into visible timeout errors' => str_contains($files['js'], 'MG_REQUEST_TIMEOUT')
        && str_contains($files['js'], 'The order list took too long to load')
        && str_contains($files['js'], 'The order details took too long to load'),
    'new list requests replace stale requests instead of being ignored' => str_contains($files['js'], 'state.listRequest')
        && !str_contains($files['js'], 'if(state.loading)return'),
    'summary loads independently so it cannot block the order list' => str_contains($files['js'], '/api/merchant/commerce-orders-summary.php')
        && str_contains($files['js'], 'loadOrders(false);')
        && str_contains($files['js'], 'loadSummary(false);'),
    'performance migration adds the seven required supporting indexes' => substr_count($files['sql'], 'ALTER TABLE') === 7
        && str_contains($files['sql'], 'idx_commerce_orders_merchant_created')
        && str_contains($files['sql'], 'idx_pppm_items_source_order_line')
        && str_contains($files['sql'], 'idx_microgift_instances_order_item')
        && str_contains($files['sql'], 'idx_payment_refunds_order_status')
        && str_contains($files['sql'], 'idx_payment_disputes_order_created'),
    'performance migration is idempotent' => substr_count($files['sql'], 'information_schema.STATISTICS') === 7
        && substr_count($files['sql'], 'PREPARE s FROM @sql') === 7,
    'performance migration is registered in the canonical manifest' => str_contains($files['manifest'], "'merchant_orders_performance_hardening_v1.sql'"),
    'orders view retains loading error empty pagination and retry states' => str_contains($files['view'], 'data-orders-loading')
        && str_contains($files['view'], 'data-orders-error')
        && str_contains($files['view'], 'data-orders-empty')
        && str_contains($files['view'], 'data-orders-pagination')
        && str_contains($files['view'], 'data-orders-retry'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Merchant Orders freeze repair validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Merchant Orders freeze repair contract: ' . count($checks) . '/' . count($checks) . " checks passed.\n";
