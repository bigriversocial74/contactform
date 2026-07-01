<?php
declare(strict_types=1);

require_once __DIR__ . '/_admin_schema.php';

function mg_queue_alert_empty_summary(array $schemaRequired = []): array
{
    return [
        'total' => 0,
        'active_total' => 0,
        'escalated_total' => 0,
        'waiting_on_merchant_total' => 0,
        'waiting_on_customer_total' => 0,
        'review_total' => 0,
        'overdue_total' => 0,
        'due_today_total' => 0,
        'due_soon_total' => 0,
        'assigned_to_me_total' => 0,
        'sidebar_badge_total' => 0,
        'header_badge_total' => 0,
        'schema_required' => $schemaRequired,
        'score' => ['section' => 'Queue alerts', 'score' => $schemaRequired ? 7 : 10, 'max' => 10, 'status' => $schemaRequired ? 'schema_required' : 'cleared'],
    ];
}

function mg_queue_alert_summary(PDO $pdo, int $actorId): array
{
    if (!mg_admin_schema_has_table($pdo, 'admin_user_notes')) {
        return mg_queue_alert_empty_summary(['admin_user_notes']);
    }

    $columns = mg_admin_schema_columns($pdo, 'admin_user_notes');
    $required = ['status'];
    $missing = array_values(array_filter($required, static fn(string $column): bool => empty($columns[$column])));
    if ($missing) {
        return mg_queue_alert_empty_summary($missing);
    }

    $flagReview = !empty($columns['flag_state']) ? 'SUM(flag_state IN ("flagged","review"))' : '0';
    $overdue = !empty($columns['due_at']) ? 'SUM(due_at IS NOT NULL AND due_at < NOW() AND status <> "resolved")' : '0';
    $dueToday = !empty($columns['due_at']) ? 'SUM(due_at >= CURDATE() AND due_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND status <> "resolved")' : '0';
    $dueSoon = !empty($columns['due_at']) ? 'SUM(due_at >= NOW() AND due_at < DATE_ADD(NOW(), INTERVAL 48 HOUR) AND status <> "resolved")' : '0';
    $assigned = !empty($columns['assigned_admin_user_id']) ? 'SUM(assigned_admin_user_id = ? AND status <> "resolved")' : '0';

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) total,
                SUM(status <> "resolved") active_total,
                SUM(status = "escalated") escalated_total,
                SUM(status = "waiting_on_merchant") waiting_on_merchant_total,
                SUM(status = "waiting_on_customer") waiting_on_customer_total,
                ' . $flagReview . ' review_total,
                ' . $overdue . ' overdue_total,
                ' . $dueToday . ' due_today_total,
                ' . $dueSoon . ' due_soon_total,
                ' . $assigned . ' assigned_to_me_total
         FROM admin_user_notes'
    );
    $stmt->execute(!empty($columns['assigned_admin_user_id']) ? [$actorId] : []);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'total' => (int)($row['total'] ?? 0),
        'active_total' => (int)($row['active_total'] ?? 0),
        'escalated_total' => (int)($row['escalated_total'] ?? 0),
        'waiting_on_merchant_total' => (int)($row['waiting_on_merchant_total'] ?? 0),
        'waiting_on_customer_total' => (int)($row['waiting_on_customer_total'] ?? 0),
        'review_total' => (int)($row['review_total'] ?? 0),
        'overdue_total' => (int)($row['overdue_total'] ?? 0),
        'due_today_total' => (int)($row['due_today_total'] ?? 0),
        'due_soon_total' => (int)($row['due_soon_total'] ?? 0),
        'assigned_to_me_total' => (int)($row['assigned_to_me_total'] ?? 0),
        'sidebar_badge_total' => (int)($row['active_total'] ?? 0) + (int)($row['escalated_total'] ?? 0),
        'header_badge_total' => (int)($row['overdue_total'] ?? 0) + (int)($row['escalated_total'] ?? 0),
        'schema_required' => [],
        'score' => ['section' => 'Queue alerts', 'score' => 10, 'max' => 10, 'status' => 'cleared'],
    ];
}

