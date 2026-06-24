<?php
/**
 * Refreshed authorization helpers for server-rendered admin pages.
 *
 * Admin APIs already refresh the authenticated user from the database before
 * enforcing permissions. These helpers bring the same fail-closed behavior to
 * PHP-rendered admin pages without applying API-only response headers/CSP.
 */
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/admin-permission-matrix.php';
require_once dirname(__DIR__) . '/api/db.php';
require_once dirname(__DIR__) . '/api/security.php';
require_once __DIR__ . '/user_models.php';

function mg_admin_page_public_user(array $user): array
{
    $userId = (int)($user['id'] ?? 0);

    return [
        'id' => $userId,
        'email' => (string)($user['email'] ?? ''),
        'full_name' => (string)($user['full_name'] ?? ''),
        'display_name' => (string)($user['display_name'] ?? $user['full_name'] ?? $user['email'] ?? ''),
        'status' => (string)($user['status'] ?? 'active'),
        'email_verified_at' => $user['email_verified_at'] ?? null,
        'roles' => array_values(array_unique(is_array($user['roles'] ?? null) ? $user['roles'] : [])),
        'permissions' => array_values(array_unique(is_array($user['permissions'] ?? null) ? $user['permissions'] : [])),
        'models' => function_exists('mg_user_active_model_codes') && $userId > 0
            ? array_values(array_unique(mg_user_active_model_codes($userId)))
            : [],
        'model_assignments' => function_exists('mg_user_model_assignments') && $userId > 0
            ? array_values(mg_user_model_assignments($userId))
            : [],
    ];
}

function mg_admin_page_load_user_auth(int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }

    $pdo = mg_db();
    $stmt = $pdo->prepare('SELECT id, email, full_name, display_name, status, email_verified_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return null;
    }

    $rolesStmt = $pdo->prepare(
        'SELECT r.slug
         FROM roles r
         INNER JOIN user_roles ur ON ur.role_id = r.id
         WHERE ur.user_id = ?
         ORDER BY r.slug'
    );
    $rolesStmt->execute([$userId]);
    $user['roles'] = array_column($rolesStmt->fetchAll(PDO::FETCH_ASSOC), 'slug');

    $permStmt = $pdo->prepare(
        'SELECT DISTINCT p.slug
         FROM permissions p
         INNER JOIN role_permissions rp ON rp.permission_id = p.id
         INNER JOIN user_roles ur ON ur.role_id = rp.role_id
         WHERE ur.user_id = ?
         ORDER BY p.slug'
    );
    $permStmt->execute([$userId]);
    $user['permissions'] = array_column($permStmt->fetchAll(PDO::FETCH_ASSOC), 'slug');

    return $user;
}

function mg_admin_page_refresh_session_user(): ?array
{
    $sessionUser = mg_current_user();
    if (!$sessionUser || empty($sessionUser['id'])) {
        return null;
    }

    $userId = (int)$sessionUser['id'];
    if (!mg_session_is_active($userId)) {
        mg_security_log('warning', 'admin.page.session_rejected', 'Admin page session failed DB-backed validation.', [], $userId);
        unset($_SESSION['mg_user']);
        return null;
    }

    $fresh = mg_admin_page_load_user_auth($userId);
    if (!$fresh) {
        mg_security_log('warning', 'admin.page.user_missing', 'Admin page session user no longer exists.', [], $userId);
        unset($_SESSION['mg_user']);
        return null;
    }

    $_SESSION['mg_user'] = mg_admin_page_public_user($fresh);
    return $_SESSION['mg_user'];
}

function mg_admin_page_user_has_permission(array $user, string $permission): bool
{
    return mg_admin_permission_user_has($user, $permission);
}

function mg_admin_page_forbidden(string $permission = 'admin'): never
{
    header('Cache-Control: no-store, private');
    http_response_code(403);
    exit('Forbidden');
}

function mg_require_admin_page_any(array $permissions): array
{
    $user = mg_admin_page_refresh_session_user();
    if (!$user) {
        mg_require_auth();
        $user = mg_admin_page_refresh_session_user();
    }

    if (!$user || ($user['status'] ?? '') !== 'active') {
        mg_security_log('warning', 'admin.page.inactive_account', 'Inactive or missing account attempted admin page access.', [
            'permissions' => $permissions,
        ], (int)($user['id'] ?? 0));
        mg_admin_page_forbidden($permissions[0] ?? 'admin');
    }

    foreach ($permissions as $permission) {
        if (is_string($permission) && $permission !== '' && mg_admin_permission_user_has($user, $permission)) {
            header('Cache-Control: no-store, private');
            header('Pragma: no-cache');
            return $user;
        }
    }

    mg_security_log('warning', 'admin.page.permission_denied', 'Admin page permission denied.', [
        'permissions' => $permissions,
    ], (int)$user['id']);
    mg_admin_page_forbidden($permissions[0] ?? 'admin');
}

function mg_require_admin_page_permission(string $permission): array
{
    return mg_require_admin_page_any([$permission]);
}

function mg_require_admin_page_key(string $pageKey): array
{
    $permissions = mg_admin_page_permissions($pageKey);
    if ($permissions === []) {
        mg_admin_page_forbidden($pageKey);
    }
    return mg_require_admin_page_any($permissions);
}
