<?php
/**
 * Permission helpers for server-rendered pages.
 * API endpoints must still enforce authorization before returning protected data.
 */

declare(strict_types=1);

function mg_user_roles(): array
{
    $user = mg_current_user();
    return is_array($user['roles'] ?? null) ? $user['roles'] : [];
}

function mg_user_permissions(): array
{
    $user = mg_current_user();
    return is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
}

function mg_has_role(string $role): bool
{
    return in_array($role, mg_user_roles(), true);
}

function mg_has_permission(string $permission): bool
{
    $user = mg_current_user();
    if (!$user) {
        return false;
    }

    if (mg_has_role('admin') || mg_has_role('super_admin')) {
        return true;
    }

    return in_array($permission, mg_user_permissions(), true);
}

function mg_can_access_inbox(): bool
{
    return mg_has_permission('agent.inbox.view') || mg_has_permission('messages.read');
}

function mg_can_manage_agent(): bool
{
    return mg_has_permission('agent.manage') || mg_has_permission('merchant.manage');
}
