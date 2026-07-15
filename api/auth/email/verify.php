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

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id,user_id FROM email_verification_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new InvalidArgumentException('This verification link is invalid or expired.');

        $userId = (int) $row['user_id'];
        $pdo->prepare('UPDATE users SET email_verified_at=COALESCE(email_verified_at,NOW()),updated_at=NOW() WHERE id=?')->execute([$userId]);
        $pdo->prepare('UPDATE email_verification_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    mg_rate_limit_clear('auth.email_verify.token', $hash);
    $sessionUser = mg_current_user();
    if (is_array($sessionUser) && (int) ($sessionUser['id'] ?? 0) === $userId) {
        mg_refresh_session_user(true);
    }
    $redirect = mg_safe_return_path((string) ($_SESSION['mg_post_verify_redirect'] ?? '/inbox.php'));
    unset($_SESSION['mg_post_verify_redirect']);
    mg_audit('auth.email_verified', 'user', [], $userId);
    mg_event('user.email_verified', [], $userId);
    mg_ok(['redirect' => $redirect], 'Email verified.');
} catch (InvalidArgumentException $e) {
    mg_security_log('warning', 'auth.email_verify.invalid_token', 'Invalid or expired email verification token used.');
    mg_fail($e->getMessage(), 400);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'auth.email_verify_error', 'Email verification failed.', ['exception_class' => $e::class]);
    mg_fail('Unable to verify email right now.', 500);
}
