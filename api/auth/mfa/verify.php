<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$pending = mg_mfa_pending_login();
if (!$pending) mg_fail('MFA challenge expired. Sign in again.', 401, ['redirect' => '/signin.php']);
$code = (string) ($input['code'] ?? '');
if (trim($code) === '') mg_fail('Enter an authenticator or recovery code.', 422);
$ip = mg_client_ip() ?: 'unknown';
$userId = (int) $pending['user_id'];
mg_rate_limit('auth.mfa.ip', $ip, 8, 900);
mg_rate_limit('auth.mfa.user', (string) $userId, 8, 900);
try {
    $pdo = mg_db();
    if (!mg_mfa_verify_user($pdo, $userId, $code, true)) {
        mg_security_log('warning', 'auth.mfa_invalid', 'Invalid MFA challenge code.', [], $userId);
        mg_fail('Invalid authenticator or recovery code.', 401);
    }
    $user = mg_load_user_auth($userId);
    if (!$user || (string) ($user['status'] ?? '') !== 'active' || (int) ($user['auth_version'] ?? 1) !== (int) ($pending['auth_version'] ?? 1)) {
        unset($_SESSION['mg_mfa_pending']);
        mg_fail('Account state changed. Sign in again.', 401, ['redirect' => '/signin.php']);
    }
    $returnPath = mg_safe_return_path((string) ($pending['return_path'] ?? '/inbox.php'));
    unset($_SESSION['mg_mfa_pending']);
    mg_set_session_user($user, 'mfa');
    mg_rate_limit_clear('auth.mfa.ip', $ip);
    mg_rate_limit_clear('auth.mfa.user', (string) $userId);
    mg_audit('auth.login_mfa_completed', 'user', [], $userId);
    mg_event('user.logged_in_mfa', [], $userId);
    mg_ok(['user' => mg_public_user($user), 'redirect' => $returnPath], 'Signed in securely.');
} catch (Throwable $e) {
    mg_security_log('error', 'auth.mfa_verify_failed', 'MFA challenge failed.', ['exception_class' => $e::class], $userId);
    mg_fail('Unable to complete MFA challenge.', 500);
}
