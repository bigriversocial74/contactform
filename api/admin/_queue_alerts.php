<?php
declare(strict_types=1);

function mg_queue_alert_summary(PDO $pdo, int $actorId): array
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) total,
                SUM(status <> "resolved") active_total,
                SUM(status = "escalated") escalated_total,
                SUM(status = "waiting_on_merchant") waiting_on_merchant_total,
                SUM(status = "waiting_on_customer") waiting_on_customer_total,
                SUM(flag_state IN ("flagged","review")) review_total,
                SUM(due_at IS NOT NULL AND due_at < NOW() AND status <> "resolved") overdue_total,
                SUM(due_at >= CURDATE() AND due_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND status <> "resolved") due_today_total,
                SUM(due_at >= NOW() AND due_at < DATE_ADD(NOW(), INTERVAL 48 HOUR) AND status <> "resolved") due_soon_total,
                SUM(assigned_admin_user_id = ? AND status <> "resolved") assigned_to_me_total
         FROM admin_user_notes'
    );
    $stmt->execute([$actorId]);
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
        'score' => ['section' => 'Queue alerts', 'score' => 10, 'max' => 10, 'status' => 'cleared'],
    ];
}

function mg_queue_notice_exists(PDO $pdo, ?int $noteId, string $type): bool
{
    if ($noteId === null) {
        $stmt = $pdo->prepare('SELECT id FROM admin_queue_notifications WHERE notification_type = ? AND created_at >= CURDATE() LIMIT 1');
        $stmt->execute([$type]);
        return (bool)$stmt->fetch();
    }
    $stmt = $pdo->prepare('SELECT id FROM admin_queue_notifications WHERE note_id = ? AND notification_type = ? AND created_at >= CURDATE() LIMIT 1');
    $stmt->execute([$noteId, $type]);
    return (bool)$stmt->fetch();
}

function mg_queue_notice_create(PDO $pdo, array $data): void
{
    $noteId = isset($data['note_id']) ? (int)$data['note_id'] : null;
    $type = (string)$data['notification_type'];
    if (mg_queue_notice_exists($pdo, $noteId, $type)) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO admin_queue_notifications
         (public_id,note_id,target_user_id,assigned_admin_user_id,actor_user_id,notification_type,severity,title,message,metadata_json,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,NOW())'
    );
    $stmt->execute([
        mg_public_uuid(),
        $noteId,
        $data['target_user_id'] ?? null,
        $data['assigned_admin_user_id'] ?? null,
        $data['actor_user_id'] ?? null,
        $type,
        $data['severity'] ?? 'info',
        $data['title'],
        $data['message'],
        isset($data['metadata']) ? json_encode($data['metadata'], JSON_UNESCAPED_SLASHES) : null,
    ]);
}

function mg_queue_seed_due_notices(PDO $pdo, int $actorId): int
{
    $stmt = $pdo->query(
        'SELECT id, public_id, target_user_id, assigned_admin_user_id, due_at,
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
            'target_user_id' => (int)$note['target_user_id'],
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
    $stmt = $pdo->query(
        'SELECT n.public_id, n.category, n.priority, n.status, n.flag_state, n.due_at,
                target.email target_email, target.display_name target_display_name, target.full_name target_full_name
         FROM admin_user_notes n
         INNER JOIN users target ON target.id = n.target_user_id
         WHERE n.status <> "resolved"
         ORDER BY CASE WHEN n.due_at IS NOT NULL AND n.due_at < NOW() THEN 0 ELSE 1 END,
                  CASE n.priority WHEN "critical" THEN 1 WHEN "high" THEN 2 WHEN "normal" THEN 3 ELSE 4 END,
                  n.due_at ASC, n.updated_at DESC
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
