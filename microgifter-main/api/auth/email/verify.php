<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/security.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$token = trim((string) ($input['token'] ?? ''));
$ip = mg_client_ip() ?: 'unknown';
mg_rate_limit('auth.email_verify.ip', $ip, 8, 3600);

if ($token === '') {
    mg_security_log('warning', 'auth.email_verify.missing_token', 'Email verification missing token.');
    mg_fail('Verification token is required.', 422, ['token' => 'Missing token.']);
}

try {
    $pdo = mg_db();
    $hash = hash('sha256', $token);
    mg_rate_limit('auth.email_verify.token', $hash, 8, 3600);

    $stmt = $pdo->prepare('SELECT id, user_id FROM email_verification_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        mg_security_log('warning', 'auth.email_verify.invalid_token', 'Invalid or expired email verification token used.');
        mg_fail('This verification link is invalid or expired.', 400);
    }

    $userId = (int) $row['user_id'];
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()), updated_at = NOW() WHERE id = ?');
    $stmt->execute([$userId]);
    $stmt = $pdo->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
    $stmt->execute([$userId]);
    $pdo->commit();

    mg_audit('auth.email_verified', 'user', [], $userId);
    mg_event('user.email_verified', [], $userId);

    mg_ok(['redirect' => '/account.php'], 'Email verified.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'auth.email_verify_error', 'Email verification failed.', ['exception' => $e->getMessage()]);
    mg_fail('Unable to verify email right now.', 500);
}
