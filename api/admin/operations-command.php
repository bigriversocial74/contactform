<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once __DIR__ . '/_queue_alerts.php';
require_once __DIR__ . '/_queue_sla.php';
require_once __DIR__ . '/_queue_reporting.php';
require_once __DIR__ . '/_queue_automation.php';
require_once __DIR__ . '/_ops_incidents.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$actor = mg_require_api_user();
$actorId = (int)$actor['id'];
$pdo = mg_db();

function mg_admin_ops_command_has(array $actor, string $permission): bool
{
    return mg_admin_account_actor_has($actor, $permission)
        || mg_admin_account_actor_has($actor, 'admin.support_queue.manage')
        || mg_admin_account_actor_has($actor, 'admin.queue_automation.run')
        || mg_admin_account_actor_has($actor, 'admin.queue_reporting.view')
        || mg_admin_account_actor_has($actor, 'admin.notifications.view')
        || mg_admin_account_actor_has($actor, 'admin.operations_incidents.manage')
        || mg_admin_account_actor_has($actor, 'admin.users.manage');
}

function mg_admin_ops_command_require(array $actor, string $permission): void
{
    if (!mg_admin_ops_command_has($actor, $permission)) {
        mg_audit('permission_denied', 'security', ['permission' => $permission, 'area' => 'admin_operations_command'], (int)$actor['id']);
        mg_security_log('warning', 'admin.operations_command.denied', 'Admin operations command center permission denied.', ['permission' => $permission], (int)$actor['id']);
        mg_fail('Permission denied.', 403);
    }
}

function mg_admin_ops_command_note_id(mixed $value): string
{
    $id = trim((string)$value);
    if (preg_match('/^[a-f0-9-]{20,60}$/i', $id) !== 1) {
        throw new MgAdminAccountException('Invalid queue note identifier.', 422);
    }
    return $id;
}

function mg_admin_ops_filter(mixed $value): ?string
{
    $filter = strtolower(trim((string)$value));
    return in_array($filter, ['breached','overdue','escalated','unassigned','incomplete','followup','stale_waiting','aging'], true) ? $filter : null;
}

function mg_admin_ops_notification_summary(PDO $pdo): array
{
    $row = $pdo->query('SELECT COUNT(*) total, SUM(read_at IS NULL) unread_total, SUM(read_at IS NULL AND severity="critical") critical_unread_total, SUM(read_at IS NULL AND severity="warning") warning_unread_total, SUM(notification_type IN ("overdue","sla_breach") AND read_at IS NULL) overdue_unread_total, SUM(notification_type IN ("escalated","auto_escalated") AND read_at IS NULL) escalated_unread_total, SUM(notification_type IN ("automation_failed") AND read_at IS NULL) automation_failed_unread_total FROM admin_queue_notifications')->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'total' => (int)($row['total'] ?? 0),
        'unread_total' => (int)($row['unread_total'] ?? 0),
        'critical_unread_total' => (int)($row['critical_unread_total'] ?? 0),
        'warning_unread_total' => (int)($row['warning_unread_total'] ?? 0),
        'overdue_unread_total' => (int)($row['overdue_unread_total'] ?? 0),
        'escalated_unread_total' => (int)($row['escalated_unread_total'] ?? 0),
        'automation_failed_unread_total' => (int)($row['automation_failed_unread_total'] ?? 0),
    ];
}

function mg_admin_ops_filter_sql(?string $filter): string
{
    return match ($filter) {
        'breached' => 'n.sla_status = "breached"',
        'overdue' => 'n.due_at IS NOT NULL AND n.due_at < NOW()',
        'escalated' => 'n.status = "escalated"',
        'unassigned' => 'n.assigned_admin_user_id IS NULL',
        'incomplete' => 'n.notes_incomplete = 1',
        'followup' => 'n.followup_required = 1',
        'stale_waiting' => 'n.status IN ("waiting_on_merchant","waiting_on_customer") AND n.updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)',
        'aging' => 'n.created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)',
        default => '(n.sla_status = "breached" OR (n.due_at IS NOT NULL AND n.due_at < NOW()) OR n.status = "escalated" OR n.assigned_admin_user_id IS NULL OR n.notes_incomplete = 1 OR n.followup_required = 1 OR n.created_at < DATE_SUB(NOW(), INTERVAL 14 DAY))',
    };
}

