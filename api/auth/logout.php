<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/security.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$user = mg_authenticated_user();
$userId = is_array($user) && !empty($user['id']) ? (int) $user['id'] : null;

if ($userId) {
    try {
        mg_hardened_revoke_current_session(mg_db(), $userId);
    } catch (Throwable $e) {
        mg_security_log('error', 'session.logout_revoke_failed', 'Logout could not mark the DB session revoked.', ['exception_class' => $e::class], $userId);
    }
    mg_audit('auth.logout', 'user', ['email' => $user['email'] ?? null], $userId);
    mg_event('user.logged_out', ['email' => $user['email'] ?? null], $userId);
}

mg_clear_session_identity(true);
mg_ok(['redirect' => '/index.php'], 'Signed out.');
