<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once __DIR__ . '/_queue_alerts.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_queue_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission) || mg_admin_account_actor_has($actor, 'admin.user_notes.manage') || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_queue_require(array $actor, string $permission): void
{
    if (!mg_admin_queue_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission' => $permission, 'area' => 'admin_support_queue'], (int)$actor['id']);
        mg_security_log('warning', 'admin.support_queue.denied', 'Admin support queue permission denied.', ['permission' => $permission], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

function mg_admin_queue_value(mixed $value, array $allowed, ?string $fallback = null): ?string
{
    $text = strtolower(trim((string)$value));
    if ($text === '' && $fallback === null) {
        return null;
    }
    return in_array($text, $allowed, true) ? $text : $fallback;
}

function mg_admin_queue_note_id(mixed $value): string
{
    $id = trim((string)$value);
    if (preg_match('/^[a-f0-9-]{20,60}$/i', $id) !== 1) {
        throw new MgAdminAccountException('Invalid queue note identifier.', 422);
    }
    return $id;
}

function mg_admin_queue_due(mixed $value): ?string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
        return $raw . ' 17:00:00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $raw) === 1) {
        return str_replace('T', ' ', strlen($raw) === 16 ? $raw . ':00' : $raw);
    }
    throw new MgAdminAccountException('Invalid follow-up due date.', 422);
}

function mg_admin_queue_assign_id(PDO $pdo, mixed $value): ?int
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^[1-9][0-9]{0,19}$/', $raw) !== 1) {
        throw new MgAdminAccountException('Invalid assignee.', 422);
    }
    $id = (int)$raw;
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        throw new MgAdminAccountException('Assigned admin user was not found.', 404);
    }
    return $id;
}

