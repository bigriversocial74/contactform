<?php
/**
 * Object-level authorization helpers.
 *
 * These helpers are intentionally small and reusable. Every future record type
 * that belongs to a user, merchant, organization, team, store, product, gift,
 * order, inbox thread, or agent workspace should pass through an ownership or
 * scoped-permission check before reading or mutating the record.
 */
declare(strict_types=1);

function mg_user_can_access_owner(array $user, int $ownerUserId, string $permission = ''): bool
{
    $userId = (int) ($user['id'] ?? 0);
    if ($userId > 0 && $userId === $ownerUserId) {
        return true;
    }

    if ($permission === '') {
        return false;
    }

    if (function_exists('mg_api_user_has_permission')) {
        return mg_api_user_has_permission($user, $permission);
    }

    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (in_array('super_admin', $roles, true)) {
        return true;
    }

    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    return in_array($permission, $permissions, true);
}

function mg_require_owner_or_permission(array $user, int $ownerUserId, string $permission, string $message = 'Permission denied.'): array
{
    if (!mg_user_can_access_owner($user, $ownerUserId, $permission)) {
        if (function_exists('mg_audit')) {
            mg_audit('object_permission_denied', 'security', [
                'owner_user_id' => $ownerUserId,
                'permission' => $permission,
            ], (int) ($user['id'] ?? 0));
        }
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'object.permission_denied', 'Object-level permission denied.', [
                'owner_user_id' => $ownerUserId,
                'permission' => $permission,
            ], (int) ($user['id'] ?? 0));
        }
        mg_fail($message, 403);
    }

    return $user;
}
