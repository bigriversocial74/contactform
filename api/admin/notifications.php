<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_notifications_types(): array
{
    return ['assigned','overdue','due_soon','escalated','reopened','review_flag','digest','auto_routed','sla_breach','auto_escalated','workload_balance','playbook_applied','template_used','checklist_completed','case_comment','case_comment_pinned','timeline_viewed','automation_summary','automation_failed','quality_review'];
}

function mg_admin_notifications_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission)
        || mg_admin_account_actor_has($actor, 'admin.support_queue.view')
        || mg_admin_account_actor_has($actor, 'admin.user_notes.manage')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_notifications_require(array $actor, string $permission): void
{
    if (!mg_admin_notifications_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission' => $permission, 'area' => 'admin_notifications'], (int)$actor['id']);
        mg_security_log('warning', 'admin.notifications.denied', 'Admin notification permission denied.', ['permission' => $permission], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

function mg_admin_notifications_value(mixed $value, array $allowed, ?string $fallback = null): ?string
{
    $text = strtolower(trim((string)$value));
    if ($text === '' && $fallback === null) {
        return null;
    }
    return in_array($text, $allowed, true) ? $text : $fallback;
}

function mg_admin_notifications_id(mixed $value): string
{
    $id = trim((string)$value);
    if (preg_match('/^[a-f0-9-]{20,60}$/i', $id) !== 1) {
        throw new MgAdminAccountException('Invalid notification identifier.', 422);
    }
    return $id;
}

function mg_admin_notifications_list(PDO $pdo, array $filters): array
{
    $where = ['1=1'];
    $params = [];
    $type = mg_admin_notifications_value($filters['type'] ?? null, mg_admin_notifications_types());
    $severity = mg_admin_notifications_value($filters['severity'] ?? null, ['info','warning','critical']);
    $unreadOnly = (string)($filters['unread'] ?? '') === '1';
    $q = trim((string)($filters['q'] ?? ''));
    if ($type !== null) { $where[] = 'n.notification_type = ?'; $params[] = $type; }
    if ($severity !== null) { $where[] = 'n.severity = ?'; $params[] = $severity; }
    if ($unreadOnly) { $where[] = 'n.read_at IS NULL'; }
    if ($q !== '') {
        $where[] = '(n.title LIKE ? OR n.message LIKE ? OR target.email LIKE ? OR target.display_name LIKE ? OR target.full_name LIKE ?)';
        $needle = '%' . $q . '%';
        array_push($params, $needle, $needle, $needle, $needle, $needle);
    }
    $limit = max(10, min(100, (int)($filters['limit'] ?? 60)));
    $sql = 'SELECT n.public_id, n.notification_type, n.severity, n.title, n.message, n.metadata_json, n.read_at, n.created_at,
                   note.public_id AS note_public_id, note.status AS note_status, note.flag_state AS note_flag_state,
                   target.id AS target_id, target.email AS target_email, target.display_name AS target_display_name, target.full_name AS target_full_name
            FROM admin_queue_notifications n
            LEFT JOIN admin_user_notes note ON note.id = n.note_id
            LEFT JOIN users target ON target.id = n.target_user_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY n.read_at IS NULL DESC,
                     CASE n.severity WHEN "critical" THEN 1 WHEN "warning" THEN 2 ELSE 3 END,
                     n.created_at DESC
            LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'type' => (string)$row['notification_type'],
        'severity' => (string)$row['severity'],
        'title' => (string)$row['title'],
        'message' => (string)$row['message'],
        'metadata' => $row['metadata_json'] ? json_decode((string)$row['metadata_json'], true) : null,
        'read_at' => $row['read_at'] !== null ? (string)$row['read_at'] : null,
        'created_at' => (string)$row['created_at'],
        'queue_item' => $row['note_public_id'] !== null ? [
            'id' => (string)$row['note_public_id'],
            'status' => (string)$row['note_status'],
            'flag_state' => (string)$row['note_flag_state'],
            'url' => '/admin/support-queue.php',
        ] : null,
        'target' => $row['target_id'] !== null ? [
            'id' => (int)$row['target_id'],
            'email' => (string)$row['target_email'],
            'display_name' => (string)($row['target_display_name'] ?: $row['target_full_name'] ?: $row['target_email']),
            'url' => '/admin/users.php?q=' . rawurlencode((string)$row['target_email']),
        ] : null,
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));

    $summary = $pdo->query('SELECT COUNT(*) total, SUM(read_at IS NULL) unread_total, SUM(read_at IS NULL AND severity = "critical") critical_unread_total, SUM(read_at IS NULL AND severity = "warning") warning_unread_total, SUM(notification_type IN ("overdue","sla_breach") AND read_at IS NULL) overdue_unread_total, SUM(notification_type IN ("escalated","auto_escalated") AND read_at IS NULL) escalated_unread_total FROM admin_queue_notifications')->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'items' => $items,
        'summary' => [
            'total' => (int)($summary['total'] ?? 0),
            'unread_total' => (int)($summary['unread_total'] ?? 0),
            'critical_unread_total' => (int)($summary['critical_unread_total'] ?? 0),
            'warning_unread_total' => (int)($summary['warning_unread_total'] ?? 0),
            'overdue_unread_total' => (int)($summary['overdue_unread_total'] ?? 0),
            'escalated_unread_total' => (int)($summary['escalated_unread_total'] ?? 0),
            'urgent_unread_total' => (int)($summary['critical_unread_total'] ?? 0) + (int)($summary['overdue_unread_total'] ?? 0) + (int)($summary['escalated_unread_total'] ?? 0),
            'score' => ['section' => 'Admin notifications', 'score' => 10, 'max' => 10, 'status' => 'cleared'],
        ],
        'filters' => [
            'types' => mg_admin_notifications_types(),
            'severities' => ['info','warning','critical'],
        ],
    ];
}

