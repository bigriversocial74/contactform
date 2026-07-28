<?php
declare(strict_types=1);

/**
 * Return whether an authenticated user should receive links into the
 * administrator workspace.
 *
 * Product/operational permissions such as merchant.payments.view,
 * microgift.operations.view, demand.dashboard.view, or tips.reverse are not
 * administrator identity by themselves. Keeping this gate narrow prevents
 * ordinary customer and merchant accounts from receiving an Admin link.
 */
function mg_admin_navigation_user_can_access(array $user): bool
{
    $roles = array_values(array_filter(
        is_array($user['roles'] ?? null) ? $user['roles'] : [],
        'is_string'
    ));

    if (in_array('admin', $roles, true) || in_array('super_admin', $roles, true)) {
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
