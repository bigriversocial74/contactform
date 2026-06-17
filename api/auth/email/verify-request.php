<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/security.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$user = mg_require_api_user();
$ip = mg_client_ip() ?: 'unknown';
mg_rate_limit('auth.email_verify_request.ip', $ip, 5, 3600);
mg_rate_limit('auth.email_verify_request.user', (string) $user['id'], 5, 3600);

try {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $minutes = max(60, (int) mg_config_value('security', 'verify_token_minutes', 1440));

    $expireOld = mg_db()->prepare('UPDATE email_verification_tokens SET used_at = COALESCE(used_at, NOW()) WHERE user_id = ? AND used_at IS NULL');
    $expireOld->execute([(int) $user['id']]);

    $stmt = mg_db()->prepare('INSERT INTO email_verification_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ' . $minutes . ' MINUTE), NOW())');
    $stmt->execute([(int) $user['id'], $hash]);

    mg_audit('auth.email_verification_requested', 'user', [], (int) $user['id']);
    mg_event('user.email_verification_requested', [], (int) $user['id']);
    mg_ok([], 'Verification request created. Email delivery will send the link when configured.');
} catch (Throwable $e) {
    mg_security_log('error', 'auth.email_verify_request_error', 'Email verification request failed.', ['exception' => $e->getMessage()], (int) $user['id']);
    mg_fail('Unable to create verification request right now.', 500);
}
