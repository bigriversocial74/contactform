<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_recent_auth();
try {
    $setup = mg_mfa_begin_totp(mg_db(), (int) $user['id'], (string) ($input['label'] ?? 'Authenticator app'));
    $issuer = rawurlencode('Microgifter');
    $account = rawurlencode((string) $user['email']);
    $uri = 'otpauth://totp/' . $issuer . ':' . $account . '?secret=' . rawurlencode($setup['secret']) . '&issuer=' . $issuer . '&algorithm=SHA1&digits=6&period=30';
    mg_audit('auth.mfa_setup_started', 'user_mfa_method', ['method_id' => $setup['method_id']], (int) $user['id']);
    mg_ok(['method_id' => $setup['method_id'], 'secret' => $setup['secret'], 'otpauth_uri' => $uri], 'Authenticator setup started.');
} catch (Throwable $e) {
    mg_security_log('error', 'auth.mfa_setup_failed', 'MFA setup failed.', ['exception_class' => $e::class], (int) $user['id']);
    mg_fail('Unable to start MFA setup.', 500);
}
