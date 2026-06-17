<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'DELETE'], true)) {
    mg_fail('Method not allowed.', 405);
}

$user = mg_require_api_user();
$pdo = mg_db();

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT id, ip_address, user_agent, last_seen_at, expires_at, revoked_at, created_at,
                CASE WHEN session_hash = ? THEN 1 ELSE 0 END AS is_current
         FROM user_sessions
         WHERE user_id = ?
         ORDER BY last_seen_at DESC
         LIMIT 50'
    );
    $stmt->execute([mg_current_session_hash(), (int) $user['id']]);
    mg_ok(['sessions' => $stmt->fetchAll()], 'Sessions loaded.');
}

$input = mg_input();
mg_require_csrf_for_write($input);

$mode = (string) ($input['mode'] ?? 'all_except_current');

if ($mode === 'current') {
    mg_revoke_current_session((int) $user['id']);
    unset($_SESSION['mg_user']);
    session_regenerate_id(true);
    mg_audit('session_revoked_current', 'user_session', [], (int) $user['id']);
    mg_event('user.session.revoked_current', ['user_id' => (int) $user['id']], (int) $user['id']);
    mg_ok(['redirect' => '/signin.php'], 'Current session revoked.');
}

if ($mode === 'all') {
    mg_revoke_user_sessions((int) $user['id']);
    unset($_SESSION['mg_user']);
    session_regenerate_id(true);
    mg_audit('sessions_revoked_all', 'user_session', [], (int) $user['id']);
    mg_event('user.sessions.revoked_all', ['user_id' => (int) $user['id']], (int) $user['id']);
    mg_ok(['redirect' => '/signin.php'], 'All sessions revoked.');
}

$stmt = $pdo->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND session_hash <> ? AND revoked_at IS NULL');
$stmt->execute([(int) $user['id'], mg_current_session_hash()]);

mg_audit('sessions_revoked_other_devices', 'user_session', [], (int) $user['id']);
mg_event('user.sessions.revoked_other_devices', ['user_id' => (int) $user['id']], (int) $user['id']);

mg_ok(['revoked' => $stmt->rowCount()], 'Other sessions revoked.');
