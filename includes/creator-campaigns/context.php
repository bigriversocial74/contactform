<?php
declare(strict_types=1);

function mg_creator_campaign_user_has_platform_permission(PDO $pdo, array $user, string $permission): bool
{
    if (mg_creator_campaign_is_admin_actor($user)) {
        return true;
    }
    if (function_exists('mg_api_user_has_permission') && mg_api_user_has_permission($user, $permission)) {
        return true;
    }

    $userId = (int) ($user['id'] ?? 0);
    if ($userId < 1) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM user_roles ur
         INNER JOIN role_permissions rp ON rp.role_id=ur.role_id
         INNER JOIN permissions p ON p.id=rp.permission_id
         WHERE ur.user_id=? AND p.slug=? LIMIT 1'
    );
    $stmt->execute([$userId, $permission]);
    return (bool) $stmt->fetchColumn();
}

function mg_creator_campaign_require_active_merchant_model(array $user): void
{
    if (mg_creator_campaign_is_admin_actor($user)) {
        return;
    }

    $userId = (int) ($user['id'] ?? 0);
    if ($userId < 1 || !function_exists('mg_user_has_active_model') || !mg_user_has_active_model($userId, 'merchant')) {
        throw new DomainException('An active Merchant model is required.');
    }

    if (function_exists('mg_current_active_model_context')) {
        $activeModel = mg_current_active_model_context($userId);
        if ($activeModel !== 'merchant') {
            throw new DomainException('Switch to the Merchant model before managing creator campaigns.');
        }
    }
}

function mg_creator_campaign_resolve_workspace(
    PDO $pdo,
    array $user,
    ?int $requestedWorkspaceId = null,
    bool $forUpdate = false
): array {
    $userId = (int) ($user['id'] ?? 0);
    if ($userId < 1) {
        throw new DomainException('Authentication is required.');
    }

    $statusStmt = $pdo->prepare('SELECT status FROM users WHERE id=? LIMIT 1');
    $statusStmt->execute([$userId]);
    if ((string) ($statusStmt->fetchColumn() ?: '') !== 'active') {
        throw new DomainException('An active user account is required.');
    }

    mg_creator_campaign_require_active_merchant_model($user);
    $isAdmin = mg_creator_campaign_is_admin_actor($user);
    $context = function_exists('mg_user_package_context')
        ? mg_user_package_context($pdo, $user)
        : [];
    if (!$isAdmin && empty($context['merchant_access'])) {
        throw new DomainException('An active paid or complimentary merchant package is required.');
    }

    $workspaceId = (int) ($context['workspace_id'] ?? 0);
    $workspaceRole = strtolower(trim((string) ($context['workspace_role'] ?? '')));
    $ownerUserId = (int) ($context['entitlement_user_id'] ?? $userId);

    if ($isAdmin && $requestedWorkspaceId !== null) {
        $workspaceId = $requestedWorkspaceId;
        $workspaceRole = 'admin';
    } elseif ($workspaceId < 1) {
        $stmt = $pdo->prepare(
            'SELECT id FROM merchant_workspaces WHERE merchant_user_id=? LIMIT 1'
            . ($forUpdate ? ' FOR UPDATE' : '')
        );
        $stmt->execute([$ownerUserId]);
        $workspaceId = (int) ($stmt->fetchColumn() ?: 0);
        if ($workspaceId > 0 && $workspaceRole === '') {
            $workspaceRole = $ownerUserId === $userId ? 'owner' : '';
        }
    }

    if ($workspaceId < 1) {
        throw new DomainException('Merchant workspace has not been created.');
    }
    if (!$isAdmin && $requestedWorkspaceId !== null && $requestedWorkspaceId !== $workspaceId) {
        throw new DomainException('Cross-workspace creator campaign access is not allowed.');
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM merchant_workspaces WHERE id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$workspaceId]);
    $workspace = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$workspace || (string) ($workspace['status'] ?? '') !== 'active') {
        throw new DomainException('An active merchant workspace is required.');
    }

    $workspace['workspace_role'] = $workspaceRole;
    $workspace['package_context'] = $context;
    return $workspace;
}

