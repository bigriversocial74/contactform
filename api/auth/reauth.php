<?php
declare(strict_types=1);
require_once __DIR__ . '/_identity_core.php';
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$password = (string) ($input['password'] ?? '');
$code = (string) ($input['code'] ?? '');
if ($password === '') mg_fail('Current password is required.', 422);
$ip = mg_client_ip() ?: 'unknown';
mg_rate_limit('auth.reauth.ip', $ip, 10, 900);
mg_rate_limit('auth.reauth.user', (string) $user['id'], 10, 900);
try {
    mg_identity_authenticate(mg_db(), (string) $user['email'], $password);
    if (!empty($user['mfa_enabled']) && !mg_mfa_verify_user(mg_db(), (int) $user['id'], $code, true)) {
        mg_fail('A valid MFA or recovery code is required.', 401);
    }
    $_SESSION['mg_step_up_at'] = time();
    if (!mg_hardened_rotate_authenticated_session((int) $user['id'], (int) ($user['auth_version'] ?? 1), $user['roles'] ?? [])) {
        throw new RuntimeException('Unable to rotate the authenticated session.');
    }
    mg_rate_limit_clear('auth.reauth.ip', $ip);
    mg_rate_limit_clear('auth.reauth.user', (string) $user['id']);
    mg_audit('auth.reauthenticated', 'user', [], (int) $user['id']);
    mg_ok(['valid_until' => gmdate('c', time() + max(60, (int) mg_config_value('security', 'step_up_max_age_seconds', 600)))], 'Identity confirmed.');
} catch (MgIdentityException $e) {
    mg_fail('Current password is incorrect.', 401);
} catch (Throwable $e) {
    mg_security_log('error', 'auth.reauth_failed', 'Reauthentication failed.', ['exception_class' => $e::class], (int) $user['id']);
    mg_fail('Unable to confirm identity.', 500);
}
