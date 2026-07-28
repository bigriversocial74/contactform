<?php
declare(strict_types=1);

/**
 * Return whether the user has an explicit administrator role.
 *
 * Global account navigation must use this narrower identity check. A customer
 * or merchant may hold a delegated administrative permission without being an
 * administrator and must not receive the Admin dashboard link.
 */
function mg_admin_navigation_user_has_admin_role(array $user): bool
{
    $roles = array_values(array_filter(
        is_array($user['roles'] ?? null) ? $user['roles'] : [],
        'is_string'
    ));

    return in_array('admin', $roles, true) || in_array('super_admin', $roles, true);
}

/**
 * Return whether a user may enter the administrator workspace.
 *
 * Direct page authorization may honor explicit delegated permissions. This is
 * intentionally broader than global navigation visibility.
 */
function mg_admin_navigation_user_can_access(array $user): bool
{
    if (mg_admin_navigation_user_has_admin_role($user)) {
        return true;
    }

    $permissions = array_values(array_filter(
        is_array($user['permissions'] ?? null) ? $user['permissions'] : [],
        'is_string'
    ));

    foreach ($permissions as $permission) {
        if (str_starts_with($permission, 'admin.')) {
            return true;
        }
    }

    return count(array_intersect($permissions, [
        'security.logs.view',
        'subscriptions.admin',
        'share_market.admin',
        'social.moderate',
        'ops.alerts.assign',
        'ops.alerts.resolve',
    ])) > 0;
}
