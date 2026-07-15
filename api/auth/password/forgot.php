<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/security.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$email = strtolower(trim((string) ($input['email'] ?? '')));
$ip = mg_client_ip() ?: 'unknown';

mg_rate_limit('auth.password_forgot.ip', $ip, (int) mg_config_value('security', 'rate_limit_recovery_max', 5), (int) mg_config_value('security', 'rate_limit_recovery_window', 3600));
if ($email !== '') {
    mg_rate_limit('auth.password_forgot.email', $email, (int) mg_config_value('security', 'rate_limit_recovery_max', 5), (int) mg_config_value('security', 'rate_limit_recovery_window', 3600));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    mg_security_log('warning', 'auth.password_forgot.invalid_input', 'Invalid recovery email input.', ['email_present' => $email !== '']);
    mg_fail('Enter a valid email address.', 422, ['email' => 'Invalid email.']);
}

try {
    $pdo = mg_db();
    $stmt = $pdo->prepare('SELECT id,full_name,display_name,status FROM users WHERE email=? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && (string) ($user['status'] ?? '') === 'active') {
        $userId = (int) $user['id'];
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $minutes = max(10, (int) mg_config_value('security', 'reset_token_minutes', 60));
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE password_reset_tokens SET used_at=COALESCE(used_at,NOW()) WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
            $insert = $pdo->prepare('INSERT INTO password_reset_tokens (user_id,token_hash,expires_at,created_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL ' . $minutes . ' MINUTE),NOW())');
            $insert->execute([$userId, $tokenHash]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $name = trim((string) ($user['display_name'] ?? $user['full_name'] ?? ''));
        $sent = mg_send_password_reset_email($userId, $email, $name, $token);
        mg_audit('auth.password_reset_requested', 'user', ['delivery_sent' => $sent], $userId);
        mg_event('user.password_reset_requested', ['delivery_sent' => $sent], $userId);
        if (!$sent) {
            mg_security_log('critical', 'auth.password_reset_delivery_pending', 'Password reset token created but email delivery failed.', [], $userId);
        }
    }

    mg_ok([], 'If an account exists for that email, a reset link will be sent.');
} catch (Throwable $e) {
    mg_security_log('error', 'auth.password_forgot_error', 'Password recovery request failed.', ['exception_class' => $e::class]);
    mg_fail('Unable to process password reset right now.', 500);
}
