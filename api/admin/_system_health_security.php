<?php
declare(strict_types=1);

function mg_admin_system_health_is_super_admin(array $user): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    return in_array('super_admin', $roles, true);
}

function mg_admin_system_health_can_view_security_audit(array $user): bool
{
    return mg_admin_system_health_is_super_admin($user);
}

function mg_admin_system_health_require_security_auditor(array $user): void
{
    if (mg_admin_system_health_can_view_security_audit($user)) {
        return;
    }
    mg_security_log('warning', 'admin.security_hardening_audit.denied', 'Security hardening audit access denied.', [], (int)($user['id'] ?? 0));
    mg_fail('Permission denied.', 403);
}

function mg_admin_system_health_sensitive_token(int $userId): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $key = 'mg_admin_system_health_sensitive_token';
    $issuedKey = 'mg_admin_system_health_sensitive_token_issued_at';
    $userKey = 'mg_admin_system_health_sensitive_token_user_id';
    $expired = empty($_SESSION[$issuedKey]) || ((int)$_SESSION[$issuedKey] < (time() - 1800));
    $wrongUser = (int)($_SESSION[$userKey] ?? 0) !== $userId;
    if (empty($_SESSION[$key]) || $expired || $wrongUser) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
        $_SESSION[$issuedKey] = time();
        $_SESSION[$userKey] = $userId;
    }
    return (string)$_SESSION[$key];
}

function mg_admin_system_health_verify_sensitive_token(array $input): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $token = $input['sensitive_confirm_token'] ?? ($_SERVER['HTTP_X_MG_SENSITIVE_TOKEN'] ?? null);
    if (!is_string($token) || $token === '') {
        return false;
    }
    $stored = $_SESSION['mg_admin_system_health_sensitive_token'] ?? null;
    $issued = (int)($_SESSION['mg_admin_system_health_sensitive_token_issued_at'] ?? 0);
    return is_string($stored) && $stored !== '' && $issued >= (time() - 1800) && hash_equals($stored, $token);
}

function mg_admin_system_health_require_sensitive_action(array $user, array $input, string $action): void
{
    if (!mg_admin_system_health_is_super_admin($user)) {
        mg_security_log('warning', 'admin.system_health.sensitive_denied', 'Sensitive system health action denied.', ['action' => $action], (int)($user['id'] ?? 0));
        mg_fail('Permission denied.', 403);
    }
    if (!mg_admin_system_health_verify_sensitive_token($input)) {
        mg_security_log('warning', 'admin.system_health.sensitive_token_invalid', 'Sensitive system health action was missing a valid confirmation token.', ['action' => $action], (int)($user['id'] ?? 0));
        mg_fail('Security confirmation expired. Refresh the page and try again.', 419);
    }
}

function mg_admin_system_health_apply_admin_page_headers(): void
{
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((bool)mg_config_value('app', 'trust_proxy', false) && (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}