function mg_queue_notice_type(PDO $pdo, string $type): string
{
    $values = mg_admin_schema_enum_values($pdo, 'admin_queue_notifications', 'notification_type');
    if (!$values || in_array($type, $values, true)) {
        return $type;
    }
    $fallbacks = [
        'automation_summary' => 'digest',
        'automation_failed' => 'review_flag',
        'quality_review' => 'review_flag',
        'auto_routed' => 'digest',
        'sla_breach' => 'overdue',
        'auto_escalated' => 'escalated',
        'workload_balance' => 'digest',
        'playbook_applied' => 'digest',
        'template_used' => 'digest',
        'checklist_completed' => 'digest',
        'case_comment' => 'digest',
        'case_comment_pinned' => 'digest',
        'timeline_viewed' => 'digest',
    ];
    $fallback = $fallbacks[$type] ?? 'digest';
    if (in_array($fallback, $values, true)) {
        return $fallback;
    }
    return $values[0] ?? $type;
}

function mg_queue_notice_exists(PDO $pdo, ?int $noteId, string $type): bool
{
    if (!mg_admin_schema_has_table($pdo, 'admin_queue_notifications')) {
        return false;
    }
    $columns = mg_admin_schema_columns($pdo, 'admin_queue_notifications');
    if (empty($columns['id']) || empty($columns['notification_type'])) {
        return false;
    }
    $storedType = mg_queue_notice_type($pdo, $type);
    $where = ['notification_type = ?'];
    $params = [$storedType];
    if ($noteId !== null && !empty($columns['note_id'])) {
        $where[] = 'note_id = ?';
        $params[] = $noteId;
    }
    if (!empty($columns['created_at'])) {
        $where[] = 'created_at >= CURDATE()';
    }
    $stmt = $pdo->prepare('SELECT id FROM admin_queue_notifications WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
    $stmt->execute($params);
    return (bool)$stmt->fetch();
}

function mg_queue_notice_create(PDO $pdo, array $data): void
{
    if (!mg_admin_schema_has_table($pdo, 'admin_queue_notifications')) {
        return;
    }
    $columns = mg_admin_schema_columns($pdo, 'admin_queue_notifications');
    $required = ['public_id', 'notification_type', 'title', 'message'];
    foreach ($required as $column) {
        if (empty($columns[$column])) {
            return;
        }
    }

    $noteId = isset($data['note_id']) ? (int)$data['note_id'] : null;
    $originalType = (string)$data['notification_type'];
    $storedType = mg_queue_notice_type($pdo, $originalType);
    if (mg_queue_notice_exists($pdo, $noteId, $originalType)) {
        return;
    }

    $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];
    if ($storedType !== $originalType) {
        $metadata['original_notification_type'] = $originalType;
    }
    if (!empty($data['assigned_admin_user_id']) && empty($columns['assigned_admin_user_id'])) {
        $metadata['assigned_admin_user_id'] = (int)$data['assigned_admin_user_id'];
    }
    if (!empty($data['actor_user_id']) && empty($columns['actor_user_id'])) {
        $metadata['actor_user_id'] = (int)$data['actor_user_id'];
    }
    if (!empty($data['target_user_id']) && empty($columns['target_user_id'])) {
        $metadata['target_user_id'] = (int)$data['target_user_id'];
    }

    $insert = [
        'public_id' => mg_public_uuid(),
        'notification_type' => $storedType,
        'title' => (string)$data['title'],
        'message' => (string)$data['message'],
    ];
    if (!empty($columns['note_id'])) {
        $insert['note_id'] = $noteId;
    }
    if (!empty($columns['target_user_id'])) {
        $insert['target_user_id'] = $data['target_user_id'] ?? null;
    }
    if (!empty($columns['assigned_admin_user_id'])) {
        $insert['assigned_admin_user_id'] = $data['assigned_admin_user_id'] ?? null;
    }
    if (!empty($columns['actor_user_id'])) {
        $insert['actor_user_id'] = $data['actor_user_id'] ?? null;
    }
    if (!empty($columns['severity'])) {
        $insert['severity'] = $data['severity'] ?? 'info';
    }
    if (!empty($columns['metadata_json'])) {
        $insert['metadata_json'] = $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null;
    }

    $columnNames = array_keys($insert);
    $placeholders = array_fill(0, count($columnNames), '?');
    $params = array_values($insert);
    if (!empty($columns['created_at'])) {
        $columnNames[] = 'created_at';
        $placeholders[] = 'NOW()';
    }
    $stmt = $pdo->prepare('INSERT INTO admin_queue_notifications (' . implode(',', $columnNames) . ') VALUES (' . implode(',', $placeholders) . ')');
    $stmt->execute($params);
}