function mg_admin_ops_critical_counts(PDO $pdo): array
{
    $row = $pdo->query('SELECT
        SUM(status <> "resolved" AND sla_status = "breached") breached,
        SUM(status <> "resolved" AND due_at IS NOT NULL AND due_at < NOW()) overdue,
        SUM(status <> "resolved" AND status = "escalated") escalated,
        SUM(status <> "resolved" AND assigned_admin_user_id IS NULL) unassigned,
        SUM(status <> "resolved" AND notes_incomplete = 1) incomplete,
        SUM(status <> "resolved" AND followup_required = 1) followup,
        SUM(status <> "resolved" AND status IN ("waiting_on_merchant","waiting_on_customer") AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)) stale_waiting,
        SUM(status <> "resolved" AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)) aging
        FROM admin_user_notes')->fetch(PDO::FETCH_ASSOC) ?: [];
    return array_map('intval', $row);
}

function mg_admin_ops_critical_items(PDO $pdo, ?string $filter = null): array
{
    $where = 'n.status <> "resolved" AND (' . mg_admin_ops_filter_sql($filter) . ')';
    $sql = 'SELECT n.public_id, n.status, n.priority, n.category, n.flag_state, n.routed_lane, n.sla_status, n.due_at, n.sla_due_at, n.created_at, n.updated_at, n.note, n.reason, n.notes_incomplete, n.followup_required, n.assigned_admin_user_id, target.email target_email, target.display_name target_display_name, assigned.email assigned_email, assigned.display_name assigned_display_name FROM admin_user_notes n INNER JOIN users target ON target.id = n.target_user_id LEFT JOIN users assigned ON assigned.id = n.assigned_admin_user_id WHERE ' . $where . ' ORDER BY CASE WHEN n.sla_status="breached" THEN 1 WHEN n.due_at IS NOT NULL AND n.due_at < NOW() THEN 2 WHEN n.status="escalated" THEN 3 WHEN n.assigned_admin_user_id IS NULL THEN 4 WHEN n.notes_incomplete=1 THEN 5 ELSE 6 END, n.created_at ASC LIMIT 40';
    $stmt = $pdo->query($sql);
    return array_map(static function (array $row): array {
        $reason = 'aging';
        if ((string)($row['sla_status'] ?? '') === 'breached') { $reason = 'sla_breached'; }
        elseif (!empty($row['due_at']) && strtotime((string)$row['due_at'] . ' UTC') < time()) { $reason = 'overdue'; }
        elseif ((string)$row['status'] === 'escalated') { $reason = 'escalated'; }
        elseif (empty($row['assigned_admin_user_id'])) { $reason = 'unassigned'; }
        elseif (!empty($row['notes_incomplete'])) { $reason = 'incomplete_notes'; }
        elseif (!empty($row['followup_required'])) { $reason = 'followup_required'; }
        return [
            'id' => (string)$row['public_id'],
            'reason' => $reason,
            'status' => (string)$row['status'],
            'priority' => (string)$row['priority'],
            'category' => (string)$row['category'],
            'flag_state' => (string)$row['flag_state'],
            'routed_lane' => (string)($row['routed_lane'] ?? 'general'),
            'sla_status' => (string)($row['sla_status'] ?? 'unknown'),
            'due_at' => $row['due_at'] !== null ? (string)$row['due_at'] : null,
            'sla_due_at' => $row['sla_due_at'] !== null ? (string)$row['sla_due_at'] : null,
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
            'note' => mb_substr((string)$row['note'], 0, 220),
            'target' => ['email' => (string)$row['target_email'], 'display_name' => (string)($row['target_display_name'] ?: $row['target_email'])],
            'assigned' => $row['assigned_email'] !== null ? ['email' => (string)$row['assigned_email'], 'display_name' => (string)($row['assigned_display_name'] ?: $row['assigned_email'])] : null,
            'url' => '/admin/support-queue.php?case=' . rawurlencode((string)$row['public_id']),
            'timeline_url' => '/admin/support-queue.php?timeline=' . rawurlencode((string)$row['public_id']),
            'actions' => ['assign_self','mark_reviewed','open_queue','open_timeline'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_admin_ops_score(array $sla, array $notifications, array $reporting, array $automation, array $counts, array $incidents): array
{
    $score = 100;
    $score -= min(30, ((int)($sla['summary']['breached_total'] ?? 0)) * 6);
    $score -= min(20, ((int)($notifications['critical_unread_total'] ?? 0)) * 4);
    $score -= min(15, ((int)($counts['aging'] ?? 0)) * 3);
    $score -= min(15, ((int)($counts['incomplete'] ?? 0)) * 3);
    $score -= min(20, ((int)($incidents['summary']['critical_total'] ?? 0)) * 10);
    $lastCompleted = (string)($automation['summary']['last_completed_at'] ?? '');
    if ($lastCompleted === '' || strtotime($lastCompleted . ' UTC') < time() - 86400) {
        $score -= 15;
    }
    if ((float)($reporting['summary']['reopen_rate'] ?? 0) > 5) {
        $score -= 5;
    }
    $score = max(0, $score);
    return ['value' => $score, 'label' => $score >= 90 ? 'healthy' : ($score >= 70 ? 'watch' : 'attention'), 'inputs' => ['sla_breached' => (int)($sla['summary']['breached_total'] ?? 0), 'critical_unread' => (int)($notifications['critical_unread_total'] ?? 0), 'aging' => (int)($counts['aging'] ?? 0), 'incomplete_notes' => (int)($counts['incomplete'] ?? 0), 'active_incidents' => (int)($incidents['summary']['active_total'] ?? 0), 'automation_last_completed_at' => $lastCompleted]];
}

function mg_admin_ops_apply_action(PDO $pdo, int $actorId, string $noteId, string $action): array
{
    $stmt = $pdo->prepare('SELECT id, public_id, target_user_id, assigned_admin_user_id FROM admin_user_notes WHERE public_id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$noteId]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$note) {
        throw new MgAdminAccountException('Queue item not found.', 404);
    }
    if ($action === 'assign_self') {
        $update = $pdo->prepare('UPDATE admin_user_notes SET assigned_admin_user_id = ?, updated_at = NOW() WHERE id = ?');
        $update->execute([$actorId, (int)$note['id']]);
        mg_queue_notice_create($pdo, ['note_id' => (int)$note['id'], 'target_user_id' => (int)$note['target_user_id'], 'assigned_admin_user_id' => $actorId, 'actor_user_id' => $actorId, 'notification_type' => 'assigned', 'severity' => 'info', 'title' => 'Queue item assigned from command center', 'message' => 'A queue item was assigned from the operations command center.', 'metadata' => ['note_public_id' => $noteId]]);
    } elseif ($action === 'mark_reviewed') {
        $update = $pdo->prepare('UPDATE admin_user_notes SET notes_incomplete = 0, followup_required = 0, resolution_reviewed_at = NOW(), updated_at = NOW() WHERE id = ?');
        $update->execute([(int)$note['id']]);
        mg_queue_notice_create($pdo, ['note_id' => (int)$note['id'], 'target_user_id' => (int)$note['target_user_id'], 'assigned_admin_user_id' => $note['assigned_admin_user_id'] !== null ? (int)$note['assigned_admin_user_id'] : null, 'actor_user_id' => $actorId, 'notification_type' => 'quality_review', 'severity' => 'info', 'title' => 'Queue item reviewed', 'message' => 'A queue item was marked reviewed from the operations command center.', 'metadata' => ['note_public_id' => $noteId]]);
    } else {
        throw new MgAdminAccountException('Invalid command center action.', 422);
    }
    return ['note_id' => $noteId, 'action' => $action, 'updated' => true];
}

try {
    if ($method === 'POST') {
        mg_rate_limit('admin.operations_command.write', 'user:' . $actorId, 60, 60);
        mg_admin_ops_command_require($actor, 'admin.operations_command.manage');
        $input = mg_input();
        mg_require_csrf_for_write($input);
        $action = strtolower(trim((string)($input['action'] ?? '')));
        if ($action === 'run_automation') {
            $result = mg_queue_automation_run($pdo, $actorId, 'manual');
        } else {
            $noteId = mg_admin_ops_command_note_id($input['note_id'] ?? null);
            $pdo->beginTransaction();
            $result = mg_admin_ops_apply_action($pdo, $actorId, $noteId, $action);
            $pdo->commit();
        }
        mg_audit('admin_operations_command_' . $action, 'user', $result, $actorId);
        mg_event('admin.operations_command.' . $action, $result + ['admin_user_id' => $actorId], $actorId);
        mg_security_log('info', 'admin.operations_command.action', 'Admin operations command center action completed.', $result, $actorId);
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Cookie, Authorization');
        mg_ok(['result' => $result], 'Command center action completed.');
    }

    mg_rate_limit('admin.operations_command.read', 'user:' . $actorId, 180, 60);
    mg_admin_ops_command_require($actor, 'admin.operations_command.view');
    $filter = mg_admin_ops_filter($_GET['critical'] ?? null);
    $sla = mg_queue_sla_health($pdo);
    $reporting = mg_queue_reporting_read($pdo, (int)($_GET['window_days'] ?? 30));
    $automation = mg_queue_automation_summary($pdo);
    $notifications = mg_admin_ops_notification_summary($pdo);
    $counts = mg_admin_ops_critical_counts($pdo);
    $critical = mg_admin_ops_critical_items($pdo, $filter);
    $incidents = mg_ops_incident_payload($pdo);
    $payload = [
        'queue_health' => $sla,
        'reporting' => ['summary' => $reporting['summary'], 'outcomes' => $reporting['outcomes'], 'playbooks' => $reporting['playbooks'], 'aging' => $reporting['aging']],
        'automation' => $automation,
        'notifications' => $notifications,
        'incidents' => $incidents,
        'critical_items' => $critical,
        'critical_filter' => $filter,
        'drilldowns' => [
            ['filter' => 'breached', 'label' => 'Breached SLA', 'count' => (int)($counts['breached'] ?? 0), 'href' => '/admin/operations-command.php?critical=breached'],
            ['filter' => 'overdue', 'label' => 'Overdue', 'count' => (int)($counts['overdue'] ?? 0), 'href' => '/admin/operations-command.php?critical=overdue'],
            ['filter' => 'escalated', 'label' => 'Escalated', 'count' => (int)($counts['escalated'] ?? 0), 'href' => '/admin/operations-command.php?critical=escalated'],
            ['filter' => 'unassigned', 'label' => 'Unassigned', 'count' => (int)($counts['unassigned'] ?? 0), 'href' => '/admin/operations-command.php?critical=unassigned'],
            ['filter' => 'incomplete', 'label' => 'Incomplete notes', 'count' => (int)($counts['incomplete'] ?? 0), 'href' => '/admin/operations-command.php?critical=incomplete'],
            ['filter' => 'followup', 'label' => 'Follow-up required', 'count' => (int)($counts['followup'] ?? 0), 'href' => '/admin/operations-command.php?critical=followup'],
            ['filter' => 'stale_waiting', 'label' => 'Stale waiting', 'count' => (int)($counts['stale_waiting'] ?? 0), 'href' => '/admin/operations-command.php?critical=stale_waiting'],
        ],
        'actions' => [
            ['label' => 'Declare incident', 'action' => 'declare_incident', 'href' => '/admin/operations-command.php'],
            ['label' => 'Run automation', 'action' => 'run_automation', 'href' => '/admin/operations-command.php'],
            ['label' => 'Open follow-up queue', 'href' => '/admin/support-queue.php'],
            ['label' => 'Open notifications', 'href' => '/admin/notifications.php'],
            ['label' => 'Open reporting', 'href' => '/admin/support-queue.php'],
            ['label' => 'Open system health', 'href' => '/admin/system-health.php'],
        ],
        'ops_score' => mg_admin_ops_score($sla, $notifications, $reporting, $automation, $counts, $incidents),
        'score' => ['section' => 'Operations incident mode', 'score' => 10, 'max' => 10, 'status' => 'cleared'],
        'generated_at' => gmdate('Y-m-d H:i:s'),
    ];
    mg_audit('admin_operations_command_viewed', 'user', ['critical_items' => count($critical), 'critical_filter' => $filter, 'active_incidents' => $incidents['summary']['active_total']], $actorId);
    mg_event('admin.operations_command.viewed', ['critical_items' => count($critical), 'critical_filter' => $filter, 'active_incidents' => $incidents['summary']['active_total'], 'admin_user_id' => $actorId], $actorId);
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok($payload, 'Operations command center loaded.');
} catch (MgAdminAccountException $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_fail($error->getMessage(), $error->httpStatus());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    mg_security_log('error', 'admin.operations_command.failed', 'Admin operations command center request failed.', ['exception_class' => $error::class], $actorId);
    mg_fail('Unable to load operations command center.', 500);
}
