<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/db.php';
require_once __DIR__ . '/pricing-packages.php';

if (!function_exists('mg_package_entitlement_decode_json')) {
function mg_package_entitlement_decode_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
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
        'package_name' => 'Free Wallet',
        'status' => 'free',
        'billing_cycle' => null,
        'amount_cents' => 0,
        'currency' => 'USD',
        'is_free' => true,
        'is_paid' => false,
        'is_complimentary' => false,
        'merchant_access' => false,
        'social_access' => (bool) $user,
        'subscription' => null,
        'entitlement_source' => 'free_wallet',
        'entitlement_user_id' => (int) ($user['id'] ?? 0),
        'workspace_id' => null,
        'workspace_public_id' => null,
        'workspace_owner_user_id' => null,
        'workspace_role' => null,
        'features' => [
            'Gift inbox','Sent gifts','Claimed gifts','Social gifting','Wallet access',
            'Purchase and send gifts','Claim and track gifts',
        ],
        'limits' => [
            'max_microgifts' => 0,'max_rewards' => 0,'max_active_campaigns' => 0,
            'max_crm_contacts' => 0,'monthly_stamps_included' => 0,
            'max_landing_pages' => 0,'max_locations' => 0,'max_team_seats' => 0,
            'stamp_overage_enabled' => false,'bulk_stamp_purchase_enabled' => false,
            'email_stamps_enabled' => false,'sms_stamps_enabled' => false,
        ],
    ];
}
}

if (!function_exists('mg_package_entitlement_catalog_package')) {
function mg_package_entitlement_catalog_package(string $packageId): ?array
{
    $packageId = mg_package_entitlement_slug($packageId);
    if ($packageId === 'free') return mg_package_entitlement_free_context();
    foreach (mg_pricing_packages() as $package) {
        $id = mg_package_entitlement_slug((string) ($package['id'] ?? $package['name'] ?? ''));
        if ($id !== $packageId) continue;
        return [
            'package_id' => $id,
            'package_name' => (string) ($package['name'] ?? ucwords(str_replace('-', ' ', $id))),
            'features' => array_values((array) ($package['included_features'] ?? [])),
            'limits' => is_array($package['limits'] ?? null) ? $package['limits'] : [],
        ];
    }
    return null;
}
}

