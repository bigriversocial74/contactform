<?php
/**
 * Canonical admin permission matrix.
 *
 * Keep this file UI-neutral. Sidebar/template code may read it later, but this
 * phase uses it for backend/API enforcement and documentation alignment only.
 */
declare(strict_types=1);

function mg_admin_permission_matrix(): array
{
    return [
        'pages' => [
            'admin.dashboard' => ['admin.dashboard.view'],
            'admin.users' => ['admin.users.view'],
            'admin.sessions' => ['admin.sessions.view'],
            'admin.audit_logs' => ['admin.audit.view'],
            'admin.security_logs' => ['security.logs.view', 'admin.security_logs.view'],
            'admin.system_health' => ['admin.health.view'],
            'admin.settings' => ['admin.settings.manage'],
            'admin.commerce' => ['admin.commerce.view'],
        ],
        'commerce_domains' => [
            'all' => 'admin.commerce.view',
            'order' => 'admin.commerce.orders.view',
            'refund' => 'admin.commerce.refunds.view',
            'dispute' => 'admin.commerce.disputes.view',
            'subscription' => 'admin.commerce.subscriptions.view',
            'tip' => 'admin.commerce.tips.view',
            'microgift' => 'admin.commerce.microgifts.view',
            'case' => 'admin.commerce.cases.view',
        ],
        'commerce_actions' => [
            'open_case' => 'admin.commerce.cases.manage',
            'assign_case' => 'admin.commerce.cases.manage',
            'add_case_note' => 'admin.commerce.cases.manage',
            'resolve_case' => 'admin.commerce.cases.manage',
            'dismiss_case' => 'admin.commerce.cases.manage',
            'reopen_case' => 'admin.commerce.cases.manage',
            'reverse_tip' => 'admin.commerce.tips.reverse',
        ],
        'aliases' => [
            // Transitional compatibility while older roles/branches are being merged.
            'admin.security_logs.view' => ['security.logs.view'],
            'admin.commerce.orders.view' => ['admin.commerce.view', 'merchant.payments.view'],
            'admin.commerce.refunds.view' => ['admin.commerce.view', 'merchant.payments.view'],
            'admin.commerce.disputes.view' => ['admin.commerce.view', 'merchant.payments.view'],
            'admin.commerce.subscriptions.view' => ['admin.commerce.view', 'subscriptions.admin'],
            'admin.commerce.tips.view' => ['admin.commerce.view', 'tips.reverse'],
            'admin.commerce.microgifts.view' => ['admin.commerce.view', 'microgift.operations.view'],
            'admin.commerce.cases.view' => ['admin.commerce.view', 'admin.commerce.manage'],
            'admin.commerce.cases.manage' => ['admin.commerce.manage'],
            'admin.commerce.tips.reverse' => ['tips.reverse'],
        ],
    ];
}

function mg_admin_permission_aliases(string $permission): array
{
    $matrix = mg_admin_permission_matrix();
    return array_values(array_unique($matrix['aliases'][$permission] ?? []));
}

function mg_admin_permission_equivalents(string $permission): array
{
    return array_values(array_unique(array_merge([$permission], mg_admin_permission_aliases($permission))));
}

function mg_admin_commerce_domain_permission(string $domain): string
{
    $matrix = mg_admin_permission_matrix();
    return $matrix['commerce_domains'][$domain] ?? 'admin.commerce.view';
}

function mg_admin_commerce_action_required_permission(string $action): string
{
    $matrix = mg_admin_permission_matrix();
    return $matrix['commerce_actions'][$action] ?? 'admin.commerce.cases.manage';
}

function mg_admin_permission_user_has(array $user, string $permission): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (in_array('super_admin', $roles, true)) {
        return true;
    }

    foreach (mg_admin_permission_equivalents($permission) as $candidate) {
        if (function_exists('mg_api_user_has_permission') && mg_api_user_has_permission($user, $candidate)) {
            return true;
        }

        $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
        if (in_array($candidate, $permissions, true)) {
            return true;
        }
    }

    return false;
}

function mg_admin_commerce_read_permissions(): array
{
    $matrix = mg_admin_permission_matrix();
    return array_values($matrix['commerce_domains']);
}

function mg_admin_commerce_user_can_read_any(array $user): bool
{
    foreach (mg_admin_commerce_read_permissions() as $permission) {
        if (mg_admin_permission_user_has($user, $permission)) {
            return true;
        }
    }
    return false;
}
