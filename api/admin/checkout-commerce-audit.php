<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
$user = mg_require_api_user();
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
if (!in_array('super_admin', $roles, true) && !in_array('merchant.payments.view', $permissions, true)) {
    mg_fail('Permission denied.', 403);
}

$root = dirname(__DIR__, 2);
$checks = [];
$pass = static function (string $message, array $context = []) use (&$checks): void { $checks[] = ['status'=>'pass','message'=>$message,'context'=>$context]; };
$fail = static function (string $message, array $context = []) use (&$checks): void { $checks[] = ['status'=>'fail','message'=>$message,'context'=>$context]; };

$files = [
    'cart.php','checkout.php','checkout-success.php','wallet.php','inbox.php','sent.php','claimed.php',
    'assets/js/cart.js','assets/js/customer-commerce.js','assets/js/checkout.js','assets/js/order-success.js','assets/js/merchant-payments.js','assets/js/merchant-pppm.js',
    'api/commerce/cart.php','api/commerce/cart-items.php','api/commerce/cart-item.php','api/commerce/checkout-draft.php','api/commerce/orders.php','api/commerce/order-confirmation.php','api/commerce/_checkout.php','api/commerce/_foundation.php','api/commerce/_order_issuance_summary.php',
    'api/payments/order-checkout-session.php','api/payments/session.php','api/payments/sandbox-confirm.php','api/payments/_capture.php','api/payments/_checkout_session.php','api/payments/_fulfillment.php',
    'api/merchant/financial-dashboard.php','api/merchant/pppm-items.php','api/merchant/orders.php','merchant-payments.php','merchant-pppm.php','includes/merchant-payments-view.php','includes/merchant-pppm-view.php',
    'api/microgifts/_action_center_projection.php','api/account/action-center.php','api/account/wallet-items.php'
];
foreach ($files as $file) is_file($root . '/' . $file) ? $pass('File exists: ' . $file) : $fail('Missing file: ' . $file);

$contracts = [
    'assets/js/customer-commerce.js' => ['createCheckoutFromCart','checkout-draft.php','orders.php','order-checkout-session.php','safeCheckoutUrl'],
    'assets/js/cart.js' => ['data-cart-checkout','createCheckoutFromCart','/checkout.php?session='],
    'assets/js/checkout.js' => ['sandbox-confirm.php','checkout-success.php','payment_status === \'paid\''],
    'api/commerce/checkout-draft.php' => ['Checkout currently supports one merchant','idempotency_key','items_json','checkout_drafts'],
    'api/commerce/_checkout.php' => ['commerce_orders','commerce_order_items','order_fee_snapshots','receipts','converted_order_id'],
    'api/payments/_checkout_session.php' => ['payment_intents','checkout_sessions','mg_payment_assert_checkout_ready','provider_checkout_url'],
    'api/payments/_capture.php' => ['payment_transactions','mg_payment_issue_order_pppm','mg_payment_issue_order_microgifts','receipts SET status=\'finalized\''],
    'api/payments/_fulfillment.php' => ['pppm_items','mg_microgift_issue','mg_action_center_receive','commerce-order-item:'],
    'api/commerce/order-confirmation.php' => ['mg_order_issuance_summary','action_center','receipt'],
    'api/merchant/financial-dashboard.php' => ['pppm_item_count','microgift_count','inbox_item_count','redeemed_pppm_count'],
    'assets/js/merchant-payments.js' => ['PPPM:','Microgifts:','Inbox:','View PPPM'],
    'checkout-success.php' => ['/wallet.php','/claimed.php','/inbox.php'],
];
foreach ($contracts as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) continue;
    $content = file_get_contents($path) ?: '';
    foreach ($needles as $needle) str_contains($content, $needle) ? $pass('Contract found in ' . $file . ': ' . $needle) : $fail('Missing contract in ' . $file . ': ' . $needle);
}

