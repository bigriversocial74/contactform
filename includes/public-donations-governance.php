<?php
declare(strict_types=1);

require_once __DIR__ . '/public-donations-feature.php';

/**
 * Public Donations Phase 9 governance and lifecycle hardening.
 *
 * This layer centralizes workspace ownership, action-specific authorization,
 * rollout enforcement, request throttling, hourly operation budgets, audit
 * telemetry, and privacy-safe release metadata. Reward lifecycle mutations
 * remain in the allocation and recall engines.
 */

const MG_PUBLIC_DONATIONS_GOVERNANCE_ACTIONS = [
    'view' => 'merchant.public_donations.view',
    'manage' => 'merchant.public_donations.manage',
    'assign' => 'merchant.public_donations.assign',
    'allocate' => 'merchant.public_donations.allocate',
    'recall' => 'merchant.public_donations.recall',
    'report' => 'merchant.public_donations.report',
];

function mg_public_donations_governance_fail(string $message, int $status = 403): never
{
    if (function_exists('mg_fail')) {
        mg_fail($message, $status);
    }
    throw new RuntimeException($message, $status);
}

function mg_public_donations_governance_permission(string $action): string
{
    $action = strtolower(trim($action));
    if (!isset(MG_PUBLIC_DONATIONS_GOVERNANCE_ACTIONS[$action])) {
        mg_public_donations_governance_fail('Invalid Public Donations governance action.', 500);
    }
    return MG_PUBLIC_DONATIONS_GOVERNANCE_ACTIONS[$action];
}

function mg_public_donations_governance_workspace_role(array $context): string
{
    return strtolower(trim((string)($context['workspace_role'] ?? '')));
}

function mg_public_donations_governance_workspace_allows(array $context, string $action): bool
{
    $role = mg_public_donations_governance_workspace_role($context);
    if (in_array($role, ['owner', 'manager'], true)) {
        return true;
    }
    if (in_array($action, ['view', 'report'], true)) {
        return in_array($role, ['marketing', 'marketer', 'staff', 'viewer'], true);
    }
    if (in_array($action, ['manage', 'assign'], true)) {
        return in_array($role, ['marketing', 'marketer'], true);
    }
    return false;
}

function mg_public_donations_governance_actor_active(PDO $pdo, int $actorId): bool
{
    if ($actorId < 1) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$actorId]);
    return strtolower((string)$stmt->fetchColumn()) === 'active';
}

function mg_public_donations_governance_log_denial(
    string $reason,
    string $action,
    int $merchantId,
    int $actorId,
    array $context = []
): void {
    $metadata = array_merge([
        'reason' => $reason,
        'action' => $action,
        'permission' => MG_PUBLIC_DONATIONS_GOVERNANCE_ACTIONS[$action] ?? null,
        'merchant_user_id' => $merchantId,
    ], $context);

    if (function_exists('mg_audit')) {
        mg_audit('merchant.public_donations.governance_denied', 'security', $metadata, $actorId > 0 ? $actorId : null);
    }
    if (function_exists('mg_security_log')) {
        mg_security_log(
            'warning',
            'public_donations.governance_denied',
            'Public Donations governance denied a request.',
            $metadata,
            $actorId > 0 ? $actorId : null
        );
    }
}

/**
 * Resolve the merchant workspace owner separately from the authenticated actor.
 * Team members operate on the owner's merchant records while all audit history
 * remains attributed to the team member who performed the action.
 */
