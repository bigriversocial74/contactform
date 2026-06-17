<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'DELETE'], true)) {
    mg_fail('Method not allowed.', 405);
}

$user = mg_require_permission($method === 'GET' ? 'admin.sessions.view' : 'admin.sessions.revoke');
$pdo = mg_db();

if ($method === 'GET') {
    $limit = min(max((int) ($_GET['limit'] ?? 50), 1), 100);
    $targetUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

    if ($targetUserId > 0) {
        $stmt = $pdo->prepare(
            'SELECT s.id, s.user_id, u.email, u.display_name, s.ip_address, s.user_agent,
                    s.last_seen_at, s.expires_at, s.revoked_at, s.created_at
             FROM user_sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.user_id = ?
             ORDER BY s.last_seen_at DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$targetUserId]);
    } else {
        $stmt = $pdo->query(
            'SELECT s.id, s.user_id, u.email, u.display_name, s.ip_address, s.user_agent,
                    s.last_seen_at, s.expires_at, s.revoked_at, s.created_at
             FROM user_sessions s
             INNER JOIN users u ON u.id = s.user_id
             ORDER BY s.last_seen_at DESC
             LIMIT ' . $limit
        );
    }

    mg_ok(['sessions' => $stmt->fetchAll()], 'Sessions loaded.');
}

$input = mg_input();
mg_require_csrf_for_write($input);

$sessionId = isset($input['session_id']) ? (int) $input['session_id'] : 0;
$targetUserId = isset($input['user_id']) ? (int) $input['user_id'] : 0;

if ($sessionId > 0) {
    $stmt = $pdo->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL');
    $stmt->execute([$sessionId]);
    mg_audit('admin_session_revoked', 'user_session', ['session_id' => $sessionId], (int) $user['id']);
    mg_event('admin.session.revoked', ['session_id' => $sessionId, 'admin_user_id' => (int) $user['id']], (int) $user['id']);
    mg_ok(['revoked' => $stmt->rowCount()], 'Session revoked.');
}

if ($targetUserId > 0) {
    $stmt = $pdo->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL');
    $stmt->execute([$targetUserId]);
    mg_audit('admin_user_sessions_revoked', 'user_session', ['target_user_id' => $targetUserId], (int) $user['id']);
    mg_event('admin.user_sessions.revoked', ['target_user_id' => $targetUserId, 'admin_user_id' => (int) $user['id']], (int) $user['id']);
    mg_ok(['revoked' => $stmt->rowCount()], 'User sessions revoked.');
}

mg_fail('Provide session_id or user_id.', 422);
