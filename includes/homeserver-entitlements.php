<?php
declare(strict_types=1);

require_once __DIR__ . '/package-entitlements.php';

if (!function_exists('mg_homeserver_capability_registry')) {
function mg_homeserver_capability_registry(): array
{
    return [
        'homeserver.download',
        'homeserver.manage',
        'homeserver.pair',
        'homeserver.cloud_sync',
        'homeserver.operational_data',
        'homeserver.agent_actions',
        'homeserver.feature_updates',
        'homeserver.beta_updates',
        'homeserver.device_limit',
    ];
}
}

if (!function_exists('mg_homeserver_package_policy')) {
function mg_homeserver_package_policy(string $packageId): array
{
    $packageId = mg_package_entitlement_slug($packageId);
    $baseCapabilities = [
        'homeserver.download',
        'homeserver.manage',
        'homeserver.pair',
        'homeserver.cloud_sync',
        'homeserver.operational_data',
        'homeserver.agent_actions',
        'homeserver.feature_updates',
        'homeserver.device_limit',
    ];

    return match ($packageId) {
        'starter' => [
            'package_id' => 'starter',
            'capabilities' => $baseCapabilities,
            'device_limit' => 1,
        ],
        'growth' => [
            'package_id' => 'growth',
            'capabilities' => $baseCapabilities,
            'device_limit' => 1,
        ],
        'pro' => [
            'package_id' => 'pro',
            'capabilities' => array_values(array_unique(array_merge($baseCapabilities, ['homeserver.beta_updates']))),
            'device_limit' => 2,
        ],
        'enterprise' => [
            'package_id' => 'enterprise',
            'capabilities' => array_values(array_unique(array_merge($baseCapabilities, ['homeserver.beta_updates']))),
            'device_limit' => null,
        ],
        default => [
            'package_id' => 'free',
            'capabilities' => [],
            'device_limit' => 0,
        ],
    };
}
}