function mg_public_donations_governance_context(PDO $pdo, array $user, string $action): array
{
    $action = strtolower(trim($action));
    $permission = mg_public_donations_governance_permission($action);
    $actorId = (int)($user['id'] ?? 0);

    if (!function_exists('mg_merchant_ensure_workspace')) {
        mg_public_donations_governance_fail('Merchant workspace governance is unavailable.', 503);
    }
    $workspace = mg_merchant_ensure_workspace($pdo, $user);
    $merchantId = (int)($workspace['merchant_user_id'] ?? 0);
    if ($merchantId < 1) {
        mg_public_donations_governance_fail('Merchant workspace owner is unavailable.', 503);
    }

    $packageContext = function_exists('mg_user_package_context')
        ? mg_user_package_context($pdo, $user)
        : [];
    $workspaceRole = mg_public_donations_governance_workspace_role($packageContext);

    if (!mg_public_donations_governance_actor_active($pdo, $actorId)) {
        mg_public_donations_governance_log_denial('actor_inactive', $action, $merchantId, $actorId, [
            'workspace_role' => $workspaceRole,
        ]);
        mg_public_donations_governance_fail('This account is not active.', 403);
    }

    if (!mg_public_donations_is_enabled_for($merchantId, $user)) {
        mg_public_donations_governance_log_denial('feature_disabled', $action, $merchantId, $actorId, [
            'feature_state' => mg_public_donations_feature_state(),
            'workspace_role' => $workspaceRole,
        ]);
        mg_public_donations_governance_fail('Public Donations is not enabled for this merchant.', 403);
    }

    $allowed = mg_public_donations_actor_is_admin($user)
        || $actorId === $merchantId
        || (function_exists('mg_api_user_has_permission') && mg_api_user_has_permission($user, $permission))
        || mg_public_donations_governance_workspace_allows($packageContext, $action);

    if (!$allowed) {
        mg_public_donations_governance_log_denial('permission_denied', $action, $merchantId, $actorId, [
            'workspace_role' => $workspaceRole,
        ]);
        mg_public_donations_governance_fail('You are not authorized for this Public Donations action.', 403);
    }

    return [
        'action' => $action,
        'permission' => $permission,
        'actor_user_id' => $actorId,
        'merchant_user_id' => $merchantId,
        'workspace_id' => (int)($workspace['id'] ?? 0),
        'workspace_role' => $workspaceRole,
        'feature' => mg_public_donations_feature_context($merchantId, $user),
    ];
}

function mg_public_donations_governance_rate_limit(string $action, int $merchantId, int $actorId): void
{
    if (!function_exists('mg_rate_limit')) {
        mg_public_donations_governance_fail('Request throttling is unavailable.', 503);
    }

    $limits = [
        'manage' => [60, 600],
        'assign' => [120, 600],
        'allocate' => [60, 600],
        'recall' => [60, 600],
    ];
    if (!isset($limits[$action])) {
        return;
    }

    [$maximum, $window] = $limits[$action];
    $identifier = implode(':', [
        'merchant', $merchantId,
        'actor', $actorId,
        'ip', function_exists('mg_client_ip') ? (mg_client_ip() ?? 'unknown') : 'unknown',
    ]);
    mg_rate_limit('public_donations.' . $action, $identifier, $maximum, $window);
}

function mg_public_donations_governance_hourly_limit(string $kind): int
{
    $kind = strtolower(trim($kind));
    $environment = $kind === 'recall'
        ? 'MG_PUBLIC_DONATIONS_RECALL_UNITS_PER_HOUR'
        : 'MG_PUBLIC_DONATIONS_ALLOCATION_UNITS_PER_HOUR';
    $fallback = $kind === 'recall' ? 2000 : 5000;
    $configured = filter_var(getenv($environment) ?: null, FILTER_VALIDATE_INT);
    return max(1, min(100000, $configured === false ? $fallback : (int)$configured));
}

/**
 * Enforce a merchant-wide hourly unit budget from inside an existing database
 * transaction. Completed idempotent replays return before this function, so a
 * replay never consumes the budget twice.
 */
