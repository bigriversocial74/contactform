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
require_once dirname(__DIR__) . '/includes/identity-security.php';

if (!function_exists('mg_is_direct_api_request')) {
    function mg_is_direct_api_request(?string $scriptName = null): bool
    {
        $scriptName = str_replace('\\', '/', $scriptName ?? (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        return preg_match('#(?:^|/)api(?:/|$)#', $scriptName) === 1;
    }
}

if (mg_is_direct_api_request()) {
    mg_apply_api_security_headers();

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
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
