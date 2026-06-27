<?php
declare(strict_types=1);

require_once __DIR__ . '/_package_billing.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$userId = (int)$user['id'];

try {
    mg_platform_package_sync_defaults($pdo);
    $subscription = mg_platform_account_subscription_snapshot($pdo, $userId, false);
    if (!$subscription) {
        $starter = mg_platform_package_get($pdo, 'starter');
        $amount = $starter ? mg_platform_package_amount_cents($starter, 'month') : 2900;
        mg_ok([
            'package_id' => 'starter',
            'package_name' => 'Starter',
            'status' => 'not_started',
            'status_label' => 'Starter',
            'billing_cycle' => 'month',
            'renews_on' => null,
            'next_charge_cents' => $amount,
            'next_charge_label' => '$' . number_format($amount / 100, 0),
            'currency' => 'USD',
            'stripe_customer_reference' => null,
            'stripe_subscription_reference' => null,
            'stripe_session_reference' => null,
            'manage_billing_available' => false,
            'manage_billing_note' => 'Stripe Billing Portal is not configured yet.',
        ], 'Platform subscription status loaded.');
    }

    $status = (string)($subscription['status'] ?? 'active');
    $periodEnd = (string)($subscription['next_billing_at'] ?? $subscription['current_period_end'] ?? '');
    $amount = (int)($subscription['amount_cents'] ?? 0);
    mg_ok([
        'package_id' => (string)$subscription['package_id'],
        'package_name' => (string)($subscription['package_name'] ?? ucwords(str_replace('-', ' ', (string)$subscription['package_id']))),
        'status' => $status,
        'status_label' => ucwords(str_replace('_', ' ', $status)),
        'billing_cycle' => (string)($subscription['billing_cycle'] ?? 'month'),
        'renews_on' => $periodEnd !== '' ? date('M j, Y', strtotime($periodEnd)) : null,
        'next_charge_cents' => $amount,
        'next_charge_label' => '$' . number_format($amount / 100, 0),
        'currency' => (string)($subscription['currency'] ?? 'USD'),
        'stripe_customer_reference' => $subscription['provider_customer_id'] !== null ? (string)$subscription['provider_customer_id'] : null,
        'stripe_subscription_reference' => $subscription['provider_subscription_id'] !== null ? (string)$subscription['provider_subscription_id'] : null,
        'stripe_session_reference' => $subscription['provider_session_reference'] !== null ? (string)$subscription['provider_session_reference'] : null,
        'manage_billing_available' => false,
        'manage_billing_note' => 'Stripe Billing Portal is not configured yet.',
    ], 'Platform subscription status loaded.');
} catch (Throwable $e) {
    mg_fail('Unable to load platform subscription status.', 500);
}
