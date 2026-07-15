<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/security.php';
require_once dirname(__DIR__) . '/_identity_core.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$token = trim((string) ($input['token'] ?? ''));
$password = (string) ($input['password'] ?? '');
$confirmation = (string) ($input['password_confirmation'] ?? '');
$ip = mg_client_ip() ?: 'unknown';

mg_rate_limit('auth.password_reset.ip', $ip, (int) mg_config_value('security', 'rate_limit_recovery_max', 5), (int) mg_config_value('security', 'rate_limit_recovery_window', 3600));

$errors = [];
if ($token === '') $errors['token'] = 'Reset token is required.';
try { mg_identity_validate_password($password); } catch (MgIdentityException $e) { $errors['password'] = $e->getMessage(); }
if ($password !== $confirmation) $errors['password_confirmation'] = 'Passwords do not match.';
if ($errors) {
    mg_security_log('warning', 'auth.password_reset.invalid_input', 'Invalid password reset input.', ['fields' => array_keys($errors)]);
    mg_fail('Please fix the highlighted fields.', 422, $errors);
}

try {
    $pdo = mg_db();
    $hash = hash('sha256', $token);
    mg_rate_limit('auth.password_reset.token', $hash, (int) mg_config_value('security', 'rate_limit_recovery_max', 5), (int) mg_config_value('security', 'rate_limit_recovery_window', 3600));

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT prt.id,prt.user_id,u.email,u.full_name,u.display_name FROM password_reset_tokens prt INNER JOIN users u ON u.id=prt.user_id WHERE prt.token_hash=? AND prt.used_at IS NULL AND prt.expires_at>NOW() ORDER BY prt.id DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new MgIdentityException('This reset link is invalid or expired.', 400);

        $userId = (int) $row['user_id'];
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($newHash) || $newHash === '') throw new RuntimeException('Unable to secure password.');
        if (mg_identity_schema_has_column('users', 'auth_version')) {
            $pdo->prepare('UPDATE users SET password_hash=?,password_changed_at=NOW(),auth_version=auth_version+1,updated_at=NOW() WHERE id=?')->execute([$newHash, $userId]);
        } elseif (mg_identity_schema_has_column('users', 'password_changed_at')) {
            $pdo->prepare('UPDATE users SET password_hash=?,password_changed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$newHash, $userId]);
        } else {
            $pdo->prepare('UPDATE users SET password_hash=?,updated_at=NOW() WHERE id=?')->execute([$newHash, $userId]);
        }
        $pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
        mg_hardened_revoke_user_sessions($pdo, $userId, true);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    mg_rate_limit_clear('auth.password_reset.token', $hash);
    $current = mg_current_user();
    if (is_array($current) && (int) ($current['id'] ?? 0) === $userId) mg_clear_session_identity(true);
    mg_audit('auth.password_reset_completed', 'user', [], $userId);
    mg_event('user.password_reset_completed', [], $userId);
    $name = trim((string) ($row['display_name'] ?? $row['full_name'] ?? ''));
    mg_send_template_email((string) $row['email'], 'security_alert', [
        'name' => $name !== '' ? $name : (string) $row['email'],
        'event' => 'Your password was reset and all existing sessions were revoked.',
    ], ['user_id' => $userId]);

    mg_ok(['redirect' => '/signin.php'], 'Password reset. You can sign in now.');
} catch (MgIdentityException $e) {
    mg_security_log('warning', 'auth.password_reset.invalid_token', 'Invalid or expired password reset token used.');
    mg_fail($e->getMessage(), $e->httpStatus);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'auth.password_reset_error', 'Password reset failed.', ['exception_class' => $e::class]);
    mg_fail('Unable to reset password right now.', 500);
}
