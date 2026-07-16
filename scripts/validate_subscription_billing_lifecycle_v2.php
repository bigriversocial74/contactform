<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) throw new RuntimeException('Missing required file: ' . $path);
    $content = file_get_contents($full);
    if (!is_string($content)) throw new RuntimeException('Unable to read required file: ' . $path);
    return $content;
};
$expect = static function (bool $condition, string $label) use (&$failures, &$passes): void {
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if ($condition) $passes++;
    else $failures[] = $label;
};

try {
    $migration = $read('database/stage_18aj_subscription_billing_lifecycle_v2.sql');
    $billing = $read('api/subscriptions/_package_billing.php');
    $lifecycle = $read('api/subscriptions/_billing_lifecycle_v2.php');
    $checkout = $read('api/subscriptions/_checkout_handoff.php');
    $request = $read('api/subscriptions/request-upgrade.php');
    $manage = $read('api/subscriptions/manage.php');
    $portal = $read('api/subscriptions/billing-portal.php');
    $state = $read('api/subscriptions/billing-state.php');
    $webhookV2 = $read('api/subscriptions/_package_webhook_v2.php');
    $stripeWebhook = $read('api/subscriptions/_stripe_webhook.php');
    $stripe = $read('api/payments/_stripe.php');
    $status = $read('api/subscriptions/package-change-status.php');
    $authority = $read('includes/account/subscription-authority.php');
    $page = $read('account-subscriptions.php');
    $js = $read('assets/js/subscription-billing-v2.js');
    $css = $read('assets/css/subscription-billing-v2.css');

    foreach ([
        'stripe_monthly_price_id_test','stripe_monthly_price_id_live','stripe_yearly_price_id_test','stripe_yearly_price_id_live',
        'provider_schedule_id','scheduled_package_id','scheduled_billing_cycle','scheduled_effective_at',
        'provider_latest_invoice_id','provider_latest_invoice_status','provider_latest_invoice_url','provider_latest_invoice_pdf',
        'last_payment_at','last_payment_failed_at','reactivated_at','stage_18aj_subscription_billing_lifecycle_v2',
    ] as $marker) {
        $expect(str_contains($migration, $marker), 'Lifecycle migration contains ' . $marker);
    }

    $expect(
        str_contains($billing, 'function mg_platform_package_yearly_amount')
        && str_contains($billing, 'function mg_platform_package_stripe_price_id')
        && str_contains($billing, 'function mg_platform_package_find_by_price_id')
        && str_contains($billing, 'stripe_yearly_price_id_test'),
        'Canonical package billing supports monthly and yearly reusable Stripe prices'
    );

    $expect(
        str_contains($lifecycle, "'/v1/products'")
        && str_contains($lifecycle, "'/v1/prices'")
        && str_contains($lifecycle, "'/v1/billing_portal/sessions'")
        && str_contains($lifecycle, "'/v1/subscription_schedules'")
        && str_contains($lifecycle, "'from_subscription' => \$subscriptionId")
        && str_contains($lifecycle, "'end_behavior' => 'release'")
        && str_contains($lifecycle, "'proration_behavior' => 'none'"),
        'Lifecycle creates reusable products and prices, opens the portal, and schedules next-period changes'
    );

    $expect(
        !str_contains($lifecycle, "'subscription-scheduled-change:'")
        && !preg_match("#/v1/subscriptions/.+proration_behavior#s", $lifecycle),
        'Scheduled downgrades do not directly replace the active Stripe subscription price'
    );

    $expect(
        str_contains($checkout, 'mg_subscription_billing_v2_price_id')
        && str_contains($checkout, "'mode' => 'subscription'")
        && str_contains($checkout, "'billing_cycle' => \$billingCycle")
        && str_contains($checkout, "'provider_price_id' => \$priceId")
        && !str_contains($checkout, "'price_data' =>"),
        'Checkout uses canonical reusable Stripe Prices for monthly and yearly subscriptions'
    );

    $expect(
        str_contains($request, 'mg_subscription_billing_v2_request')
        && str_contains($request, 'mg_subscription_billing_v2_schedule_change')
        && str_contains($request, 'mg_subscription_billing_v2_attach_portal')
        && str_contains($request, 'billing_cycle'),
        'Upgrade request selects checkout, portal, or scheduled change from canonical state'
    );

    $expect(
        str_contains($manage, 'platform_account_subscriptions')
        && str_contains($manage, 'cancel_at_period_end')
        && str_contains($manage, 'reactivate')
        && str_contains($manage, 'mg_subscription_billing_v2_release_schedule')
        && !preg_match('/UPDATE\s+subscriptions\s+SET/i', $manage),
        'Customer cancellation and reactivation use Stripe and canonical subscription authority only'
    );

    $expect(
        str_contains($portal, 'mg_require_api_user')
        && str_contains($portal, 'mg_require_csrf_for_write')
        && str_contains($portal, 'mg_subscription_billing_v2_portal_session'),
        'Billing portal endpoint is authenticated, CSRF protected, and provider backed'
    );

    $expect(
        str_contains($state, 'provider_latest_invoice_url')
        && str_contains($state, 'scheduled_package_id')
        && str_contains($state, 'portal_available')
        && str_contains($status, 'change_scheduled')
        && str_contains($status, 'cancel_pending'),
        'Billing state and activation status expose invoices, portal access, cancellation, and scheduled changes'
    );

    foreach ([
        'customer.subscription.created','customer.subscription.updated','customer.subscription.deleted',
        'customer.subscription.paused','customer.subscription.resumed','invoice.paid','invoice.payment_failed',
        'invoice.payment_action_required','subscription_schedule.created','subscription_schedule.updated',
        'subscription_schedule.completed','subscription_schedule.released','subscription_schedule.canceled',
    ] as $eventType) {
        $expect(str_contains($webhookV2, $eventType), 'Webhook v2 handles ' . $eventType);
    }

    $expect(
        str_contains($webhookV2, 'provider_latest_invoice_status')
        && str_contains($webhookV2, 'last_payment_failed_at')
        && str_contains($webhookV2, 'mg_subscription_package_webhook_v2_reconcile_request')
        && str_contains($webhookV2, 'priceMatchesScheduled')
        && str_contains($stripeWebhook, 'mg_subscription_package_webhook_v2_try_process'),
        'Webhook lifecycle records payment state and only applies matching provider package changes'
    );

    foreach (['/v1/products','/v1/prices','/v1/billing_portal/sessions','/v1/subscription_schedules'] as $stubPath) {
        $expect(str_contains($stripe, $stubPath), 'Stripe test stub supports ' . $stubPath);
    }

    $expect(
        str_contains($authority, 'portal_available')
        && str_contains($authority, 'scheduled_package_id')
        && str_contains($authority, 'latest_invoice_url'),
        'Subscription view authority exposes real canonical billing details'
    );

    $expect(
        str_contains($page, '/assets/css/subscription-billing-v2.css?v=1.0.0')
        && str_contains($page, '/assets/js/subscription-billing-v2.js?v=1.0.0')
        && str_contains($page, 'data-subscription-billing-v2-root'),
        'Subscription account page loads the v2 billing interface'
    );

    $expect(
        str_contains($js, "MG.post('/api/subscriptions/request-upgrade.php'")
        && str_contains($js, "MG.post('/api/subscriptions/billing-portal.php'")
        && str_contains($js, "MG.post('/api/subscriptions/manage.php'")
        && str_contains($js, 'data-sub-v2-cycle')
        && str_contains($js, 'Confirm Period-End Cancellation')
        && !str_contains($js, 'window.confirm('),
        'Billing UI uses a review modal and inline confirmation without browser dialogs'
    );

    $expect(
        str_contains($css, '.mg-sub-v2-modal')
        && str_contains($css, '.mg-sub-lifecycle-strip')
        && str_contains($css, '.mg-sub-billing-actions')
        && str_contains($css, '.mg-btn.is-confirming')
        && str_contains($css, '@media(max-width:640px)'),
        'Billing v2 UI is responsive and includes lifecycle and confirmation states'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo '[FAIL] ' . $error->getMessage() . PHP_EOL;
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("\nSubscription billing lifecycle v2 validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) fwrite(STDERR, ' - ' . $failure . PHP_EOL);
    exit(1);
}

echo sprintf("\nSubscription billing lifecycle v2 validation passed: %d checks.\n", $passes);