function mg_queue_seed_due_notices(PDO $pdo, int $actorId): int
{
    if (!mg_admin_schema_has_table($pdo, 'admin_user_notes')) {
        return 0;
    }
    $columns = mg_admin_schema_columns($pdo, 'admin_user_notes');
    $missing = mg_admin_schema_missing_columns($pdo, 'admin_user_notes', ['id', 'public_id', 'status', 'due_at']);
    if ($missing) {
        return 0;
    }

    $targetExpr = !empty($columns['target_user_id']) ? 'target_user_id' : 'NULL AS target_user_id';
    $assignedExpr = !empty($columns['assigned_admin_user_id']) ? 'assigned_admin_user_id' : 'NULL AS assigned_admin_user_id';
    $stmt = $pdo->query(
        'SELECT id, public_id, ' . $targetExpr . ', ' . $assignedExpr . ', due_at,
                CASE WHEN due_at < NOW() THEN "overdue" ELSE "due_soon" END notice_type
         FROM admin_user_notes
         WHERE status <> "resolved" AND due_at IS NOT NULL AND due_at < DATE_ADD(NOW(), INTERVAL 48 HOUR)
         ORDER BY due_at ASC LIMIT 100'
    );
    $created = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $note) {
        $type = (string)$note['notice_type'];
        if (mg_queue_notice_exists($pdo, (int)$note['id'], $type)) {
            continue;
        }
        mg_queue_notice_create($pdo, [
            'note_id' => (int)$note['id'],
            'target_user_id' => $note['target_user_id'] !== null ? (int)$note['target_user_id'] : null,
            'assigned_admin_user_id' => $note['assigned_admin_user_id'] !== null ? (int)$note['assigned_admin_user_id'] : null,
            'actor_user_id' => $actorId,
            'notification_type' => $type,
            'severity' => $type === 'overdue' ? 'critical' : 'warning',
            'title' => $type === 'overdue' ? 'Follow-up overdue' : 'Follow-up due soon',
            'message' => $type === 'overdue' ? 'A queue item is overdue.' : 'A queue item is due within 48 hours.',
            'metadata' => ['note_public_id' => (string)$note['public_id'], 'due_at' => (string)$note['due_at']],
        ]);
        $created++;
    }
    return $created;
}

function mg_queue_digest(PDO $pdo, int $actorId): array
{
    if (!mg_admin_schema_has_table($pdo, 'admin_user_notes')) {
        return [
            'alerts' => mg_queue_alert_summary($pdo, $actorId),
            'top_items' => [],
            'score' => ['section' => 'Daily queue digest', 'score' => 7, 'max' => 10, 'status' => 'schema_required'],
        ];
    }

    $columns = mg_admin_schema_columns($pdo, 'admin_user_notes');
    $select = [
        'n.public_id',
        !empty($columns['category']) ? 'n.category' : '"general" AS category',
        !empty($columns['priority']) ? 'n.priority' : '"normal" AS priority',
        !empty($columns['status']) ? 'n.status' : '"open" AS status',
        !empty($columns['flag_state']) ? 'n.flag_state' : '"none" AS flag_state',
        !empty($columns['due_at']) ? 'n.due_at' : 'NULL AS due_at',
        'target.email target_email',
        'target.display_name target_display_name',
        'target.full_name target_full_name',
    ];
    $orderDue = !empty($columns['due_at']) ? 'CASE WHEN n.due_at IS NOT NULL AND n.due_at < NOW() THEN 0 ELSE 1 END, n.due_at ASC,' : '';
    $stmt = $pdo->query(
        'SELECT ' . implode(', ', $select) . '
         FROM admin_user_notes n
         INNER JOIN users target ON target.id = n.target_user_id
         WHERE n.status <> "resolved"
         ORDER BY ' . $orderDue . '
                  CASE n.priority WHEN "critical" THEN 1 WHEN "high" THEN 2 WHEN "normal" THEN 3 ELSE 4 END,
                  n.updated_at DESC
         LIMIT 12'
    );
    return [
        'alerts' => mg_queue_alert_summary($pdo, $actorId),
        'top_items' => array_map(static fn(array $row): array => [
            'id' => (string)$row['public_id'],
            'target' => (string)($row['target_display_name'] ?: $row['target_full_name'] ?: $row['target_email']),
            'category' => (string)$row['category'],
            'priority' => (string)$row['priority'],
            'status' => (string)$row['status'],
            'flag_state' => (string)$row['flag_state'],
            'due_at' => $row['due_at'] !== null ? (string)$row['due_at'] : null,
        ], $stmt->fetchAll(PDO::FETCH_ASSOC)),
        'score' => ['section' => 'Daily queue digest', 'score' => 10, 'max' => 10, 'status' => 'cleared'],
    ];
}