try {
    $pdo = mg_db();
    $tables = ['carts','cart_items','checkout_drafts','commerce_orders','commerce_order_items','order_fee_snapshots','receipts','payment_intents','checkout_sessions','payment_transactions','financial_ledger_entries','pppm_sources','pppm_source_events','pppm_issuance_requests','pppm_items','microgift_templates','microgift_template_versions','microgift_instances','microgift_inbox_items','notifications'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        ((int)$stmt->fetchColumn() > 0) ? $pass('Table exists: ' . $table) : $fail('Missing table: ' . $table);
    }
    $columns = [
        'checkout_drafts' => ['public_id','cart_id','buyer_user_id','merchant_user_id','items_json','subtotal_cents','platform_fee_cents','total_cents','status','idempotency_key','converted_order_id'],
        'commerce_orders' => ['public_id','buyer_user_id','merchant_user_id','payment_status','fulfillment_status','subtotal_cents','platform_fee_cents','total_cents','paid_at','source_type','source_reference','idempotency_key'],
        'commerce_order_items' => ['public_id','order_id','product_id','product_version_id','merchant_user_id','title_snapshot','quantity','unit_amount_cents','line_total_cents','pppm_issuance_request_id'],
        'payment_intents' => ['public_id','order_id','provider_key','amount_cents','currency','application_fee_cents','destination_account_reference','status','idempotency_key','provider_intent_reference','captured_at'],
        'checkout_sessions' => ['public_id','order_id','payment_intent_id','provider_key','status','provider_checkout_url','expires_at','completed_at'],
        'payment_transactions' => ['public_id','payment_intent_id','transaction_type','provider_reference','amount_cents','status'],
        'pppm_items' => ['public_id','source_reference','source_line_reference','merchant_user_id','owner_user_id','recipient_user_id','value_cents_snapshot','currency_snapshot','status'],
        'microgift_instances' => ['public_id','pppm_item_id','recipient_user_id','status','issued_at'],
        'microgift_inbox_items' => ['public_id','instance_id','user_id','merchant_user_id','folder','state'],
    ];
    foreach ($columns as $table => $cols) {
        foreach ($cols as $column) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $stmt->execute([$table,$column]);
            ((int)$stmt->fetchColumn() > 0) ? $pass('Column exists: ' . $table . '.' . $column) : $fail('Missing column: ' . $table . '.' . $column);
        }
    }
    foreach ([
        ['commerce_orders','payment_status',['unpaid','paid','partially_refunded','refunded','disputed','failed']],
        ['commerce_orders','fulfillment_status',['pending','issued','partial','failed','cancelled']],
        ['payment_intents','status',['created','requires_action','succeeded','failed','cancelled']],
        ['checkout_sessions','status',['open','completed','expired']],
        ['pppm_items','status',['available','sent','delivered','viewed','claim_pending','verified','redeemed','expired','cancelled','refunded','voided']],
        ['microgift_inbox_items','folder',['inbox','sent','claimed']],
    ] as [$table,$column,$values]) {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
        $stmt->execute([$column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $type = (string)($row['Type'] ?? '');
        foreach ($values as $value) str_contains($type, "'" . $value . "'") ? $pass('Enum supports ' . $table . '.' . $column . ': ' . $value) : $fail('Enum missing ' . $table . '.' . $column . ': ' . $value, ['type'=>$type]);
    }
    foreach (['commerce.checkout.create','merchant.payments.view','merchant.pppm.view','catalog.products.view'] as $permission) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE slug=?');
        $stmt->execute([$permission]);
        ((int)$stmt->fetchColumn() > 0) ? $pass('Permission exists: ' . $permission) : $fail('Missing permission: ' . $permission);
    }
} catch (Throwable $error) {
    $fail('Database audit failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()]);
}

$failures = array_values(array_filter($checks, static fn(array $check): bool => $check['status'] === 'fail'));
$score = count($checks) === 0 ? 0 : (int)round(((count($checks) - count($failures)) / count($checks)) * 10);
mg_ok(['score'=>$score,'status'=>count($failures)===0?'passed':'failed','summary'=>['checks'=>count($checks),'passed'=>count($checks)-count($failures),'failed'=>count($failures)],'checks'=>$checks], count($failures)===0 ? 'Checkout, payment, PPPM, and wallet issuance audit passed.' : 'Checkout commerce audit has failures.');
