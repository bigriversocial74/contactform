<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/db.php';
require_once __DIR__ . '/pricing-packages.php';

if (!function_exists('mg_package_entitlement_decode_json')) {
function mg_package_entitlement_decode_json(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}
}

if (!function_exists('mg_package_entitlement_slug')) {
function mg_package_entitlement_slug(mixed $value): string
{
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
}
}

if (!function_exists('mg_package_entitlement_free_context')) {
function mg_package_entitlement_free_context(?array $user = null): array
{
    return [
        'package_id' => 'free',
        'package_name' => 'Free',
        'status' => 'free',
        'billing_cycle' => null,
        'amount_cents' => 0,
        'currency' => 'USD',
        'is_free' => true,
        'is_paid' => false,
        'merchant_access' => false,
        'social_access' => (bool) $user,
        'subscription' => null,
        'features' => [
            'Gift inbox',
            'Sent gifts',
            'Claimed gifts',
            'Social gifting',
            'Wallet access',
        ],
        'limits' => [
            'max_microgifts' => 0,
            'max_rewards' => 0,
            'max_active_campaigns' => 0,
            'max_crm_contacts' => 0,
            'monthly_stamps_included' => 0,
            'max_landing_pages' => 0,
            'max_locations' => 0,
            'max_team_seats' => 0,
            'stamp_overage_enabled' => false,
            'bulk_stamp_purchase_enabled' => false,
            'email_stamps_enabled' => false,
            'sms_stamps_enabled' => false,
        ],
    ];
}
}

if (!function_exists('mg_package_entitlement_catalog_package')) {
function mg_package_entitlement_catalog_package(string $packageId): ?array
{
    $packageId = mg_package_entitlement_slug($packageId);
    if ($packageId === 'free') {
        return mg_package_entitlement_free_context();
    }
    foreach (mg_pricing_packages() as $package) {
        $id = mg_package_entitlement_slug((string) ($package['id'] ?? $package['name'] ?? ''));
        if ($id === $packageId) {
            return [
                'package_id' => $id,
                'package_name' => (string) ($package['name'] ?? ucwords(str_replace('-', ' ', $id))),
                'features' => array_values((array) ($package['included_features'] ?? [])),
                'limits' => is_array($package['limits'] ?? null) ? $package['limits'] : [],
            ];
        }
    }
    return null;
}
}

if (!function_exists('mg_package_entitlement_subscription_row')) {
function mg_package_entitlement_subscription_row(PDO $pdo, int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT s.*, p.name package_name, p.features_json, p.limits_json, p.is_self_serve, p.requires_admin_review
             FROM platform_account_subscriptions s
             LEFT JOIN platform_subscription_packages p ON p.package_id = s.package_id
             WHERE s.user_id = ?
             ORDER BY FIELD(s.status, "active", "trialing", "cancel_pending", "past_due", "incomplete", "pending_admin_review", "paused", "canceled", "expired"), s.updated_at DESC, s.id DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable) {
        return null;
    }
}
}

if (!function_exists('mg_package_entitlement_active_statuses')) {
function mg_package_entitlement_active_statuses(): array
{
    return ['active', 'trialing', 'cancel_pending', 'past_due'];
}
}

if (!function_exists('mg_package_entitlement_admin_context')) {
function mg_package_entitlement_admin_context(?array $user = null): array
{
    $catalog = mg_package_entitlement_catalog_package('enterprise') ?? [];
    $context = mg_package_entitlement_free_context($user);
    $context['package_id'] = 'enterprise';
    $context['package_name'] = 'Enterprise';
    $context['status'] = 'admin';
    $context['is_free'] = false;
    $context['is_paid'] = true;
    $context['merchant_access'] = true;
    $context['social_access'] = true;
    $context['features'] = array_values((array) ($catalog['features'] ?? $context['features']));
    $context['limits'] = is_array($catalog['limits'] ?? null) ? $catalog['limits'] : [
        'max_microgifts' => null,
        'max_rewards' => null,
        'max_active_campaigns' => null,
        'max_crm_contacts' => null,
        'monthly_stamps_included' => null,
        'max_landing_pages' => null,
        'max_locations' => null,
        'max_team_seats' => null,
        'stamp_overage_enabled' => true,
        'bulk_stamp_purchase_enabled' => true,
        'email_stamps_enabled' => true,
        'sms_stamps_enabled' => true,
    ];
    return $context;
}
}