function mg_admin_queue_list(PDO $pdo, array $filters): array
{
    $where = ['1=1'];
    $params = [];
    $status = mg_admin_queue_value($filters['status'] ?? null, ['open','waiting_on_merchant','waiting_on_customer','resolved','escalated']);
    $priority = mg_admin_queue_value($filters['priority'] ?? null, ['low','normal','high','critical']);
    $category = mg_admin_queue_value($filters['category'] ?? null, ['support','risk','billing','merchant_onboarding','product_catalog','crm_campaigns','general']);
    $flag = mg_admin_queue_value($filters['flag_state'] ?? null, ['none','flagged','cleared','review']);
    $assigned = strtolower(trim((string)($filters['assigned'] ?? '')));
    $due = strtolower(trim((string)($filters['due'] ?? '')));
    $q = trim((string)($filters['q'] ?? ''));

    if ($status !== null) { $where[] = 'n.status = ?'; $params[] = $status; }
    if ($priority !== null) { $where[] = 'n.priority = ?'; $params[] = $priority; }
    if ($category !== null) { $where[] = 'n.category = ?'; $params[] = $category; }
    if ($flag !== null) { $where[] = 'n.flag_state = ?'; $params[] = $flag; }
    if ($assigned === 'me') { $where[] = 'n.assigned_admin_user_id = ?'; $params[] = (int)$filters['actor_id']; }
    if ($assigned === 'unassigned') { $where[] = 'n.assigned_admin_user_id IS NULL'; }
    if ($assigned === 'assigned') { $where[] = 'n.assigned_admin_user_id IS NOT NULL'; }
    if ($due === 'overdue') { $where[] = 'n.due_at IS NOT NULL AND n.due_at < NOW() AND n.status <> "resolved"'; }
    if ($due === 'today') { $where[] = 'n.due_at >= CURDATE() AND n.due_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)'; }
    if ($due === 'week') { $where[] = 'n.due_at >= CURDATE() AND n.due_at < DATE_ADD(CURDATE(), INTERVAL 7 DAY)'; }
    if ($q !== '') {
        $where[] = '(n.note LIKE ? OR n.reason LIKE ? OR target.email LIKE ? OR target.display_name LIKE ? OR target.full_name LIKE ?)';
        $needle = '%' . $q . '%';
        array_push($params, $needle, $needle, $needle, $needle, $needle);
    }

    $limit = max(10, min(100, (int)($filters['limit'] ?? 50)));
    $sql = 'SELECT n.public_id, n.category, n.priority, n.status, n.flag_state, n.note, n.reason,
                   n.due_at, n.resolved_at, n.closed_at, n.created_at, n.updated_at,
                   target.id AS target_id, target.email AS target_email, target.display_name AS target_display_name, target.full_name AS target_full_name,
                   admin.id AS admin_id, admin.email AS admin_email, admin.display_name AS admin_display_name,
                   assigned.id AS assigned_id, assigned.email AS assigned_email, assigned.display_name AS assigned_display_name
            FROM admin_user_notes n
            INNER JOIN users target ON target.id = n.target_user_id
            INNER JOIN users admin ON admin.id = n.admin_user_id
            LEFT JOIN users assigned ON assigned.id = n.assigned_admin_user_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY
              CASE n.priority WHEN "critical" THEN 1 WHEN "high" THEN 2 WHEN "normal" THEN 3 ELSE 4 END,
              CASE WHEN n.due_at IS NULL THEN 1 ELSE 0 END,
              n.due_at ASC,
              n.updated_at DESC
            LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'category' => (string)$row['category'],
        'priority' => (string)$row['priority'],
        'status' => (string)$row['status'],
        'flag_state' => (string)$row['flag_state'],
        'note' => (string)$row['note'],
        'reason' => (string)$row['reason'],
        'due_at' => $row['due_at'] !== null ? (string)$row['due_at'] : null,
        'resolved_at' => $row['resolved_at'] !== null ? (string)$row['resolved_at'] : null,
        'closed_at' => $row['closed_at'] !== null ? (string)$row['closed_at'] : null,
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
        'target' => [
            'id' => (int)$row['target_id'],
            'email' => (string)$row['target_email'],
            'display_name' => (string)($row['target_display_name'] ?: $row['target_full_name'] ?: $row['target_email']),
        ],
        'created_by' => [
            'id' => (int)$row['admin_id'],
            'email' => (string)$row['admin_email'],
            'display_name' => (string)($row['admin_display_name'] ?: $row['admin_email']),
        ],
        'assigned_to' => $row['assigned_id'] !== null ? [
            'id' => (int)$row['assigned_id'],
            'email' => (string)$row['assigned_email'],
            'display_name' => (string)($row['assigned_display_name'] ?: $row['assigned_email']),
        ] : null,
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));

    $alerts = mg_queue_alert_summary($pdo, (int)($filters['actor_id'] ?? 0));
    return [
        'items' => $items,
        'summary' => [
            'total' => $alerts['total'],
            'active_total' => $alerts['active_total'],
            'escalated_total' => $alerts['escalated_total'],
            'review_total' => $alerts['review_total'],
            'overdue_total' => $alerts['overdue_total'],
            'due_today_total' => $alerts['due_today_total'],
            'due_soon_total' => $alerts['due_soon_total'],
            'assigned_to_me_total' => $alerts['assigned_to_me_total'],
            'header_badge_total' => $alerts['header_badge_total'],
            'score' => ['section' => 'Support queue', 'score' => 10, 'max' => 10, 'status' => 'cleared'],
        ],
        'alerts' => $alerts,
    ];
}

function mg_admin_queue_action_updates(PDO $pdo, array $actor, array $input): array
{
    $action = strtolower(trim((string)($input['action'] ?? '')));
    $allowed = ['resolve','escalate','reopen','clear_flag','flag_review','flag_user','waiting_on_merchant','waiting_on_customer','assign_self','assign_user','unassign','set_due','clear_due'];
    if (!in_array($action, $allowed, true)) {
        throw new MgAdminAccountException('Invalid queue action.', 422);
    }
    $updates = ['updated_at = NOW()'];
    $params = [];
    if ($action === 'resolve') { $updates[] = 'status = "resolved"'; $updates[] = 'resolved_at = NOW()'; $updates[] = 'closed_at = NOW()'; }
    if ($action === 'escalate') { $updates[] = 'status = "escalated"'; }
    if ($action === 'reopen') { $updates[] = 'status = "open"'; $updates[] = 'resolved_at = NULL'; $updates[] = 'closed_at = NULL'; }
    if ($action === 'clear_flag') { $updates[] = 'flag_state = "cleared"'; }
    if ($action === 'flag_review') { $updates[] = 'flag_state = "review"'; }
    if ($action === 'flag_user') { $updates[] = 'flag_state = "flagged"'; }
    if ($action === 'waiting_on_merchant') { $updates[] = 'status = "waiting_on_merchant"'; }
    if ($action === 'waiting_on_customer') { $updates[] = 'status = "waiting_on_customer"'; }
    if ($action === 'assign_self') { $updates[] = 'assigned_admin_user_id = ?'; $params[] = (int)$actor['id']; }
    if ($action === 'assign_user') { $updates[] = 'assigned_admin_user_id = ?'; $params[] = mg_admin_queue_assign_id($pdo, $input['assigned_admin_user_id'] ?? null); }
    if ($action === 'unassign') { $updates[] = 'assigned_admin_user_id = NULL'; }
    if ($action === 'set_due') { $updates[] = 'due_at = ?'; $params[] = mg_admin_queue_due($input['due_at'] ?? null); }
    if ($action === 'clear_due') { $updates[] = 'due_at = NULL'; }
    return [$action, $updates, $params];
}

