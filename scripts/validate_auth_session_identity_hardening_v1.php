<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'app core' => 'includes/app-core.php',
    'page auth' => 'includes/auth.php',
    'permissions' => 'includes/permissions.php',
    'csrf' => 'includes/csrf.php',
    'identity' => 'includes/identity-security.php',
    'sessions' => 'includes/session-security.php',
    'mfa' => 'includes/mfa.php',
    'mail' => 'includes/mail.php',
    'api bootstrap' => 'api/bootstrap.php',
    'config' => 'api/config.php',
    'identity core' => 'api/auth/_identity_core.php',
    'login' => 'api/auth/login.php',
    'register' => 'api/auth/register.php',
    'forgot' => 'api/auth/password/forgot.php',
    'reset' => 'api/auth/password/reset.php',
    'verify' => 'api/auth/email/verify.php',
    'resend' => 'api/auth/email/resend.php',
    'mfa verify' => 'api/auth/mfa/verify.php',
    'reauth' => 'api/auth/reauth.php',
    'session api' => 'api/me/sessions.php',
    'mfa status' => 'api/me/mfa/status.php',
    'mfa setup' => 'api/me/mfa/setup.php',
    'mfa confirm' => 'api/me/mfa/confirm.php',
    'mfa disable' => 'api/me/mfa/disable.php',
    'auth js' => 'assets/js/auth.js',
    'migration' => 'database/auth_session_identity_hardening_v1.sql',
    'challenge page' => 'mfa-challenge.php',
    'verify page' => 'verify-email.php',
];

$contents = [];
foreach ($required as $label => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$relative}\n");
        exit(1);
    }
    $contents[$relative] = (string) file_get_contents($path);
}

$has = static fn(string $file, string $needle): bool => str_contains($contents[$file] ?? '', $needle);
$checks = [
    'strict PHP session mode' => $has('includes/app-core.php', "session.use_strict_mode','1"),
    'cookies-only sessions' => $has('includes/app-core.php', "session.use_only_cookies','1"),
    'session trans sid disabled' => $has('includes/app-core.php', "session.use_trans_sid','0"),
    'page security headers' => $has('includes/app-core.php', 'mg_apply_page_security_headers'),
    'page auth uses canonical identity' => $has('includes/auth.php', 'mg_authenticated_user()'),
    'page verification redirect' => $has('includes/auth.php', '/verify-email.php?pending=1'),
    'only super admin bypasses permissions' => $has('includes/permissions.php', "mg_has_role('super_admin')") && !$has('includes/permissions.php', "mg_has_role('admin') ||"),
    'csrf rotates after identity changes' => $has('includes/csrf.php', 'mg_rotate_csrf_token'),
    'auth version is in public identity' => $has('includes/identity-security.php', "'auth_version'"),
    'page and API session refresh' => $has('includes/identity-security.php', 'mg_refresh_session_user'),
    'verified API gate' => $has('includes/identity-security.php', 'mg_email_verification_gate_enabled'),
    'idle and absolute policy' => $has('includes/session-security.php', 'idle_expires_at') && $has('includes/session-security.php', 'absolute_expires_at'),
    'periodic session rotation' => $has('includes/session-security.php', 'mg_hardened_rotate_authenticated_session'),
    'strict session revocation' => $has('includes/session-security.php', 'mg_hardened_revoke_user_sessions'),
    'password timing equalization' => $has('api/auth/_identity_core.php', 'mg_identity_dummy_password_hash'),
    'password hash upgrade' => $has('api/auth/_identity_core.php', 'password_needs_rehash'),
    'atomic registration transaction' => $has('api/auth/register.php', '$pdo->beginTransaction()') && $has('api/auth/register.php', 'merchant_team_members'),
    'recovery email delivery' => $has('api/auth/password/forgot.php', 'mg_send_password_reset_email'),
    'atomic password reset and revocation' => $has('api/auth/password/reset.php', 'FOR UPDATE') && $has('api/auth/password/reset.php', 'mg_hardened_revoke_user_sessions'),
    'email verification token lock' => $has('api/auth/email/verify.php', 'FOR UPDATE'),
    'verification resend is generic' => $has('api/auth/email/resend.php', 'If the address belongs to an unverified account'),
    'MFA encrypted at rest' => $has('includes/mfa.php', 'aes-256-gcm'),
    'MFA replay prevention' => $has('includes/mfa.php', 'last_counter'),
    'MFA recovery codes are hashed' => $has('includes/mfa.php', 'mg_mfa_recovery_hash'),
    'MFA enrollment revokes old sessions' => $has('api/me/mfa/confirm.php', 'mg_hardened_revoke_user_sessions'),
    'MFA disable revokes old sessions' => $has('api/me/mfa/disable.php', 'mg_hardened_revoke_user_sessions'),
    'step up rotates session' => $has('api/auth/reauth.php', 'mg_hardened_rotate_authenticated_session'),
    'server response controls redirect' => strpos($contents['assets/js/auth.js'], 'data.data.redirect') < strpos($contents['assets/js/auth.js'], 'data-success-redirect'),
    'migration has auth version' => $has('database/auth_session_identity_hardening_v1.sql', "'users','auth_version'"),
    'migration has session expiry columns' => $has('database/auth_session_identity_hardening_v1.sql', "'idle_expires_at'") && $has('database/auth_session_identity_hardening_v1.sql', "'absolute_expires_at'"),
    'migration has MFA tables' => $has('database/auth_session_identity_hardening_v1.sql', 'CREATE TABLE IF NOT EXISTS user_mfa_methods') && $has('database/auth_session_identity_hardening_v1.sql', 'CREATE TABLE IF NOT EXISTS user_mfa_recovery_codes'),
    'migration preserves legacy users' => $has('database/auth_session_identity_hardening_v1.sql', 'Existing active accounts predate'),
    'migration has no check constraints' => stripos($contents['database/auth_session_identity_hardening_v1.sql'], 'CHECK (') === false,
];

require_once $root . '/includes/mfa.php';
$knownSecret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
$checks['base32 round trip'] = mg_mfa_base32_encode(mg_mfa_base32_decode($knownSecret)) === $knownSecret;
$checks['RFC TOTP vector'] = mg_mfa_totp_code($knownSecret, 1) === '287082';

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, "Auth/session/identity hardening validation failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "Auth, Session and Identity Production Hardening v1 validation passed (" . count($checks) . " checks).\n";