if (!function_exists('mg_homeserver_entitlement_context')) {
function mg_homeserver_entitlement_context(?PDO $pdo = null, ?array $user = null): array
{
    $user = $user ?? (function_exists('mg_current_user') ? mg_current_user() : null);
    if (!$user) {
        return [
            'state' => 'not_included',
            'included' => false,
            'owner_eligible' => false,
            'package_id' => 'free',
            'package_name' => 'Free Wallet',
            'subscription_status' => 'signed_out',
            'entitlement_source' => 'signed_out',
            'capabilities' => [],
            'device_limit' => 0,
            'can_download' => false,
            'can_manage' => false,
            'can_pair' => false,
            'can_cloud_sync' => false,
            'can_operational_data' => false,
            'can_agent_actions' => false,
            'can_feature_updates' => false,
            'can_beta_updates' => false,
            'message' => 'Sign in to review HomeServer access.',
        ];
    }

    $pdo ??= mg_db();
    $package = mg_user_package_context($pdo, $user);
    $userId = (int)($user['id'] ?? 0);
    $source = strtolower(trim((string)($package['entitlement_source'] ?? 'free_wallet'));
    $entitlementUserId = (int)($package['entitlement_user_id'] ?? 0);
    $packageId = mg_package_entitlement_slug((string)($package['package_id'] ?? 'free')) ?: 'free';
    $subscriptionStatus = strtolower(trim((string)($package['status'] ?? 'free'));
    $activePackage = !empty($package['merchant_access']) && $packageId !== 'free';
    $ownerEligible = $source === 'admin_override'
        || ($source === 'direct_subscription' && $entitlementUserId === $userId && $userId > 0);
    $workspaceDerived = $source === 'workspace_subscription';

    $policy = mg_homeserver_package_policy($activePackage ? $packageId : 'free');
    $capabilities = $activePackage && $ownerEligible
        ? array_values((array)($policy['capabilities'] ?? []))
        : [];

    if ($activePackage && $workspaceDerived) {
        $state = 'owner_required';
        $message = 'HomeServer is managed by the merchant workspace owner. Delegated device management is not enabled yet.';
    } elseif ($activePackage && $ownerEligible && in_array($subscriptionStatus, ['past_due', 'cancel_pending'], true)) {
        $state = 'grace';
        $message = 'HomeServer access remains available while the subscription needs attention.';
    } elseif ($activePackage && $ownerEligible) {
        $state = 'included';
        $message = 'HomeServer is included with this Microgifter package.';
    } elseif (in_array($subscriptionStatus, ['paused', 'canceled', 'expired', 'incomplete', 'pending_admin_review'], true)) {
        $state = 'suspended';
        $message = 'HomeServer cloud access is not active for the current subscription state.';
    } else {
        $state = 'not_included';
        $message = 'HomeServer requires an active paid or complimentary Microgifter package.';
    }

    $has = static fn(string $capability): bool => in_array($capability, $capabilities, true);

    return [
        'state' => $state,
        'included' => $activePackage && $ownerEligible,
        'owner_eligible' => $ownerEligible,
        'workspace_derived' => $workspaceDerived,
        'package_id' => $packageId,
        'package_name' => (string)($package['package_name'] ?? 'Free Wallet'),
        'subscription_status' => $subscriptionStatus,
        'entitlement_source' => $source,
        'entitlement_user_id' => $entitlementUserId,
        'workspace_id' => $package['workspace_id'] ?? null,
        'workspace_owner_user_id' => $package['workspace_owner_user_id'] ?? null,
        'workspace_role' => $package['workspace_role'] ?? null,
        'is_paid' => !empty($package['is_paid']),
        'is_complimentary' => !empty($package['is_complimentary']),
        'capabilities' => $capabilities,
        'device_limit' => $activePackage && $ownerEligible ? ($policy['device_limit'] ?? 0) : 0,
        'can_download' => $has('homeserver.download'),
        'can_manage' => $has('homeserver.manage'),
        'can_pair' => $has('homeserver.pair'),
        'can_cloud_sync' => $has('homeserver.cloud_sync'),
        'can_operational_data' => $has('homeserver.operational_data'),
        'can_agent_actions' => $has('homeserver.agent_actions'),
        'can_feature_updates' => $has('homeserver.feature_updates'),
        'can_beta_updates' => $has('homeserver.beta_updates'),
        'message' => $message,
    ];
}
}

if (!function_exists('mg_homeserver_entitlement_has')) {
function mg_homeserver_entitlement_has(array $entitlement, string $capability): bool
{
    return in_array($capability, (array)($entitlement['capabilities'] ?? []), true);
}
}

if (!function_exists('mg_homeserver_active_device_count')) {
function mg_homeserver_active_device_count(PDO $pdo, int $ownerUserId): int
{
    if ($ownerUserId < 1) return 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT installation_id) FROM homeserver_devices WHERE owner_user_id=? AND status='active'");
        $stmt->execute([$ownerUserId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}
}

if (!function_exists('mg_homeserver_entitlement_payload')) {
function mg_homeserver_entitlement_payload(PDO $pdo, array $user, ?array $entitlement = null): array
{
    $entitlement ??= mg_homeserver_entitlement_context($pdo, $user);
    $activeDevices = mg_homeserver_active_device_count($pdo, (int)($user['id'] ?? 0));
    $deviceLimit = $entitlement['device_limit'] ?? 0;
    $remaining = $deviceLimit === null ? null : max(0, (int)$deviceLimit - $activeDevices);

    return [
        'state' => (string)($entitlement['state'] ?? 'not_included'),
        'included' => !empty($entitlement['included']),
        'owner_eligible' => !empty($entitlement['owner_eligible']),
        'package_id' => (string)($entitlement['package_id'] ?? 'free'),
        'package_name' => (string)($entitlement['package_name'] ?? 'Free Wallet'),
        'subscription_status' => (string)($entitlement['subscription_status'] ?? 'free'),
        'entitlement_source' => (string)($entitlement['entitlement_source'] ?? 'free_wallet'),
        'is_paid' => !empty($entitlement['is_paid']),
        'is_complimentary' => !empty($entitlement['is_complimentary']),
        'capabilities' => array_values((array)($entitlement['capabilities'] ?? [])),
        'device_limit' => $deviceLimit,
        'active_device_count' => $activeDevices,
        'remaining_device_slots' => $remaining,
        'can_download' => !empty($entitlement['can_download']),
        'can_manage' => !empty($entitlement['can_manage']),
        'can_pair' => !empty($entitlement['can_pair']),
        'can_cloud_sync' => !empty($entitlement['can_cloud_sync']),
        'can_operational_data' => !empty($entitlement['can_operational_data']),
        'can_agent_actions' => !empty($entitlement['can_agent_actions']),
        'can_feature_updates' => !empty($entitlement['can_feature_updates']),
        'can_beta_updates' => !empty($entitlement['can_beta_updates']),
        'message' => (string)($entitlement['message'] ?? ''),
        'upgrade_url' => '/account-subscriptions.php?homeserver=upgrade',
        'manage_url' => !empty($entitlement['can_manage']) ? '/account-homeserver.php' : null,
    ];
}
}

if (!function_exists('mg_homeserver_require_capability')) {
function mg_homeserver_require_capability(PDO $pdo, array $user, string $capability, string $message): array
{
    $entitlement = mg_homeserver_entitlement_context($pdo, $user);
    if (mg_homeserver_entitlement_has($entitlement, $capability)) return $entitlement;

    $details = ['entitlement' => mg_homeserver_entitlement_payload($pdo, $user, $entitlement)];
    if (function_exists('mg_fail')) mg_fail($message, 403, $details);
    throw new RuntimeException($message);
}
}