function mg_creator_campaign_workspace_role_allows(string $role, string $permission): bool
{
    $role = strtolower(trim($role));
    if (in_array($role, ['owner', 'admin', 'manager'], true)) {
        return true;
    }
    if (in_array($role, ['analyst', 'viewer'], true)) {
        return in_array($permission, [
            'merchant.creator_campaigns.view',
            'merchant.creator_directory.view',
        ], true);
    }
    if (in_array($role, ['location_staff', 'claims_staff'], true)) {
        return $permission === 'merchant.creator_campaigns.view';
    }
    return false;
}

function mg_creator_campaign_require_permission(
    PDO $pdo,
    array $user,
    array $workspace,
    string $permission
): void {
    $userId = (int) ($user['id'] ?? 0);
    if (mg_creator_campaign_is_admin_actor($user)) {
        return;
    }

    $platformAllowed = mg_creator_campaign_user_has_platform_permission($pdo, $user, $permission);
    $isWorkspaceOwner = $userId > 0 && $userId === (int) ($workspace['merchant_user_id'] ?? 0);
    $workspaceRole = strtolower(trim((string) ($workspace['workspace_role'] ?? '')));
    $workspaceAllowed = mg_creator_campaign_workspace_role_allows($workspaceRole, $permission);

    if ($platformAllowed && ($isWorkspaceOwner || $workspaceAllowed)) {
        return;
    }

    if (function_exists('mg_audit')) {
        mg_audit('permission_denied', 'creator_campaign', [
            'permission' => $permission,
            'workspace_id' => (int) ($workspace['id'] ?? 0),
            'workspace_role' => $workspace['workspace_role'] ?? null,
        ], $userId ?: null);
    }
    throw new DomainException('Creator campaign permission is not enabled for this account or workspace role.');
}

function mg_creator_campaign_actor_context(
    PDO $pdo,
    array $user,
    string $permission,
    ?int $requestedWorkspaceId = null,
    bool $forUpdate = false
): array {
    $workspace = mg_creator_campaign_resolve_workspace($pdo, $user, $requestedWorkspaceId, $forUpdate);
    mg_creator_campaign_require_permission($pdo, $user, $workspace, $permission);
    return [
        'actor_user_id' => (int) ($user['id'] ?? 0),
        'workspace_id' => (int) $workspace['id'],
        'workspace_owner_user_id' => (int) $workspace['merchant_user_id'],
        'workspace_role' => $workspace['workspace_role'] ?? null,
        'workspace' => $workspace,
    ];
}

function mg_creator_campaign_creator_eligibility(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        "SELECT u.id user_id,u.status user_status,cp.id creator_profile_id,cp.public_id creator_profile_public_id,
                cp.status creator_profile_status,uma.status creator_assignment_status
         FROM users u
         LEFT JOIN user_models um ON um.code='creator'
         LEFT JOIN user_model_assignments uma ON uma.user_id=u.id AND uma.user_model_id=um.id
         LEFT JOIN creator_profiles cp ON cp.user_id=u.id
         WHERE u.id=? LIMIT 1"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $reasons = [];
    if (($row['user_status'] ?? null) !== 'active') {
        $reasons[] = 'user_inactive';
    }
    if (($row['creator_assignment_status'] ?? null) !== 'active') {
        $reasons[] = 'creator_model_inactive';
    }
    if (($row['creator_profile_status'] ?? null) !== 'active') {
        $reasons[] = 'creator_profile_inactive';
    }

    return [
        'eligible' => $reasons === [],
        'user_id' => $userId,
        'creator_profile_id' => isset($row['creator_profile_id']) ? (int) $row['creator_profile_id'] : null,
        'creator_profile_public_id' => $row['creator_profile_public_id'] ?? null,
        'reasons' => $reasons,
    ];
}

function mg_creator_campaign_require_creator_eligibility(PDO $pdo, int $userId): array
{
    $eligibility = mg_creator_campaign_creator_eligibility($pdo, $userId);
    if (!$eligibility['eligible']) {
        throw new DomainException('Creator eligibility requirements are not satisfied.');
    }
    return $eligibility;
}