if (!function_exists('mg_user_package_context')) {
function mg_user_package_context(?PDO $pdo = null, ?array $user = null): array
{
    $user = $user ?? (function_exists('mg_current_user') ? mg_current_user() : null);
    if (!$user) {
        return mg_package_entitlement_free_context(null);
    }

    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (in_array('admin', $roles, true) || in_array('super_admin', $roles, true)) {
        return mg_package_entitlement_admin_context($user);
    }

    $userId = (int) ($user['id'] ?? 0);
    $pdo = $pdo ?: mg_db();
    $subscription = mg_package_entitlement_subscription_row($pdo, $userId);
    if ($subscription) {
        $packageId = mg_package_entitlement_slug((string) ($subscription['package_id'] ?? 'free')) ?: 'free';
        $catalog = mg_package_entitlement_catalog_package($packageId) ?? mg_package_entitlement_free_context($user);
        $status = (string) ($subscription['status'] ?? 'inactive');
        $active = in_array($status, mg_package_entitlement_active_statuses(), true);
        $features = mg_package_entitlement_decode_json($subscription['features_json'] ?? null) ?: array_values((array) ($catalog['features'] ?? []));
        $limits = mg_package_entitlement_decode_json($subscription['limits_json'] ?? null) ?: (is_array($catalog['limits'] ?? null) ? $catalog['limits'] : []);

        return [
            'package_id' => $packageId,
            'package_name' => (string) ($subscription['package_name'] ?? $catalog['package_name'] ?? ucwords(str_replace('-', ' ', $packageId))),
            'status' => $status,
            'billing_cycle' => $subscription['billing_cycle'] ?? null,
            'amount_cents' => isset($subscription['amount_cents']) ? (int) $subscription['amount_cents'] : 0,
            'currency' => strtoupper((string) ($subscription['currency'] ?? 'USD')),
            'is_free' => !$active || $packageId === 'free',
            'is_paid' => $active && $packageId !== 'free',
            'merchant_access' => $active && $packageId !== 'free',
            'social_access' => true,
            'subscription' => $subscription,
            'features' => $features,
            'limits' => $limits,
        ];
    }

    if (in_array('merchant', $roles, true)) {
        $catalog = mg_package_entitlement_catalog_package('starter') ?? [];
        $context = mg_package_entitlement_free_context($user);
        $context['package_id'] = 'starter';
        $context['package_name'] = 'Starter';
        $context['status'] = 'legacy_role';
        $context['is_free'] = false;
        $context['is_paid'] = true;
        $context['merchant_access'] = true;
        $context['features'] = array_values((array) ($catalog['features'] ?? $context['features']));
        $context['limits'] = is_array($catalog['limits'] ?? null) ? $catalog['limits'] : $context['limits'];
        return $context;
    }

    return mg_package_entitlement_free_context($user);
}
}

if (!function_exists('mg_user_has_merchant_access')) {
function mg_user_has_merchant_access(?array $user = null, ?PDO $pdo = null): bool
{
    $context = mg_user_package_context($pdo, $user);
    return !empty($context['merchant_access']);
}
}

if (!function_exists('mg_package_limit_value')) {
function mg_package_limit_value(array $context, string $limitKey): mixed
{
    $limits = is_array($context['limits'] ?? null) ? $context['limits'] : [];
    return array_key_exists($limitKey, $limits) ? $limits[$limitKey] : null;
}
}

if (!function_exists('mg_package_limit_allows_create')) {
function mg_package_limit_allows_create(array $context, string $limitKey, int $currentUsage): bool
{
    if (empty($context['merchant_access'])) {
        return false;
    }
    $limit = mg_package_limit_value($context, $limitKey);
    if ($limit === null || $limit === '') {
        return true;
    }
    return $currentUsage < max(0, (int) $limit);
}
}

if (!function_exists('mg_package_require_merchant_access')) {
function mg_package_require_merchant_access(PDO $pdo, array $user, string $message = 'Merchant access requires an active paid package.'): array
{
    $context = mg_user_package_context($pdo, $user);
    if (!empty($context['merchant_access'])) {
        return $context;
    }
    if (function_exists('mg_fail')) {
        mg_fail($message, 403);
    }
    throw new RuntimeException($message);
}
}

if (!function_exists('mg_package_require_limit_available')) {
function mg_package_require_limit_available(PDO $pdo, array $user, string $limitKey, int $currentUsage, string $message): array
{
    $context = mg_package_require_merchant_access($pdo, $user);
    if (mg_package_limit_allows_create($context, $limitKey, $currentUsage)) {
        return $context;
    }
    $limit = mg_package_limit_value($context, $limitKey);
    $packageName = (string) ($context['package_name'] ?? 'current');
    $detail = $limit === null ? $message : $message . ' Your ' . $packageName . ' package limit is ' . (int) $limit . '.';
    if (function_exists('mg_fail')) {
        mg_fail($detail, 402);
    }
    throw new RuntimeException($detail);
}
}