function mg_admin_queue_notice_for_action(PDO $pdo, int $noteDbId, string $notePublicId, string $action, int $actorId, string $reason): void
{
    $map = [
        'assign_self' => ['assigned','info','Queue item assigned','A follow-up queue item was assigned.'],
        'assign_user' => ['assigned','info','Queue item assigned','A follow-up queue item was assigned.'],
        'escalate' => ['escalated','critical','Queue item escalated','A follow-up queue item was escalated.'],
        'reopen' => ['reopened','warning','Queue item reopened','A follow-up queue item was reopened.'],
        'flag_review' => ['review_flag','warning','Review flag added','A follow-up queue item was marked for review.'],
        'flag_user' => ['review_flag','warning','Queue item flagged','A follow-up queue item was flagged.'],
    ];
    if (!isset($map[$action])) {
        return;
    }
    $stmt = $pdo->prepare('SELECT target_user_id, assigned_admin_user_id FROM admin_user_notes WHERE id = ? LIMIT 1');
    $stmt->execute([$noteDbId]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$note) {
        return;
    }
    [$type, $severity, $title, $message] = $map[$action];
    mg_queue_notice_create($pdo, [
        'note_id' => $noteDbId,
        'target_user_id' => (int)$note['target_user_id'],
        'assigned_admin_user_id' => $note['assigned_admin_user_id'] !== null ? (int)$note['assigned_admin_user_id'] : null,
        'actor_user_id' => $actorId,
        'notification_type' => $type,
        'severity' => $severity,
        'title' => $title,
        'message' => $message,
        'metadata' => ['note_public_id' => $notePublicId, 'action' => $action, 'reason' => $reason],
    ]);
}

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.support_queue.read', 'user:' . $actorId, 180, 60);
        mg_admin_queue_require($actor, 'admin.support_queue.view');
        mg_queue_seed_due_notices($pdo, $actorId);
        $payload = mg_admin_queue_list($pdo, $_GET + ['actor_id' => $actorId]);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($payload, 'Support queue loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('admin.support_queue.write', 'user:' . $actorId, 90, 60);
        mg_admin_queue_require($actor, 'admin.support_queue.manage');
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $noteId = mg_admin_queue_note_id($input['note_id'] ?? null);
        $reason = mg_admin_account_reason($input['reason'] ?? null);
        [$action, $updates, $params] = mg_admin_queue_action_updates($pdo, $actor, $input);
        $pdo->beginTransaction();
        $lock = $pdo->prepare('SELECT id, target_user_id, status, flag_state FROM admin_user_notes WHERE public_id = ? LIMIT 1 FOR UPDATE');
        $lock->execute([$noteId]);
        $note = $lock->fetch(PDO::FETCH_ASSOC);
        if (!$note) {
            throw new MgAdminAccountException('Queue note not found.', 404);
        }
        $sql = 'UPDATE admin_user_notes SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $params[] = (int)$note['id'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        mg_admin_queue_notice_for_action($pdo, (int)$note['id'], $noteId, $action, $actorId, $reason);
        $metadata = [
            'note_id' => $noteId,
            'target_user_id' => (int)$note['target_user_id'],
            'action' => $action,
            'reason' => $reason,
        ];
        mg_audit('admin_support_queue_' . $action, 'user', $metadata, $actorId);
        mg_event('admin.support_queue.' . $action, $metadata + ['admin_user_id' => $actorId], $actorId);
        mg_security_log('info', 'admin.support_queue.updated', 'Admin support queue action completed.', $metadata, $actorId);
        $pdo->commit();
        $payload = mg_admin_queue_list($pdo, $_GET + ['actor_id' => $actorId]);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($payload, 'Support queue updated.');
    }
    mg_fail('Method not allowed.', 405);
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_security_log('error', 'admin.support_queue.failed', 'Admin support queue request failed.', ['exception_class' => $error::class], $actorId);
    mg_fail('Unable to process support queue request.', 500);
}
