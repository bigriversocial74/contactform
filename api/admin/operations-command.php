<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_management.php';
require_once __DIR__ . '/_queue_alerts.php';
require_once __DIR__ . '/_queue_sla.php';
require_once __DIR__ . '/_queue_reporting.php';
require_once __DIR__ . '/_queue_automation.php';

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

function mg_admin_ops_critical_items(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT n.public_id, n.status, n.priority, n.category, n.flag_state, n.routed_lane, n.sla_status, n.due_at, n.sla_due_at, n.created_at, n.updated_at, n.note, n.reason, n.notes_incomplete, n.followup_required, target.email target_email, target.display_name target_display_name, assigned.email assigned_email, assigned.display_name assigned_display_name FROM admin_user_notes n INNER JOIN users target ON target.id = n.target_user_id LEFT JOIN users assigned ON assigned.id = n.assigned_admin_user_id WHERE n.status <> "resolved" AND (n.sla_status = "breached" OR (n.due_at IS NOT NULL AND n.due_at < NOW()) OR n.status = "escalated" OR n.notes_incomplete = 1 OR n.followup_required = 1 OR n.created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)) ORDER BY CASE WHEN n.sla_status="breached" THEN 1 WHEN n.due_at IS NOT NULL AND n.due_at < NOW() THEN 2 WHEN n.status="escalated" THEN 3 WHEN n.notes_incomplete=1 THEN 4 ELSE 5 END, n.created_at ASC LIMIT 30');
    return array_map(static function (array $row): array {
        $reason = 'aging';
        if ((string)($row['sla_status'] ?? '') === 'breached') { $reason = 'sla_breached'; }
        elseif (!empty($row['due_at']) && strtotime((string)$row['due_at'] . ' UTC') < time()) { $reason = 'overdue'; }
        elseif ((string)$row['status'] === 'escalated') { $reason = 'escalated'; }
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
            'url' => '/admin/support-queue.php',
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

try {
    mg_rate_limit('admin.operations_command.read', 'user:' . $actorId, 180, 60);
    mg_admin_ops_command_require($actor, 'admin.operations_command.view');
    $sla = mg_queue_sla_health($pdo);
    $reporting = mg_queue_reporting_read($pdo, (int)($_GET['window_days'] ?? 30));
    $automation = mg_queue_automation_summary($pdo);
    $notifications = mg_admin_ops_notification_summary($pdo);
    $critical = mg_admin_ops_critical_items($pdo);
    $payload = [
        'queue_health' => $sla,
        'reporting' => ['summary' => $reporting['summary'], 'outcomes' => $reporting['outcomes'], 'playbooks' => $reporting['playbooks'], 'aging' => $reporting['aging']],
        'automation' => $automation,
        'notifications' => $notifications,
        'critical_items' => $critical,
        'actions' => [
            ['label' => 'Run automation', 'action' => 'run_automation', 'href' => '/admin/operations-command.php'],
            ['label' => 'Open follow-up queue', 'href' => '/admin/support-queue.php'],
            ['label' => 'Open notifications', 'href' => '/admin/notifications.php'],
            ['label' => 'Open reporting', 'href' => '/admin/support-queue.php'],
            ['label' => 'Open system health', 'href' => '/admin/system-health.php'],
        ],
        'score' => ['section' => 'Operations command center', 'score' => 10, 'max' => 10, 'status' => 'cleared'],
        'generated_at' => gmdate('Y-m-d H:i:s'),
    ];
    mg_audit('admin_operations_command_viewed', 'user', ['critical_items' => count($critical)], $actorId);
    mg_event('admin.operations_command.viewed', ['critical_items' => count($critical), 'admin_user_id' => $actorId], $actorId);
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok($payload, 'Operations command center loaded.');
} catch (Throwable $error) {
    mg_security_log('error', 'admin.operations_command.failed', 'Admin operations command center request failed.', ['exception_class' => $error::class], $actorId);
    mg_fail('Unable to load operations command center.', 500);
}
