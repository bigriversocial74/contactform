<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/_user_management_common.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'DELETE'], true)) {
    mg_fail('Method not allowed.', 405);
}

$actor = mg_require_permission($method === 'GET' ? 'admin.sessions.view' : 'admin.sessions.revoke');
$actorId = (int)$actor['id'];
$pdo = mg_db();
mg_rate_limit('admin.sessions.' . strtolower($method), 'user:' . $actorId, $method === 'GET' ? 180 : 30, 300);

if ($method === 'GET') {
    try {
        $targetUserId = mg_admin_user_detail_id($_GET['user_id'] ?? null);
        $limit = max(1, min((int)($_GET['limit'] ?? 25), 50));
    } catch (InvalidArgumentException $error) {
        mg_fail($error->getMessage(), 422);
    }

    $stmt = $pdo->prepare(
        'SELECT s.id,s.user_id,s.ip_address,s.user_agent,s.last_seen_at,s.expires_at,s.revoked_at,s.created_at,
                (s.session_hash=?) AS is_current
         FROM user_sessions s
         WHERE s.user_id=?
         ORDER BY s.last_seen_at DESC,s.id DESC
         LIMIT ' . $limit
    );
    $stmt->execute([mg_current_session_hash(), $targetUserId]);
    mg_ok(['sessions' => $stmt->fetchAll(PDO::FETCH_ASSOC)], 'Sessions loaded.');
}

$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $sessionId = mg_admin_user_detail_id($input['session_id'] ?? null);
    $reason = mg_admin_management_reason($input['reason'] ?? '');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'SELECT s.id,s.user_id,s.session_hash,
                EXISTS(SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=s.user_id AND r.slug="super_admin") AS target_super_admin
         FROM user_sessions s WHERE s.id=? LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        throw new MgAdminUserManagementException('Session not found.', 404);
    }
    if (hash_equals((string)$session['session_hash'], mg_current_session_hash())) {
        throw new MgAdminUserManagementException('The current administrative session cannot be revoked here.');
    }
    if ((bool)$session['target_super_admin']) {
        throw new MgAdminUserManagementException('Owner sessions require the manual owner workflow.', 403);
    }

    $revoke = $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE id=? AND revoked_at IS NULL');
    $revoke->execute([$sessionId]);
    if ($revoke->rowCount() === 0) {
        throw new MgAdminUserManagementException('The session is already inactive.');
    }
    $pdo->commit();

    mg_audit('admin_session_revoked', 'user_session', [
        'session_id' => $sessionId,
        'target_user_id' => (int)$session['user_id'],
        'reason' => $reason,
    ], $actorId);
    mg_event('admin.session.revoked', [
        'session_id' => $sessionId,
        'target_user_id' => (int)$session['user_id'],
    ], $actorId);
    mg_ok(['revoked' => 1, 'session_id' => $sessionId], 'Session revoked.');
} catch (MgAdminUserManagementException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), $error->httpStatus);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'admin.session.revoke_failed', 'Administrative session revocation failed.', [
        'exception_class' => $error::class,
    ], $actorId);
    mg_fail('Unable to revoke the session.', 500);
}
