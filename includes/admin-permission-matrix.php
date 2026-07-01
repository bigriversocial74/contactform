<?php
declare(strict_types=1);

function mg_admin_permission_matrix(): array
{
    return [
        'pages' => [
            'admin.dashboard' => ['admin.dashboard.view'],
            'admin.users' => ['admin.users.view'],
            'admin.pending_models' => ['admin.users.view'],
            'admin.merchant_catalog' => ['admin.merchants.view', 'admin.catalog.view'],
            'admin.commerce' => ['admin.commerce.view'],
            'admin.subscription_requests' => ['subscriptions.admin'],
            'admin.payments' => ['admin.settings.manage'],
            'admin.moderation' => ['social.moderate', 'admin.profiles.moderation.view', 'admin.profiles.moderation.manage'],
            'admin.sessions' => ['admin.sessions.view'],
            'admin.audit_logs' => ['admin.audit.view'],
            'admin.security_logs' => ['security.logs.view', 'admin.security_logs.view'],
            'admin.system_health' => ['admin.health.view'],
            'admin.lifecycle_health' => ['admin.health.view'],
            'admin.ops_queue' => ['ops.alerts.assign', 'ops.alerts.resolve'],
            'admin.support_queue' => ['admin.support_queue.view', 'admin.user_notes.view', 'admin.users.manage'],
            'admin.notifications' => ['admin.notifications.view', 'admin.support_queue.view', 'admin.user_notes.view', 'admin.users.manage'],
            'admin.operations_command' => ['admin.operations_command.view', 'admin.support_queue.view', 'admin.queue_automation.view', 'admin.queue_reporting.view', 'admin.notifications.view', 'admin.users.manage'],
            'admin.settings' => ['admin.settings.manage'],
            'admin.pwa_branding' => ['admin.pwa_branding.view', 'admin.pwa_branding.manage', 'admin.settings.manage'],
            'admin.ai' => ['admin.settings.manage'],
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
            'admin.security_logs.view' => ['security.logs.view'],
            'admin.support_queue.view' => ['admin.user_notes.view', 'admin.users.manage'],
            'admin.support_queue.manage' => ['admin.user_notes.manage', 'admin.users.manage'],
            'admin.notifications.view' => ['admin.support_queue.view', 'admin.user_notes.view', 'admin.users.manage'],
            'admin.notifications.manage' => ['admin.support_queue.manage', 'admin.user_notes.manage', 'admin.users.manage'],
            'admin.operations_command.view' => ['admin.support_queue.view', 'admin.queue_automation.view', 'admin.queue_reporting.view', 'admin.notifications.view', 'admin.users.manage'],
            'admin.operations_command.manage' => ['admin.queue_automation.run', 'admin.support_queue.manage', 'admin.users.manage'],
            'admin.pwa_branding.view' => ['admin.settings.manage'],
            'admin.pwa_branding.manage' => ['admin.settings.manage'],
            'admin.pwa_notifications.test' => ['admin.pwa_branding.manage', 'admin.settings.manage'],
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

    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    foreach (mg_admin_permission_equivalents($permission) as $candidate) {
        if (function_exists('mg_api_user_has_permission') && mg_api_user_has_permission($user, $candidate)) {
            return true;
        }
        if (in_array($candidate, $permissions, true)) {
            return true;
        }
    }

    return false;
}

function mg_admin_permission_user_has_any(array $user, array $permissions): bool
{
    foreach ($permissions as $permission) {
        if (is_string($permission) && mg_admin_permission_user_has($user, $permission)) {
            return true;
        }
    }
    return false;
}

function mg_admin_page_permissions(string $pageKey): array
{
    $matrix = mg_admin_permission_matrix();
    return array_values($matrix['pages'][$pageKey] ?? []);
}

function mg_admin_user_can_view_page(array $user, string $pageKey): bool
{
    $permissions = mg_admin_page_permissions($pageKey);
    return $permissions === [] || mg_admin_permission_user_has_any($user, $permissions);
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
