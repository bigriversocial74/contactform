<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_recent_auth();
$methodId = (int) ($input['method_id'] ?? 0);
$code = (string) ($input['code'] ?? '');
if ($methodId < 1 || trim($code) === '') mg_fail('Method and authenticator code are required.', 422);
try {
    $pdo = mg_db();
    $pdo->beginTransaction();
    try {
        $codes = mg_mfa_confirm_totp($pdo, (int) $user['id'], $methodId, $code);
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
    mg_set_session_user($fresh, 'mfa');
    mg_audit('auth.mfa_enabled', 'user_mfa_method', ['method_id' => $methodId], (int) $user['id']);
    mg_event('user.mfa.enabled', [], (int) $user['id']);
    mg_ok(['recovery_codes' => $codes, 'user' => mg_public_user($fresh)], 'MFA enabled. Save the recovery codes now; they will not be shown again.');
} catch (InvalidArgumentException $e) {
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    mg_security_log('error', 'auth.mfa_confirm_failed', 'MFA confirmation failed.', ['exception_class' => $e::class], (int) $user['id']);
    mg_fail('Unable to confirm MFA.', 500);
}