function mg_public_donations_governance_assert_hourly_budget(
    PDO $pdo,
    int $merchantId,
    string $kind,
    int $requestedQuantity
): array {
    $kind = strtolower(trim($kind));
    if (!in_array($kind, ['allocation', 'recall'], true)) {
        mg_public_donations_governance_fail('Invalid Public Donations operation budget.', 500);
    }
    if (!$pdo->inTransaction()) {
        mg_public_donations_governance_fail('Public Donations operation budget requires a transaction.', 500);
    }

    $requestedQuantity = max(1, $requestedQuantity);
    $limit = mg_public_donations_governance_hourly_limit($kind);
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE WHEN status='completed' THEN completed_quantity ELSE requested_quantity END),0)
           FROM campaign_donation_operations
          WHERE merchant_user_id=?
            AND operation_kind=?
            AND status IN ('processing','completed')
            AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)
          FOR UPDATE"
    );
    $stmt->execute([$merchantId, $kind]);
    $used = (int)$stmt->fetchColumn();
    if (($used + $requestedQuantity) > $limit) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'public_donations.hourly_budget_blocked', 'Public Donations hourly unit budget blocked an operation.', [
                'merchant_user_id' => $merchantId,
                'operation_kind' => $kind,
                'used_units' => $used,
                'requested_units' => $requestedQuantity,
                'limit_units' => $limit,
            ]);
        }
        mg_public_donations_governance_fail('The hourly Public Donations operation limit has been reached.', 429);
    }

    return [
        'kind' => $kind,
        'used_units' => $used,
        'requested_units' => $requestedQuantity,
        'limit_units' => $limit,
        'remaining_after' => max(0, $limit - $used - $requestedQuantity),
    ];
}

function mg_public_donations_governance_log_success(
    string $action,
    int $merchantId,
    int $actorId,
    array $context = []
): void {
    $metadata = array_merge([
        'action' => $action,
        'merchant_user_id' => $merchantId,
    ], $context);
    if (function_exists('mg_audit')) {
        mg_audit('merchant.public_donations.' . $action, 'public_donations', $metadata, $actorId);
    }
    if (function_exists('mg_security_log')) {
        mg_security_log('info', 'public_donations.' . $action, 'Public Donations governance recorded an authorized action.', $metadata, $actorId);
    }
}

function mg_public_donations_governance_operational_copy(): array
{
    return [
        'funding_type' => 'merchant_funded_promotional_rewards',
        'cash_donation' => false,
        'tax_deductible_charitable_contribution' => false,
        'statement' => 'Public Donations are merchant-funded promotional rewards. They are not cash donations or tax-deductible charitable contributions.',
    ];
}

function mg_public_donations_governance_privacy_contract(): array
{
    return [
        'original_community_identity_requires_eligible_profile' => true,
        'private_or_unavailable_accounts_are_aggregate_only' => true,
        'final_recipient_identity_exposed' => false,
        'claim_codes_exposed' => false,
        'ownership_identifiers_exposed' => false,
        'anonymized_commerce_evidence_preserved' => true,
        'campaign_attribution_preserved' => true,
    ];
}

/**
 * Return counts only. No Community or downstream identity is selected.
 */
function mg_public_donations_governance_integrity(PDO $pdo, int $merchantId): array
{
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS reward_rows,
            COALESCE(SUM(reward.status='recalled'),0) AS recalled_rows,
            COUNT(DISTINCT reward.campaign_id) AS campaign_rows,
            COUNT(DISTINCT reward.original_community_user_id) AS original_accounts,
            COALESCE(SUM(user.id IS NULL OR user.status<>'active'),0) AS unavailable_original_accounts,
            COALESCE(SUM(profile.user_id IS NULL OR profile.status<>'active' OR profile.visibility NOT IN ('public','unlisted')),0) AS aggregate_only_rows
         FROM campaign_donation_rewards reward
         INNER JOIN campaigns campaign
                 ON campaign.id=reward.campaign_id
                AND campaign.merchant_user_id=reward.merchant_user_id
                AND campaign.campaign_type='public_donation'
         LEFT JOIN users user ON user.id=reward.original_community_user_id
         LEFT JOIN public_profiles profile ON profile.user_id=reward.original_community_user_id
        WHERE reward.merchant_user_id=?"
    );
    $stmt->execute([$merchantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'reward_rows' => (int)($row['reward_rows'] ?? 0),
        'recalled_rows' => (int)($row['recalled_rows'] ?? 0),
        'campaign_rows' => (int)($row['campaign_rows'] ?? 0),
        'original_accounts' => (int)($row['original_accounts'] ?? 0),
        'unavailable_original_account_rows' => (int)($row['unavailable_original_accounts'] ?? 0),
        'aggregate_only_rows' => (int)($row['aggregate_only_rows'] ?? 0),
        'commerce_evidence_preserved' => true,
        'campaign_attribution_preserved' => true,
        'identity_values_returned' => false,
    ];
}
