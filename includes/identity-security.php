<?php
/**
 * Canonical identity and authenticated-session layer shared by pages and APIs.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/db.php';
require_once dirname(__DIR__) . '/api/security.php';
require_once __DIR__ . '/user_models.php';
require_once __DIR__ . '/session-security.php';

function mg_identity_schema_has_table(string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    try {
        $stmt = mg_db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        return $cache[$table] = ((int) $stmt->fetchColumn() > 0);
    } catch (Throwable) {
        return $cache[$table] = false;
    }
}

function mg_identity_schema_has_column(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = mg_db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table, $column]);
        return $cache[$key] = ((int) $stmt->fetchColumn() > 0);
    } catch (Throwable) {
        return $cache[$key] = false;
    }
}


function mg_email_verification_gate_enabled(): bool
{
    return (bool) mg_config_value('security', 'email_verification_required', true)
        && mg_identity_schema_has_column('users', 'auth_version');
}

function mg_public_user(array $user): array
{
    $verifiedAt = $user['email_verified_at'] ?? null;
    return [
        'id' => (int) $user['id'],
        'email' => (string) $user['email'],
        'full_name' => (string) ($user['full_name'] ?? ''),
        'display_name' => (string) ($user['display_name'] ?? $user['full_name'] ?? $user['email']),
        'status' => (string) ($user['status'] ?? 'active'),
        'email_verified_at' => $verifiedAt,
        'email_verified' => !empty($verifiedAt),
        'auth_version' => max(1, (int) ($user['auth_version'] ?? 1)),
        'mfa_enabled' => (bool) ($user['mfa_enabled'] ?? false),
        'roles' => array_values(array_unique($user['roles'] ?? [])),
        'permissions' => array_values(array_unique($user['permissions'] ?? [])),
        'models' => array_values(array_unique($user['models'] ?? [])),
        'model_assignments' => array_values($user['model_assignments'] ?? []),
    ];
}

function mg_load_user_auth(int $userId): ?array
{
    $columns = 'id,email,full_name,display_name,status,email_verified_at';
    if (mg_identity_schema_has_column('users', 'auth_version')) $columns .= ',auth_version';
    $stmt = mg_db()->prepare("SELECT {$columns} FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return null;
    if (!isset($user['auth_version'])) $user['auth_version'] = 1;

    $rolesStmt = mg_db()->prepare('SELECT r.slug FROM roles r INNER JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=? ORDER BY r.slug');
    $rolesStmt->execute([$userId]);
    $user['roles'] = array_column($rolesStmt->fetchAll(PDO::FETCH_ASSOC), 'slug');

    $permStmt = mg_db()->prepare(
        'SELECT DISTINCT p.slug FROM permissions p
         INNER JOIN role_permissions rp ON rp.permission_id=p.id
         INNER JOIN user_roles ur ON ur.role_id=rp.role_id
         WHERE ur.user_id=? ORDER BY p.slug'
    );
    $permStmt->execute([$userId]);
    $user['permissions'] = array_column($permStmt->fetchAll(PDO::FETCH_ASSOC), 'slug');
    $user['model_assignments'] = mg_user_model_assignments($userId);
    $user['models'] = mg_user_active_model_codes($userId);
    $user['mfa_enabled'] = function_exists('mg_mfa_user_enabled') ? mg_mfa_user_enabled(mg_db(), $userId) : false;
    return $user;
}

function mg_identity_reset_request_cache(): void
{
    $GLOBALS['mg_identity_request_cache'] = ['resolved' => false, 'user' => null];
}

function mg_clear_session_identity(bool $destroy = false): void
{
    unset(
        $_SESSION['mg_user'],
        $_SESSION['mg_auth_started_at'],
        $_SESSION['mg_auth_last_activity_at'],
        $_SESSION['mg_auth_rotated_at'],
        $_SESSION['mg_auth_version'],
        $_SESSION['mg_step_up_at'],
        $_SESSION['mg_mfa_pending']
    );
    mg_identity_reset_request_cache();
    if (function_exists('mg_rotate_csrf_token')) mg_rotate_csrf_token();
    if ($destroy && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (function_exists('mg_expire_session_cookie')) mg_expire_session_cookie();
        session_destroy();
    }
}

function mg_set_session_user(array $user, string $authenticationMethod = 'password'): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!headers_sent()) session_regenerate_id(true);
    if (function_exists('mg_rotate_csrf_token')) mg_rotate_csrf_token();
    $public = mg_public_user($user);
    $now = time();
    $_SESSION['mg_auth_started_at'] = $now;
    $_SESSION['mg_auth_last_activity_at'] = $now;
    $_SESSION['mg_auth_rotated_at'] = $now;
    $_SESSION['mg_auth_version'] = $public['auth_version'];
    $_SESSION['mg_step_up_at'] = $now;
    mg_hardened_record_user_session((int) $public['id'], (int) $public['auth_version'], $public['roles'], $authenticationMethod);
    $_SESSION['mg_user'] = $public;
    mg_identity_reset_request_cache();
}

function mg_refresh_session_user(bool $forceRefresh = false): ?array
{
    $cache = $GLOBALS['mg_identity_request_cache'] ?? ['resolved' => false, 'user' => null];
    if (!$forceRefresh && !empty($cache['resolved'])) return is_array($cache['user']) ? $cache['user'] : null;

    $sessionUser = mg_current_user();
    if (!$sessionUser || empty($sessionUser['id'])) {
        $GLOBALS['mg_identity_request_cache'] = ['resolved' => true, 'user' => null];
        return null;
    }

    $userId = (int) $sessionUser['id'];
    $authVersion = max(1, (int) ($sessionUser['auth_version'] ?? $_SESSION['mg_auth_version'] ?? 1));
    $roles = is_array($sessionUser['roles'] ?? null) ? $sessionUser['roles'] : [];
    $validation = mg_hardened_session_validation_result($userId, $authVersion, $roles);
    if (!$validation['active']) {
        mg_security_log('warning', 'session.rejected', 'Session rejected by DB-backed validator.', ['reason' => $validation['reason']], $userId);
        mg_clear_session_identity(false);
        $GLOBALS['mg_identity_request_cache'] = ['resolved' => true, 'user' => null];
        return null;
    }

    $fresh = mg_load_user_auth($userId);
    if (!$fresh || (string) ($fresh['status'] ?? '') !== 'active') {
        mg_security_log('warning', 'account.inactive_or_missing', 'Session user is missing or inactive.', [], $userId);
        mg_clear_session_identity(false);
        $GLOBALS['mg_identity_request_cache'] = ['resolved' => true, 'user' => null];
        return null;
    }
    if ((int) ($fresh['auth_version'] ?? 1) !== $authVersion) {
        mg_security_log('warning', 'session.auth_version_mismatch', 'Session auth version no longer matches account.', [], $userId);
        mg_clear_session_identity(false);
        $GLOBALS['mg_identity_request_cache'] = ['resolved' => true, 'user' => null];
        return null;
    }

    $rotationMinutes = max(5, (int) mg_config_value('security', 'session_rotation_minutes', 15));
    $rotatedAt = (int) ($_SESSION['mg_auth_rotated_at'] ?? 0);
    if ($rotatedAt < time() - ($rotationMinutes * 60)) {
        if (!mg_hardened_rotate_authenticated_session($userId, $authVersion, $fresh['roles'] ?? [])) {
            mg_clear_session_identity(false);
            $GLOBALS['mg_identity_request_cache'] = ['resolved' => true, 'user' => null];
            return null;
        }
        $_SESSION['mg_auth_rotated_at'] = time();
    }

    $_SESSION['mg_auth_last_activity_at'] = time();
    $_SESSION['mg_user'] = mg_public_user($fresh);
    $GLOBALS['mg_identity_request_cache'] = ['resolved' => true, 'user' => $_SESSION['mg_user']];
    return $_SESSION['mg_user'];
}

function mg_require_api_user(): array
{
    $user = mg_refresh_session_user();
    if (!$user) mg_fail('Authentication required.', 401);
    if (($user['status'] ?? '') !== 'active') {
        mg_security_log('warning', 'account.inactive_access', 'Inactive account attempted protected API access.', [], (int) $user['id']);
        mg_fail('Account is not active.', 403);
    }
    if (mg_email_verification_gate_enabled() && empty($user['email_verified_at'])) {
        mg_security_log('warning', 'account.email_verification_required', 'Unverified account attempted protected API access.', [], (int) $user['id']);
        mg_fail('Verify your email to continue.', 403, ['verification_required' => true, 'redirect' => '/verify-email.php?pending=1']);
    }
    return $user;
}

function mg_require_verified_api_user(): array
{
    $user = mg_require_api_user();
    if (empty($user['email_verified_at'])) {
        mg_security_log('warning', 'account.unverified_sensitive_access', 'Unverified account attempted a verification-gated action.', [], (int) $user['id']);
        mg_fail('Verify your email before completing this action.', 403, ['verification_required' => true]);
    }
    return $user;
}

function mg_api_user_has_permission(array $user, string $permission): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (in_array('super_admin', $roles, true)) return true;
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    return in_array($permission, $permissions, true);
}

function mg_require_permission(string $permission): array
{
    $user = mg_require_api_user();
    if (!mg_api_user_has_permission($user, $permission)) {
        mg_audit('permission_denied', 'security', ['permission' => $permission], (int) $user['id']);
        mg_security_log('warning', 'permission.denied', 'Permission denied.', ['permission' => $permission], (int) $user['id']);
        mg_fail('Permission denied.', 403);
    }
    return $user;
}

function mg_require_active_model(string $modelCode): array
{
    $user = mg_require_api_user();
    $models = is_array($user['models'] ?? null) ? $user['models'] : [];
    if (!in_array($modelCode, $models, true)) {
        mg_security_log('warning', 'user_model.required_missing', 'Required user model is not active.', ['model' => $modelCode], (int) $user['id']);
        mg_fail('Required user model is not active.', 403);
    }
    return $user;
}

function mg_recent_auth_is_valid(?int $maxAgeSeconds = null): bool
{
    $maxAge = $maxAgeSeconds ?? max(60, (int) mg_config_value('security', 'step_up_max_age_seconds', 600));
    return !empty($_SESSION['mg_step_up_at']) && (int) $_SESSION['mg_step_up_at'] >= time() - $maxAge;
}

function mg_require_recent_auth(?int $maxAgeSeconds = null): array
{
    $user = mg_require_api_user();
    if (!mg_recent_auth_is_valid($maxAgeSeconds)) {
        mg_fail('Reauthentication is required for this action.', 403, ['reauthentication_required' => true]);
    }
    return $user;
}

function mg_begin_mfa_login_challenge(array $user, string $returnPath = '/inbox.php'): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!headers_sent()) session_regenerate_id(true);
    mg_rotate_csrf_token();
    $_SESSION['mg_mfa_pending'] = [
        'user_id' => (int) $user['id'],
        'auth_version' => max(1, (int) ($user['auth_version'] ?? 1)),
        'return_path' => mg_safe_return_path($returnPath),
        'expires_at' => time() + max(120, (int) mg_config_value('security', 'mfa_challenge_seconds', 300)),
    ];
    unset($_SESSION['mg_user']);
    mg_identity_reset_request_cache();
}

function mg_mfa_pending_login(): ?array
{
    $pending = $_SESSION['mg_mfa_pending'] ?? null;
    if (!is_array($pending) || empty($pending['user_id']) || (int) ($pending['expires_at'] ?? 0) < time()) {
        unset($_SESSION['mg_mfa_pending']);
        return null;
    }
    return $pending;
}
