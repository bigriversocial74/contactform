<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/security.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$token = trim((string) ($input['token'] ?? ''));
$password = (string) ($input['password'] ?? '');
$confirmation = (string) ($input['password_confirmation'] ?? '');
$ip = mg_client_ip() ?: 'unknown';

mg_rate_limit('auth.password_reset.ip', $ip, (int) mg_config_value('security', 'rate_limit_recovery_max', 5), (int) mg_config_value('security', 'rate_limit_recovery_window', 3600));

$errors = [];
if ($token === '') {
    $errors['token'] = 'Reset token is required.';
}
if (strlen($password) < 12) {
    $errors['password'] = 'Password must be at least 12 characters.';
}
if ($password !== $confirmation) {
    $errors['password_confirmation'] = 'Passwords do not match.';
}
if ($errors) {
    mg_security_log('warning', 'auth.password_reset.invalid_input', 'Invalid password reset input.', ['fields' => array_keys($errors)]);
    mg_fail('Please fix the highlighted fields.', 422, $errors);
}

try {
    $pdo = mg_db();
    $hash = hash('sha256', $token);
    mg_rate_limit('auth.password_reset.token', $hash, (int) mg_config_value('security', 'rate_limit_recovery_max', 5), (int) mg_config_value('security', 'rate_limit_recovery_window', 3600));

    $stmt = $pdo->prepare('SELECT id, user_id FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        mg_security_log('warning', 'auth.password_reset.invalid_token', 'Invalid or expired password reset token used.');
        mg_fail('This reset link is invalid or expired.', 400);
    }

    $userId = (int) $row['user_id'];
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    $stmt = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
    $stmt->execute([$userId]);
    $pdo->commit();

    mg_revoke_user_sessions($userId);
    mg_audit('auth.password_reset_completed', 'user', [], $userId);
    mg_event('user.password_reset_completed', [], $userId);

    mg_ok(['redirect' => '/signin.php'], 'Password reset. You can sign in now.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'auth.password_reset_error', 'Password reset failed.', ['exception' => $e->getMessage()]);
    mg_fail('Unable to reset password right now.', 500);
}
