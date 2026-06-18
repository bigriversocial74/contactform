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
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $userId = (int) $user['id'];
        $expireOld = $pdo->prepare('UPDATE password_reset_tokens SET used_at = COALESCE(used_at, NOW()) WHERE user_id = ? AND used_at IS NULL');
        $expireOld->execute([$userId]);

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $minutes = max(10, (int) mg_config_value('security', 'reset_token_minutes', 60));
        $stmt = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ' . $minutes . ' MINUTE), NOW())');
        $stmt->execute([$userId, $tokenHash]);
        mg_audit('auth.password_reset_requested', 'user', [], $userId);
        mg_event('user.password_reset_requested', [], $userId);
        // Email delivery is configured later. Token storage is ready.
    }

    mg_ok([], 'If an account exists for that email, a reset link will be sent.');
} catch (Throwable $e) {
    mg_security_log('error', 'auth.password_forgot_error', 'Password recovery request failed.', ['exception' => $e->getMessage()]);
    mg_fail('Unable to process password reset right now.', 500);
}
