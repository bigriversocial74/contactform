<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';

$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.view') && !mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
mg_require_method('GET');

$root = dirname(__DIR__, 2);
$checks = [];

$addCheck = static function (string $key, string $label, string $status, string $detail, array $meta = []) use (&$checks): void {
    $checks[] = ['key'=>$key, 'label'=>$label, 'status'=>$status, 'detail'=>$detail, 'meta'=>$meta];
};
$readFile = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) return '';
    $contents = file_get_contents($full);
    return is_string($contents) ? $contents : '';
};
$requireFile = static function (string $key, string $path, string $label) use ($root, $addCheck): void {
    $full = $root . '/' . ltrim($path, '/');
    $exists = is_file($full);
    $addCheck($key, $label, $exists ? 'pass' : 'fail', $exists ? $path . ' exists.' : $path . ' is missing.', ['path'=>$path]);
};

try {
    $requireFile('merchant_stamps_page', 'merchant-stamps.php', 'Merchant Stamp purchase page');
    $requireFile('stamp_checkout_page', 'stamp-checkout.php', 'Stamp checkout page');
    $requireFile('stamp_checkout_js', 'assets/js/stamp-checkout.js', 'Stamp checkout JavaScript');
    $requireFile('purchase_api', 'api/stamps/purchase.php', 'Purchase registration API');
    $requireFile('purchase_status_api', 'api/stamps/purchase-status.php', 'Purchase status API');
    $requireFile('provider_checkout_api', 'api/stamps/checkout-session.php', 'Provider checkout API');
    $requireFile('admin_completion_api', 'api/stamps/purchase-complete.php', 'Admin-only completion API');
    $requireFile('payment_webhook', 'api/payments/_webhook.php', 'Payment webhook processor');

    $checkoutJs = $readFile('assets/js/stamp-checkout.js');
    $checkoutApi = $readFile('api/stamps/checkout-session.php');
    $statusApi = $readFile('api/stamps/purchase-status.php');
    $purchaseHelper = $readFile('api/stamps/_purchases.php');
    $completeApi = $readFile('api/stamps/purchase-complete.php');
    $webhook = $readFile('api/payments/_webhook.php');

    $addCheck('secure_payment_button', 'Secure payment action', str_contains($checkoutJs, 'Continue to secure payment') && str_contains($checkoutJs, '/api/stamps/checkout-session.php') ? 'pass' : 'fail', 'Checkout UI must create a provider checkout session before redirecting.');
    $addCheck('no_merchant_self_credit', 'No merchant self-credit action', !str_contains($checkoutJs, '/api/stamps/purchase-complete.php') && !str_contains($checkoutJs, 'sandbox-confirm') && !str_contains($checkoutJs, 'Complete sandbox payment') ? 'pass' : 'fail', 'Merchant checkout UI must not expose manual completion, sandbox confirm, or self-credit controls.');
    $addCheck('owner_scoped_status', 'Owner-scoped purchase status', str_contains($statusApi, 'mg_stamp_purchase_load($pdo, (int)$user[\'id\']') ? 'pass' : 'fail', 'Purchase status must load only purchases owned by the current merchant user.');
    $addCheck('provider_checkout_guards', 'Provider checkout guards', str_contains($checkoutApi, 'mg_require_method(\'POST\')') && str_contains($checkoutApi, 'mg_require_csrf_for_write') && str_contains($checkoutApi, 'mg_stamp_purchase_load($pdo, (int)$user[\'id\']') ? 'pass' : 'fail', 'Provider checkout API must require POST, CSRF, login, and owner scope.');
    $addCheck('provider_metadata', 'Provider metadata', str_contains($purchaseHelper, 'mg_stamp_purchase_provider_metadata') && str_contains($purchaseHelper, 'source_type') && str_contains($purchaseHelper, 'stamp_purchase_id') && str_contains($purchaseHelper, 'payment_intent_data') ? 'pass' : 'fail', 'Stripe Checkout sessions must carry Stamp purchase metadata for webhook reconciliation.');
    $addCheck('verified_webhook_completion', 'Verified webhook completion', str_contains($webhook, 'mg_payment_webhook_find_stamp_purchase') && str_contains($webhook, 'mg_stamp_purchase_complete_verified') ? 'pass' : 'fail', 'Stamp ledger credit must still flow through verified provider webhook completion.');
    $addCheck('admin_only_manual_completion', 'Admin-only manual completion', str_contains($completeApi, 'admin.stamps.manage') && str_contains($completeApi, 'mg_stamp_purchase_complete_verified') ? 'pass' : 'fail', 'Manual completion must remain restricted to admin Stamp management permission.');

    $pdo = mg_db();
    $config = mg_payment_platform_config($pdo, 'stripe', mg_payment_mode());
    $stripeReady = mg_stripe_stub_enabled() || (!empty($config['enabled']) && trim((string)$config['secret_key']) !== '' && trim((string)$config['webhook_secret']) !== '');
    $addCheck('stripe_provider_config', 'Stripe provider configuration', $stripeReady ? 'pass' : 'warning', $stripeReady ? 'Stripe checkout can create hosted sessions in this mode.' : 'Stripe is not fully configured; checkout will show provider-not-configured instead of self-crediting.', ['mode'=>mg_payment_mode(), 'stub_enabled'=>mg_stripe_stub_enabled()]);

    try {
        $stmt = $pdo->query('SELECT status,COUNT(*) total FROM stamp_purchases GROUP BY status');
        $statusCounts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $statusCounts[(string)$row['status']] = (int)$row['total'];
        $addCheck('recent_purchase_visibility', 'Purchase visibility', 'pass', 'Stamp purchase table is readable for reconciliation.', ['status_counts'=>$statusCounts]);
    } catch (Throwable $tableError) {
        $addCheck('recent_purchase_visibility', 'Purchase visibility', 'warning', 'Stamp purchase table is not readable yet; import Stamp purchase migration before live QA.', ['exception_class'=>$tableError::class]);
    }

    $summary = ['pass'=>0,'warning'=>0,'fail'=>0,'total'=>count($checks),'overall'=>'pass'];
    foreach ($checks as $check) {
        $status = (string)$check['status'];
        if (!isset($summary[$status])) $summary[$status] = 0;
        $summary[$status]++;
    }
    $summary['overall'] = $summary['fail'] > 0 ? 'fail' : ($summary['warning'] > 0 ? 'warning' : 'pass');
    mg_ok(['checks'=>$checks, 'summary'=>$summary], 'Stamp checkout QA checks loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'stamps.checkout_qa_failed', 'Stamp checkout QA checks failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to run Stamp checkout QA checks.', 500);
}
