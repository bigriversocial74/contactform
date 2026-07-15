<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'DELETE'], true)) mg_fail('Method not allowed.', 405);

$user = mg_require_api_user();
$pdo = mg_db();
$userId = (int) $user['id'];

if ($method === 'GET') {
    if (mg_hardened_session_schema_ready()) {
        $stmt = $pdo->prepare(
            'SELECT id,authentication_method,ip_address,user_agent,last_seen_at,idle_expires_at,absolute_expires_at,last_rotated_at,expires_at,revoked_at,created_at,
                    CASE WHEN session_hash=? THEN 1 ELSE 0 END AS is_current
             FROM user_sessions WHERE user_id=? ORDER BY last_seen_at DESC LIMIT 50'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT id,ip_address,user_agent,last_seen_at,expires_at,revoked_at,created_at,
                    CASE WHEN session_hash=? THEN 1 ELSE 0 END AS is_current
             FROM user_sessions WHERE user_id=? ORDER BY last_seen_at DESC LIMIT 50'
        );
    }
    $stmt->execute([mg_current_session_hash(), $userId]);
    mg_ok(['sessions' => $stmt->fetchAll(PDO::FETCH_ASSOC)], 'Sessions loaded.');
}

$input = mg_input();
mg_require_csrf_for_write($input);
$mode = (string) ($input['mode'] ?? 'all_except_current');
if (!in_array($mode, ['current','all','all_except_current'], true)) mg_fail('Invalid session revocation mode.', 422);

if ($mode === 'current') {
    mg_hardened_revoke_current_session($pdo, $userId);
    mg_audit('session_revoked_current', 'user_session', [], $userId);
    mg_event('user.session.revoked_current', ['user_id' => $userId], $userId);
    mg_clear_session_identity(true);
    mg_ok(['redirect' => '/signin.php'], 'Current session revoked.');
}

if ($mode === 'all') {
    $revoked = mg_hardened_revoke_user_sessions($pdo, $userId, true);
    mg_audit('sessions_revoked_all', 'user_session', ['revoked' => $revoked], $userId);
    mg_event('user.sessions.revoked_all', ['user_id' => $userId, 'revoked' => $revoked], $userId);
    mg_clear_session_identity(true);
    mg_ok(['redirect' => '/signin.php', 'revoked' => $revoked], 'All sessions revoked.');
}

$stmt = $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND session_hash<>? AND revoked_at IS NULL');
$stmt->execute([$userId, mg_current_session_hash()]);
mg_audit('sessions_revoked_other_devices', 'user_session', ['revoked' => $stmt->rowCount()], $userId);
mg_event('user.sessions.revoked_other_devices', ['user_id' => $userId, 'revoked' => $stmt->rowCount()], $userId);
mg_ok(['revoked' => $stmt->rowCount()], 'Other sessions revoked.');