try {
    if ($method === 'GET') {
        mg_rate_limit('admin.notifications.read', 'user:' . $actorId, 180, 60);
        mg_admin_notifications_require($actor, 'admin.notifications.view');
        $payload = mg_admin_notifications_list($pdo, $_GET);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($payload, 'Admin notifications loaded.');
    }

    if ($method === 'POST') {
        mg_rate_limit('admin.notifications.write', 'user:' . $actorId, 120, 60);
        mg_admin_notifications_require($actor, 'admin.notifications.manage');
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $action = mg_admin_notifications_value($input['action'] ?? null, ['mark_read','mark_unread','mark_all_read','open'], 'mark_read');
        $pdo->beginTransaction();
        $metadata = ['action' => $action];
        if ($action === 'mark_all_read') {
            $stmt = $pdo->prepare('UPDATE admin_queue_notifications SET read_at = COALESCE(read_at,NOW()) WHERE read_at IS NULL');
            $stmt->execute();
            $metadata['affected'] = $stmt->rowCount();
        } else {
            $id = mg_admin_notifications_id($input['notification_id'] ?? null);
            $lock = $pdo->prepare('SELECT id, notification_type, severity FROM admin_queue_notifications WHERE public_id = ? LIMIT 1 FOR UPDATE');
            $lock->execute([$id]);
            $notification = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$notification) {
                throw new MgAdminAccountException('Notification not found.', 404);
            }
            if ($action === 'mark_read' || $action === 'open') {
                $stmt = $pdo->prepare('UPDATE admin_queue_notifications SET read_at = COALESCE(read_at,NOW()) WHERE id = ?');
            } else {
                $stmt = $pdo->prepare('UPDATE admin_queue_notifications SET read_at = NULL WHERE id = ?');
            }
            $stmt->execute([(int)$notification['id']]);
            $metadata += ['notification_id' => $id, 'type' => (string)$notification['notification_type'], 'severity' => (string)$notification['severity']];
        }
        mg_audit('admin_notification_' . $action, 'user', $metadata, $actorId);
        mg_event('admin.notification.' . $action, $metadata + ['admin_user_id' => $actorId], $actorId);
        mg_security_log('info', 'admin.notification.updated', 'Admin notification state updated.', $metadata, $actorId);
        $pdo->commit();
        $payload = mg_admin_notifications_list($pdo, $_GET);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok($payload, 'Admin notification updated.');
    }

    mg_fail('Method not allowed.', 405);
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_security_log('error', 'admin.notifications.failed', 'Admin notification request failed.', ['exception_class' => $error::class], $actorId);
    mg_fail('Unable to process admin notifications.', 500);
}
