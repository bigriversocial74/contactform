<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';

function mg_admin_sessions_id(mixed $value): int
{
    $raw = trim((string)$value);
    if ($raw === '' || preg_match('/^[1-9][0-9]{0,19}$/', $raw) !== 1) {
        throw new InvalidArgumentException('Invalid session identifier.');
    }

    $id = filter_var($raw, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new InvalidArgumentException('Invalid session identifier.');
    }

    return (int)$id;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'DELETE'], true)) {
    mg_fail('Method not allowed.', 405);
}

$pdo = mg_db();

if ($method === 'GET') {
    $user = mg_require_permission('admin.sessions.view');
    mg_rate_limit('admin.sessions.read', 'user:' . (int)$user['id'], 180, 60);

    try {
        $limit = min(max((int)($_GET['limit'] ?? 50), 1), 100);
        $targetUserId = isset($_GET['user_id']) && trim((string)$_GET['user_id']) !== ''
            ? mg_admin_user_detail_id($_GET['user_id'])
            : 0;

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

        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(['sessions' => $stmt->fetchAll()], 'Sessions loaded.');
    } catch (InvalidArgumentException $error) {
        mg_fail($error->getMessage(), 422);
    } catch (Throwable $error) {
        mg_security_log('error', 'admin.sessions.read_failed', 'Admin sessions query failed.', [
            'exception_class' => $error::class,
        ], (int)$user['id']);
        mg_fail('Unable to load sessions.', 500);
    }
}

$actor = mg_require_permission('admin.sessions.revoke');
$actorId = (int)$actor['id'];
mg_rate_limit('admin.sessions.revoke', 'user:' . $actorId, 30, 300);
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $reason = mg_admin_account_reason($input['reason'] ?? null);
    $sessionId = isset($input['session_id']) && trim((string)$input['session_id']) !== ''
        ? mg_admin_sessions_id($input['session_id'])
        : 0;
    $targetUserId = isset($input['user_id']) && trim((string)$input['user_id']) !== ''
        ? mg_admin_user_detail_id($input['user_id'])
        : 0;

    if ($sessionId < 1 && $targetUserId < 1) {
        mg_fail('Provide session_id or user_id.', 422);
    }

    $pdo->beginTransaction();
    if ($sessionId > 0) {
        $lookup = $pdo->prepare('SELECT user_id FROM user_sessions WHERE id = ? LIMIT 1');
        $lookup->execute([$sessionId]);
        $sessionOwner = $lookup->fetchColumn();
        if (!$sessionOwner) {
            throw new MgAdminAccountException('The session was not found or is already revoked.', 404);
        }
        $targetUserId = (int)$sessionOwner;
        $result = mg_admin_account_revoke_session($pdo, $actor, $targetUserId, $sessionId);
        $auditAction = 'admin_session_revoked';
        $eventType = 'admin.session.revoked';
    } else {
        $result = mg_admin_account_revoke_sessions($pdo, $actor, $targetUserId);
        $auditAction = 'admin_user_sessions_revoked';
        $eventType = 'admin.user_sessions.revoked';
    }
    $pdo->commit();

    $metadata = [
        'target_user_id' => $targetUserId,
        'session_id' => $sessionId > 0 ? $sessionId : null,
        'reason' => $reason,
        'result' => $result,
    ];
    mg_audit($auditAction, 'user_session', $metadata, $actorId);
    mg_event($eventType, $metadata + ['admin_user_id' => $actorId], $actorId);
    mg_security_log('info', 'admin.sessions.revoked', 'Admin session revocation completed.', [
        'target_user_id' => $targetUserId,
        'session_id' => $sessionId > 0 ? $sessionId : null,
    ], $actorId);

    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok($result, $sessionId > 0 ? 'Session revoked.' : 'User sessions revoked.');
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('warning', 'admin.sessions.revoke_rejected', 'Admin session revocation rejected.', [
        'reason' => $error->getMessage(),
    ], $actorId);
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'admin.sessions.revoke_failed', 'Admin session revocation failed.', [
        'exception_class' => $error::class,
    ], $actorId);
    mg_fail('Unable to revoke sessions.', 500);
}