if (!function_exists('mg_package_entitlement_subscription_row')) {
function mg_package_entitlement_subscription_row(PDO $pdo, int $userId): ?array
{
    if ($userId < 1) return null;
    try {
        $stmt = $pdo->prepare(
            'SELECT s.*,p.name package_name,p.features_json,p.limits_json,p.is_self_serve,p.requires_admin_review
             FROM platform_account_subscriptions s
             LEFT JOIN platform_subscription_packages p ON p.package_id=s.package_id
             WHERE s.user_id=?
             ORDER BY FIELD(s.status,"active","trialing","cancel_pending","past_due","incomplete","pending_admin_review","paused","canceled","expired"),s.updated_at DESC,s.id DESC
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

if (!function_exists('mg_package_entitlement_workspace_membership')) {
function mg_package_entitlement_workspace_membership(PDO $pdo, int $userId): ?array
{
    if ($userId < 1) return null;
    try {
        $stmt = $pdo->prepare(
            "SELECT mtm.workspace_id,mtm.role_key,mtm.status member_status,mw.public_id workspace_public_id,
                    mw.merchant_user_id workspace_owner_user_id,mw.display_name workspace_name,mw.status workspace_status
             FROM merchant_team_members mtm
             INNER JOIN merchant_workspaces mw ON mw.id=mtm.workspace_id
             WHERE mtm.user_id=? AND mtm.status='active'
             ORDER BY FIELD(mtm.role_key,'owner','manager','marketing','marketer','staff','viewer'),mtm.accepted_at DESC,mtm.id DESC
             LIMIT 1"
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

if (!function_exists('mg_package_entitlement_subscription_is_active')) {
function mg_package_entitlement_subscription_is_active(array $subscription): bool
{
    $status = (string) ($subscription['status'] ?? '');
    if (!in_array($status, mg_package_entitlement_active_statuses(), true)) return false;
    $provider = strtolower(trim((string) ($subscription['provider_key'] ?? '')));
    $periodEnd = trim((string) ($subscription['current_period_end'] ?? ''));
    if ($provider === 'admin_grant' && $periodEnd !== '') {
        $expires = strtotime($periodEnd . ' UTC');
        if ($expires !== false && $expires < time()) return false;
    }
    return true;
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
    $context['entitlement_source'] = 'admin_override';
    $context['features'] = array_values((array) ($catalog['features'] ?? $context['features']));
    $context['limits'] = is_array($catalog['limits'] ?? null) ? $catalog['limits'] : [];
    return $context;
}
}

if (!function_exists('mg_package_entitlement_from_subscription')) {
function mg_package_entitlement_from_subscription(array $subscription, ?array $user, string $source, int $entitlementUserId, ?array $workspace = null): array
{
    $packageId = mg_package_entitlement_slug((string) ($subscription['package_id'] ?? 'free')) ?: 'free';
    $catalog = mg_package_entitlement_catalog_package($packageId) ?? mg_package_entitlement_free_context($user);
    $active = mg_package_entitlement_subscription_is_active($subscription);
    $metadata = mg_package_entitlement_decode_json($subscription['metadata_json'] ?? null);
    $provider = strtolower(trim((string) ($subscription['provider_key'] ?? '')));
    $complimentary = $provider === 'admin_grant' || !empty($metadata['complimentary']);
    $features = mg_package_entitlement_decode_json($subscription['features_json'] ?? null) ?: array_values((array) ($catalog['features'] ?? []));
    $limits = mg_package_entitlement_decode_json($subscription['limits_json'] ?? null) ?: (is_array($catalog['limits'] ?? null) ? $catalog['limits'] : []);
    return [
        'package_id' => $packageId,
        'package_name' => (string) ($subscription['package_name'] ?? $catalog['package_name'] ?? ucwords(str_replace('-', ' ', $packageId))),
        'status' => $active ? (string) ($subscription['status'] ?? 'active') : 'expired',
        'billing_cycle' => $subscription['billing_cycle'] ?? null,
        'amount_cents' => isset($subscription['amount_cents']) ? (int) $subscription['amount_cents'] : 0,
        'currency' => strtoupper((string) ($subscription['currency'] ?? 'USD')),
        'is_free' => !$active || $packageId === 'free',
        'is_paid' => $active && $packageId !== 'free' && !$complimentary,
        'is_complimentary' => $active && $packageId !== 'free' && $complimentary,
        'merchant_access' => $active && $packageId !== 'free',
        'social_access' => true,
        'subscription' => $subscription,
        'entitlement_source' => $source,
        'entitlement_user_id' => $entitlementUserId,
        'workspace_id' => isset($workspace['workspace_id']) ? (int) $workspace['workspace_id'] : null,
        'workspace_public_id' => $workspace['workspace_public_id'] ?? null,
        'workspace_owner_user_id' => isset($workspace['workspace_owner_user_id']) ? (int) $workspace['workspace_owner_user_id'] : null,
        'workspace_role' => $workspace['role_key'] ?? null,
        'features' => $features,
        'limits' => $limits,
    ];
}
}

if (!function_exists('mg_user_package_context')) {
function mg_user_package_context(?PDO $pdo = null, ?array $user = null): array
{
    $user = $user ?? (function_exists('mg_current_user') ? mg_current_user() : null);
    if (!$user) return mg_package_entitlement_free_context(null);
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (in_array('admin', $roles, true) || in_array('super_admin', $roles, true)) return mg_package_entitlement_admin_context($user);
    $userId = (int) ($user['id'] ?? 0);
    $pdo = $pdo ?: mg_db();
    $direct = mg_package_entitlement_subscription_row($pdo, $userId);
    if ($direct && mg_package_entitlement_subscription_is_active($direct)) {
        return mg_package_entitlement_from_subscription($direct, $user, 'direct_subscription', $userId);
    }
    $workspace = mg_package_entitlement_workspace_membership($pdo, $userId);
    if ($workspace) {
        $ownerId = (int) ($workspace['workspace_owner_user_id'] ?? 0);
        $ownerSubscription = mg_package_entitlement_subscription_row($pdo, $ownerId);
        if ($ownerSubscription && mg_package_entitlement_subscription_is_active($ownerSubscription)) {
            return mg_package_entitlement_from_subscription($ownerSubscription, $user, 'workspace_subscription', $ownerId, $workspace);
        }
    }
    $free = mg_package_entitlement_free_context($user);
    if ($direct) {
        $free['subscription'] = $direct;
        $free['status'] = (string) ($direct['status'] ?? 'expired');
    }
    if ($workspace) {
        $free['workspace_id'] = (int) ($workspace['workspace_id'] ?? 0);
        $free['workspace_public_id'] = $workspace['workspace_public_id'] ?? null;
        $free['workspace_owner_user_id'] = (int) ($workspace['workspace_owner_user_id'] ?? 0);
        $free['workspace_role'] = $workspace['role_key'] ?? null;
    }
    return $free;
}
}

if (!function_exists('mg_workspace_role_allows_permission')) {
function mg_workspace_role_allows_permission(array $context, string $permission): bool
{
    $role = strtolower(trim((string) ($context['workspace_role'] ?? '')));
    if ($role === '') return false;
    if (in_array($role, ['owner', 'manager'], true)) return true;
    $readOnly = str_ends_with($permission, '.view') || str_ends_with($permission, '.read') || str_contains($permission, '.analytics') || str_contains($permission, '.report');
    if ($role === 'viewer') return $readOnly;
    if (in_array($role, ['marketing', 'marketer'], true)) {
        return $readOnly || str_contains($permission, 'campaign') || str_contains($permission, 'crm') || str_contains($permission, 'reward') || str_contains($permission, 'feed') || str_contains($permission, 'design');
    }
    if ($role === 'staff') {
        return $readOnly || str_contains($permission, 'claim') || str_contains($permission, 'order') || str_contains($permission, 'scanner') || str_contains($permission, 'customer');
    }
    return false;
}
}

if (!function_exists('mg_user_has_merchant_access')) {
function mg_user_has_merchant_access(?array $user = null, ?PDO $pdo = null): bool
{
    return !empty(mg_user_package_context($pdo, $user)['merchant_access']);
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
    if (empty($context['merchant_access'])) return false;
    $limit = mg_package_limit_value($context, $limitKey);
    if ($limit === null || $limit === '') return true;
    return $currentUsage < max(0, (int) $limit);
}
}

if (!function_exists('mg_package_require_merchant_access')) {
function mg_package_require_merchant_access(PDO $pdo, array $user, string $message = 'Merchant access requires an active paid or complimentary package.'): array
{
    $context = mg_user_package_context($pdo, $user);
    if (!empty($context['merchant_access'])) return $context;
    if (function_exists('mg_fail')) mg_fail($message, 403);
    throw new RuntimeException($message);
}
}

if (!function_exists('mg_package_require_limit_available')) {
function mg_package_require_limit_available(PDO $pdo, array $user, string $limitKey, int $currentUsage, string $message): array
{
    $context = mg_package_require_merchant_access($pdo, $user);
    if (mg_package_limit_allows_create($context, $limitKey, $currentUsage)) return $context;
    $limit = mg_package_limit_value($context, $limitKey);
    $packageName = (string) ($context['package_name'] ?? 'current');
    $detail = $limit === null ? $message : $message . ' Your ' . $packageName . ' package limit is ' . (int) $limit . '.';
    if (function_exists('mg_fail')) mg_fail($detail, 402);
    throw new RuntimeException($detail);
}
}
