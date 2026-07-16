<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'confirm' => $root . '/api/subscriptions/confirm-checkout.php',
    'history' => $root . '/api/subscriptions/history.php',
    'webhook' => $root . '/api/subscriptions/_stripe_webhook.php',
    'page' => $root . '/account-subscriptions.php',
    'js' => $root . '/assets/js/subscription-checkout-completion-v1.js',
    'css' => $root . '/assets/css/subscription-checkout-completion-v1.css',
];

$content = [];
foreach ($paths as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n");
        exit(1);
    }
    $content[$key] = (string)file_get_contents($path);
}

$checks = [
    'checkout return requires authenticated POST and CSRF protection' =>
        str_contains($content['confirm'], "mg_require_method('POST')")
        && str_contains($content['confirm'], 'mg_require_api_user()')
        && str_contains($content['confirm'], 'mg_require_csrf_for_write($input)'),
    'checkout return retrieves the exact Stripe session with expanded subscription data' =>
        str_contains($content['confirm'], "'/v1/checkout/sessions/'")
        && str_contains($content['confirm'], "'subscription.latest_invoice.payment_intent'")
        && str_contains($content['confirm'], "'customer'"),
    'checkout return validates request, user, source, and stored session ownership' =>
        str_contains($content['confirm'], 'provider_session_reference')
        && str_contains($content['confirm'], "source_type")
        && str_contains($content['confirm'], "package_change_request_id")
        && str_contains($content['confirm'], "Checkout session does not belong to this account"),
    'checkout return accepts only paid or no-payment-required completions' =>
        str_contains($content['confirm'], "['paid', 'no_payment_required']")
        && str_contains($content['confirm'], "'pending' => true"),
    'checkout return reuses canonical package completion and refreshes provider references' =>
        str_contains($content['confirm'], 'mg_subscription_package_webhook_complete(')
        && str_contains($content['confirm'], 'provider_customer_id=COALESCE')
        && str_contains($content['confirm'], 'provider_subscription_id=COALESCE')
        && str_contains($content['confirm'], 'provider_latest_invoice_url=COALESCE')
        && str_contains($content['confirm'], 'last_payment_at=CASE'),
    'checkout return history is duplicate-safe' =>
        str_contains($content['confirm'], "event_type=? AND provider_key=? AND provider_event_id=?")
        && str_contains($content['confirm'], "platform_subscription.checkout_return_confirmed"),
    'signed Stripe webhook records checkout and payment activity' =>
        str_contains($content['webhook'], 'mg_subscription_stripe_record_history')
        && str_contains($content['webhook'], "platform_subscription.checkout_completed")
        && str_contains($content['webhook'], "platform_subscription.payment_received")
        && str_contains($content['webhook'], "platform_subscription.payment_attention_required"),
    'billing history API is owner-scoped and exposes invoice links' =>
        str_contains($content['history'], 'WHERE s.user_id=?')
        && str_contains($content['history'], "'invoice_url'")
        && str_contains($content['history'], "'invoice_pdf'")
        && str_contains($content['history'], 'LIMIT 24'),
    'account page loads checkout completion assets with cache versions' =>
        str_contains($content['page'], '/assets/css/subscription-checkout-completion-v1.css?v=1.0.0')
        && str_contains($content['page'], '/assets/js/subscription-checkout-completion-v1.js?v=1.0.0'),
    'browser controller confirms returned Checkout and renders billing history' =>
        str_contains($content['js'], "MG.post('/api/subscriptions/confirm-checkout.php'")
        && str_contains($content['js'], "MG.get('/api/subscriptions/history.php'")
        && str_contains($content['js'], "checkoutState === 'success'")
        && str_contains($content['js'], "checkout=activated")
        && str_contains($content['js'], 'data-subscription-history'),
    'billing history UI includes responsive professional presentation' =>
        str_contains($content['css'], '.mg-sub-checkout-banner')
        && str_contains($content['css'], '.mg-sub-history-summary')
        && str_contains($content['css'], '.mg-sub-history-row')
        && str_contains($content['css'], '@media(max-width:640px)'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

if ($failed !== []) {
    fwrite(STDERR, "\nSubscription Checkout Completion v1 validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

$total = count($checks);
echo "\nSubscription Checkout Completion v1 contract: {$total}/{$total}.\n";
