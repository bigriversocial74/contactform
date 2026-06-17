<?php
/**
 * Microgifter API bootstrap.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';
require_once dirname(__DIR__) . '/includes/user_models.php';
require_once __DIR__ . '/security.php';

mg_apply_api_security_headers();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function mg_require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        mg_fail('Method not allowed.', 405);
    }
}

if (!function_exists('mg_input')) {
    function mg_input(): array
    {
        $raw = file_get_contents('php://input');
        $json = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (is_array($json)) {
            return $json;
        }
        return $_POST ?: [];
    }
}

function mg_require_csrf_for_write(array $input): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $token = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!mg_verify_csrf(is_string($token) ? $token : null)) {
            mg_security_log('warning', 'csrf.invalid', 'Invalid CSRF token.', ['method' => $method]);
            mg_fail('Invalid CSRF token.', 419);
        }
    }
}

function mg_public_user(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'email' => (string) $user['email'],
        'full_name' => (string) ($user['full_name'] ?? ''),
        'display_name' => (string) ($user['display_name'] ?? $user['full_name'] ?? $user['email']),
        'status' => (string) ($user['status'] ?? 'active'),
        'email_verified_at' => $user['email_verified_at'] ?? null,
        'roles' => array_values(array_unique($user['roles'] ?? [])),
        'permissions' => array_values(array_unique($user['permissions'] ?? [])),
        'models' => array_values(array_unique($user['models'] ?? [])),
        'model_assignments' => array_values($user['model_assignments'] ?? []),
    ];
}

function mg_load_user_auth(int $userId): ?array
{
    $pdo = mg_db();
    $stmt = $pdo->prepare('SELECT id, email, full_name, display_name, status, email_verified_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }

    $rolesStmt = $pdo->prepare('SELECT r.slug FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ? ORDER BY r.slug');
    $rolesStmt->execute([$userId]);
    $roles = array_column($rolesStmt->fetchAll(), 'slug');

    $permStmt = $pdo->prepare(
        'SELECT DISTINCT p.slug FROM permissions p
         INNER JOIN role_permissions rp ON rp.permission_id = p.id
         INNER JOIN user_roles ur ON ur.role_id = rp.role_id
         WHERE ur.user_id = ? ORDER BY p.slug'
    );
    $permStmt->execute([$userId]);
    $permissions = array_column($permStmt->fetchAll(), 'slug');

    $assignments = mg_user_model_assignments($userId);

    $user['roles'] = $roles;
    $user['permissions'] = $permissions;
    $user['model_assignments'] = $assignments;
    $user['models'] = mg_user_active_model_codes($userId);
    return $user;
}

function mg_set_session_user(array $user): void
{
    session_regenerate_id(true);
    mg_record_user_session((int) $user['id']);
    $_SESSION['mg_user'] = mg_public_user($user);
}

function mg_refresh_session_user(): ?array
{
    $sessionUser = mg_current_user();
    if (!$sessionUser || empty($sessionUser['id'])) {
        return null;
    }

    $userId = (int) $sessionUser['id'];
    if (!mg_session_is_active($userId)) {
        mg_security_log('warning', 'session.revoked_or_expired', 'Session rejected by DB-backed session validator.', [], $userId);
        unset($_SESSION['mg_user']);
        return null;
    }

    $fresh = mg_load_user_auth($userId);
    if (!$fresh) {
        unset($_SESSION['mg_user']);
        return null;
    }
    $_SESSION['mg_user'] = mg_public_user($fresh);
    return $_SESSION['mg_user'];
}

function mg_require_api_user(): array
{
    $user = mg_refresh_session_user();
    if (!$user) {
        mg_fail('Authentication required.', 401);
    }
    if (($user['status'] ?? '') !== 'active') {
        mg_security_log('warning', 'account.inactive_access', 'Inactive account attempted protected API access.', [], (int) $user['id']);
        mg_fail('Account is not active.', 403);
    }
    return $user;
}

function mg_api_user_has_permission(array $user, string $permission): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (in_array('super_admin', $roles, true)) {
        return true;
    }
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

function mg_assign_default_role(int $userId, string $roleSlug = 'customer'): void
{
    $pdo = mg_db();
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
    $stmt->execute([$roleSlug]);
    $role = $stmt->fetch();
    if (!$role) {
        return;
    }
    $assign = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())');
    $assign->execute([$userId, (int) $role['id']]);

    if ($roleSlug === 'customer') {
        try {
            mg_assign_default_customer_model($userId);
        } catch (Throwable $e) {
            mg_security_log('error', 'user_model.default_customer_failed', 'Default customer model assignment failed.', ['exception' => $e->getMessage()], $userId);
        }
    }
}

function mg_audit(string $action, string $entityType = 'system', array $metadata = [], ?int $userId = null): void
{
    try {
        $pdo = mg_db();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, metadata_json, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $userId ?? (mg_current_user()['id'] ?? null),
            $action,
            $entityType,
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            mg_client_ip(),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $e) {
        mg_security_log('error', 'audit.write_failed', 'Audit logging failed.', ['exception' => $e->getMessage()], $userId);
    }
}

function mg_event(string $eventType, array $payload = [], ?int $userId = null): void
{
    try {
        $pdo = mg_db();
        $stmt = $pdo->prepare(
            'INSERT INTO events (event_type, user_id, payload_json, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([
            $eventType,
            $userId ?? (mg_current_user()['id'] ?? null),
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        mg_security_log('error', 'event.write_failed', 'Event logging failed.', ['exception' => $e->getMessage()], $userId);
    }
}
