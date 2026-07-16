<?php
declare(strict_types=1);

require_once __DIR__ . '/_billing_lifecycle_v2.php';
require_once dirname(__DIR__, 2) . '/includes/package-entitlements.php';

mg_require_method('GET');
$user = mg_require_api_user();

try {
    $pdo = mg_db();
    mg_platform_package_sync_defaults($pdo);
    $context = mg_user_package_context($pdo, $user);
    $snapshot = mg_platform_account_subscription_snapshot($pdo, (int)$user['id'], false);
    $request = mg_subscription_package_change_latest($pdo, (int)$user['id'], false);

    $packages = [];
    $stmt = $pdo->query("SELECT package_id,name,monthly_amount_cents,yearly_amount_cents,currency,is_self_serve,requires_admin_review,features_json,limits_json FROM platform_subscription_packages WHERE status='active' ORDER BY FIELD(package_id,'starter','growth','pro','enterprise'),id");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $packages[] = [
            'package_id' => (string)$row['package_id'],
            'name' => (string)$row['name'],
            'monthly_amount_cents' => (int)$row['monthly_amount_cents'],
            'yearly_amount_cents' => (int)$row['yearly_amount_cents'],
            'currency' => strtoupper((string)$row['currency']),
            'is_self_serve' => (int)$row['is_self_serve'] === 1,
            'requires_admin_review' => (int)$row['requires_admin_review'] === 1,
            'features' => mg_platform_package_json($row['features_json'] ?? null),
            'limits' => mg_platform_package_json($row['limits_json'] ?? null),
        ];
    }

    $subscription = null;
    if ($snapshot) {
        $subscription = [
            'subscription_id' => (string)$snapshot['public_id'],
            'package_id' => (string)$snapshot['package_id'],
            'package_name' => (string)($snapshot['package_name'] ?? $snapshot['package_id']),
            'billing_cycle' => (string)$snapshot['billing_cycle'],
            'status' => (string)$snapshot['status'],
            'amount_cents' => (int)$snapshot['amount_cents'],
            'currency' => strtoupper((string)$snapshot['currency']),
            'provider_key' => (string)($snapshot['provider_key'] ?? ''),
            'current_period_start' => $snapshot['current_period_start'] ?? null,
            'current_period_end' => $snapshot['current_period_end'] ?? null,
            'next_billing_at' => $snapshot['next_billing_at'] ?? null,
            'cancel_at_period_end' => !empty($snapshot['cancel_at_period_end']),
            'scheduled_package_id' => $snapshot['scheduled_package_id'] ?? null,
            'scheduled_billing_cycle' => $snapshot['scheduled_billing_cycle'] ?? null,
            'scheduled_effective_at' => $snapshot['scheduled_effective_at'] ?? null,
            'latest_invoice_id' => $snapshot['provider_latest_invoice_id'] ?? null,
            'latest_invoice_status' => $snapshot['provider_latest_invoice_status'] ?? null,
            'latest_invoice_url' => $snapshot['provider_latest_invoice_url'] ?? null,
            'latest_invoice_pdf' => $snapshot['provider_latest_invoice_pdf'] ?? null,
            'last_payment_at' => $snapshot['last_payment_at'] ?? null,
            'last_payment_failed_at' => $snapshot['last_payment_failed_at'] ?? null,
            'portal_available' => (string)($snapshot['provider_key'] ?? '') === 'stripe' && trim((string)($snapshot['provider_customer_id'] ?? '')) !== '',
        ];
    }

    mg_ok([
        'package' => [
            'package_id' => (string)($context['package_id'] ?? 'free'),
            'package_name' => (string)($context['package_name'] ?? 'Free Wallet'),
            'merchant_access' => !empty($context['merchant_access']),
            'is_paid' => !empty($context['is_paid']),
            'is_complimentary' => !empty($context['is_complimentary']),
            'entitlement_source' => (string)($context['entitlement_source'] ?? 'free_wallet'),
        ],
        'subscription' => $subscription,
        'request' => $request ? mg_subscription_package_change_public($request) : null,
        'packages' => $packages,
    ], 'Subscription billing state loaded.');
} catch (Throwable $error) {
    mg_security_log('error','subscription.billing_state_failed','Unable to load subscription billing state.',['exception'=>$error->getMessage()],(int)($user['id']??0));
    mg_fail('Unable to load subscription billing state.',500);
}
