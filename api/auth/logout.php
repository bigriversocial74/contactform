<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/security.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$user = mg_current_user();
$userId = is_array($user) && !empty($user['id']) ? (int) $user['id'] : null;

if ($userId) {
    mg_revoke_current_session($userId);
    mg_audit('auth.logout', 'user', ['email' => $user['email'] ?? null], $userId);
    mg_event('user.logged_out', ['email' => $user['email'] ?? null], $userId);
}

mg_destroy_session();

mg_ok(['redirect' => '/index.php'], 'Signed out.');
