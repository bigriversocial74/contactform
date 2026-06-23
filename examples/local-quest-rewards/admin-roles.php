<?php
declare(strict_types=1);

function lqr_admin_role_map(): array
{
    return [
        'owner' => [
            'rank' => 100,
            'label' => 'Owner',
            'description' => 'Full control of installer unlocks, app settings, admin users, credentials, quests, users, wallets, claims, and reports.',
        ],
        'admin' => [
            'rank' => 80,
            'label' => 'Admin',
            'description' => 'Can manage quests, users, wallets, claims, and reports. Cannot manage owner-level credentials or installer unlocks.',
        ],
        'quest_manager' => [
            'rank' => 50,
            'label' => 'Quest Manager',
            'description' => 'Can create and edit quests and view quest performance.',
        ],
        'support' => [
            'rank' => 30,
            'label' => 'Support',
            'description' => 'Can view users, wallets, claims, and help with account-link support tasks.',
        ],
        'sponsor_viewer' => [
            'rank' => 10,
            'label' => 'Sponsor Viewer',
            'description' => 'Read-only sponsor reporting access.',
        ],
    ];
}

function lqr_admin_role_rank(string $roleKey): int
{
    $roles = lqr_admin_role_map();
    return (int)($roles[$roleKey]['rank'] ?? 0);
}

function lqr_admin_role_label(string $roleKey): string
{
    $roles = lqr_admin_role_map();
    return (string)($roles[$roleKey]['label'] ?? 'Unknown');
}

function lqr_admin_has_role(array $admin, string $requiredRole): bool
{
    return lqr_admin_role_rank((string)($admin['role_key'] ?? '')) >= lqr_admin_role_rank($requiredRole);
}

function lqr_admin_require_role(array $admin, string $requiredRole): void
{
    if (!lqr_admin_has_role($admin, $requiredRole)) {
        throw new RuntimeException(lqr_admin_role_label($requiredRole) . ' access required.');
    }
}

function lqr_admin_can_manage_admins(array $admin): bool
{
    return lqr_admin_has_role($admin, 'owner');
}

function lqr_admin_can_manage_settings(array $admin): bool
{
    return lqr_admin_has_role($admin, 'owner');
}

function lqr_admin_can_manage_quests(array $admin): bool
{
    return lqr_admin_has_role($admin, 'quest_manager');
}

function lqr_admin_can_support_users(array $admin): bool
{
    return lqr_admin_has_role($admin, 'support');
}

function lqr_admin_can_view_sponsor_reports(array $admin): bool
{
    return lqr_admin_has_role($admin, 'sponsor_viewer');
}

function lqr_admin_role_options(string $selected = 'admin'): string
{
    $html = '';
    foreach (lqr_admin_role_map() as $key => $role) {
        $isSelected = $key === $selected ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"' . $isSelected . '>' . htmlspecialchars((string)$role['label'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}
