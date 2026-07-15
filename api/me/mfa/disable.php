<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/auth/_identity_core.php';
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$password = (string) ($input['password'] ?? '');
$code = (string) ($input['code'] ?? '');
if ($password === '' || $code === '') mg_fail('Current password and MFA code are required.', 422);
try {
    $pdo = mg_db();
    mg_identity_authenticate($pdo, (string) $user['email'], $password);
    if (!mg_mfa_verify_user($pdo, (int) $user['id'], $code, true)) mg_fail('Invalid MFA or recovery code.', 422);

    $pdo->beginTransaction();
    try {
        mg_mfa_disable_user($pdo, (int) $user['id']);
        $newVersion = mg_hardened_bump_auth_version($pdo, (int) $user['id']);
        mg_hardened_revoke_user_sessions($pdo, (int) $user['id'], true);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $fresh = mg_load_user_auth((int) $user['id']);
    if (!$fresh) throw new RuntimeException('Unable to refresh the secured account.');
    $fresh['auth_version'] = $newVersion;
    mg_set_session_user($fresh, 'password');
    mg_audit('auth.mfa_disabled', 'user_mfa_method', [], (int) $user['id']);
    mg_event('user.mfa.disabled', [], (int) $user['id']);
    mg_ok(['user' => mg_public_user($fresh)], 'MFA disabled and other sessions were revoked.');
} catch (MgIdentityException $e) {
    mg_fail('Current password is incorrect.', 401);
} catch (Throwable $e) {
    mg_security_log('error', 'auth.mfa_disable_failed', 'MFA disable failed.', ['exception_class' => $e::class], (int) $user['id']);
    mg_fail('Unable to disable MFA.', 500);
}
