<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/security.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$sessionUser = mg_current_user();
$email = strtolower(trim((string) ($input['email'] ?? ($sessionUser['email'] ?? ''))));
$ip = mg_client_ip() ?: 'unknown';
mg_rate_limit('auth.email_resend.ip', $ip, 5, 3600);
if ($email !== '') mg_rate_limit('auth.email_resend.email', $email, 3, 3600);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    mg_fail('Enter a valid email address.', 422, ['email' => 'Invalid email.']);
}

try {
    $stmt = mg_db()->prepare('SELECT id,full_name,display_name,status,email_verified_at FROM users WHERE email=? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && (string) ($user['status'] ?? '') === 'active' && empty($user['email_verified_at'])) {
        $name = trim((string) ($user['display_name'] ?? $user['full_name'] ?? ''));
        $sent = mg_queue_verification_email((int) $user['id'], $email, $name);
        mg_audit('auth.email_verification_resent', 'user', ['delivery_sent' => $sent], (int) $user['id']);
        if (!$sent) mg_security_log('critical', 'auth.email_verification_delivery_pending', 'Verification email delivery failed.', [], (int) $user['id']);
    }
    mg_ok([], 'If the address belongs to an unverified account, a new verification link will be sent.');
} catch (Throwable $e) {
    mg_security_log('error', 'auth.email_resend_failed', 'Verification resend failed.', ['exception_class' => $e::class]);
    mg_fail('Unable to resend verification right now.', 500);
}
