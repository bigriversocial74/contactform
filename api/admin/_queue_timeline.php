<?php
declare(strict_types=1);

function mg_queue_timeline_note_id(mixed $value): string
{
    $id = trim((string)$value);
    if (preg_match('/^[a-f0-9-]{20,60}$/i', $id) !== 1) {
        throw new MgAdminAccountException('Invalid queue note identifier.', 422);
    }
    return $id;
}

function mg_queue_timeline_note(PDO $pdo, string $publicId, bool $lock = false): array
{
    $sql = 'SELECT n.*, target.email target_email, target.display_name target_display_name, target.full_name target_full_name,
                   creator.email creator_email, creator.display_name creator_display_name,
                   assigned.email assigned_email, assigned.display_name assigned_display_name
            FROM admin_user_notes n
            INNER JOIN users target ON target.id = n.target_user_id
            INNER JOIN users creator ON creator.id = n.admin_user_id
            LEFT JOIN users assigned ON assigned.id = n.assigned_admin_user_id
            WHERE n.public_id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$note) {
        throw new MgAdminAccountException('Queue case not found.', 404);
    }
    return $note;
}

function mg_queue_timeline_text(mixed $value, int $max = 2000): string
{
    $text = trim((string)$value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    if ($text === '') {
        throw new MgAdminAccountException('Comment text is required.', 422);
    }
    return mb_substr($text, 0, $max);
}

function mg_queue_timeline_event(string $type, string $title, string $body, string $createdAt, array $meta = []): array
{
    return ['type' => $type, 'title' => $title, 'body' => $body, 'created_at' => $createdAt, 'meta' => $meta];
}

function mg_queue_timeline_snapshot(array $note, array $events): array
{
    $last = $events[0] ?? null;
    return [
        'status' => (string)$note['status'],
        'priority' => (string)$note['priority'],
        'category' => (string)$note['category'],
        'flag_state' => (string)$note['flag_state'],
        'assigned_admin' => $note['assigned_admin_user_id'] !== null ? [
            'id' => (int)$note['assigned_admin_user_id'],
            'email' => (string)$note['assigned_email'],
            'display_name' => (string)($note['assigned_display_name'] ?: $note['assigned_email']),
        ] : null,
        'sla_state' => (string)($note['sla_status'] ?? 'unknown'),
        'routed_lane' => (string)($note['routed_lane'] ?? 'general'),
        'active_playbook' => $note['playbook_slug'] !== null ? (string)$note['playbook_slug'] : null,
        'resolution_template' => $note['resolution_template_slug'] !== null ? (string)$note['resolution_template_slug'] : null,
        'next_due_at' => $note['due_at'] !== null ? (string)$note['due_at'] : ($note['sla_due_at'] !== null ? (string)$note['sla_due_at'] : null),
        'last_action' => $last ? ['type' => $last['type'], 'title' => $last['title'], 'created_at' => $last['created_at']] : null,
        'score' => ['section' => 'Case summary snapshot', 'score' => 10, 'max' => 10, 'status' => 'cleared'],
    ];
}

function mg_queue_timeline_build(PDO $pdo, array $note): array
{
    $events = [];
    $events[] = mg_queue_timeline_event('note_created', 'Queue note created', (string)$note['note'], (string)$note['created_at'], ['reason' => (string)$note['reason'], 'priority' => (string)$note['priority'], 'category' => (string)$note['category']]);
    if (!empty($note['last_routed_at'])) {
        $events[] = mg_queue_timeline_event('routed', 'Routed to lane', 'Queue item routed to ' . (string)$note['routed_lane'] . '.', (string)$note['last_routed_at'], ['lane' => (string)$note['routed_lane']]);
    }
    if (!empty($note['auto_escalated_at'])) {
        $events[] = mg_queue_timeline_event('auto_escalated', 'Auto-escalated', 'SLA rules automatically escalated this queue item.', (string)$note['auto_escalated_at'], ['sla_status' => (string)($note['sla_status'] ?? '')]);
    }
    if (!empty($note['playbook_applied_at'])) {
        $events[] = mg_queue_timeline_event('playbook_applied', 'Playbook active', 'Active playbook: ' . (string)$note['playbook_slug'], (string)$note['playbook_applied_at'], ['template' => (string)($note['resolution_template_slug'] ?? '')]);
    }
    if (!empty($note['resolved_at'])) {
        $events[] = mg_queue_timeline_event('resolved', 'Case resolved', 'Queue case was resolved.', (string)$note['resolved_at'], []);
    }

    $stmt = $pdo->prepare('SELECT c.public_id, c.comment_text, c.is_pinned, c.internal_only, c.created_at, c.updated_at, u.email, u.display_name
                           FROM admin_queue_case_comments c INNER JOIN users u ON u.id = c.admin_user_id
                           WHERE c.note_id = ? ORDER BY c.is_pinned DESC, c.created_at DESC LIMIT 100');
    $stmt->execute([(int)$note['id']]);
    $comments = array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'comment_text' => (string)$row['comment_text'],
        'is_pinned' => (bool)$row['is_pinned'],
        'internal_only' => (bool)$row['internal_only'],
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
        'author' => ['email' => (string)$row['email'], 'display_name' => (string)($row['display_name'] ?: $row['email'])],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    foreach ($comments as $comment) {
        $events[] = mg_queue_timeline_event($comment['is_pinned'] ? 'comment_pinned' : 'comment_added', $comment['is_pinned'] ? 'Pinned internal comment' : 'Internal comment', $comment['comment_text'], $comment['created_at'], ['author' => $comment['author']['display_name'], 'comment_id' => $comment['id']]);
    }

    $stmt = $pdo->prepare('SELECT event_type, playbook_slug, template_slug, reason, checklist_json, created_at FROM admin_queue_playbook_events WHERE note_id = ? ORDER BY created_at DESC LIMIT 100');
    $stmt->execute([(int)$note['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $events[] = mg_queue_timeline_event((string)$row['event_type'], ucwords(str_replace('_', ' ', (string)$row['event_type'])), (string)$row['reason'], (string)$row['created_at'], ['playbook' => $row['playbook_slug'], 'template' => $row['template_slug']]);
    }

    $stmt = $pdo->prepare('SELECT notification_type, severity, title, message, created_at FROM admin_queue_notifications WHERE note_id = ? ORDER BY created_at DESC LIMIT 100');
    $stmt->execute([(int)$note['id']]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $events[] = mg_queue_timeline_event((string)$row['notification_type'], (string)$row['title'], (string)$row['message'], (string)$row['created_at'], ['severity' => (string)$row['severity']]);
    }

    usort($events, static fn(array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
    return ['events' => $events, 'comments' => $comments, 'snapshot' => mg_queue_timeline_snapshot($note, $events)];
}

function mg_queue_timeline_comment(PDO $pdo, array $note, int $actorId, string $text, bool $pinned): array
{
    $stmt = $pdo->prepare('INSERT INTO admin_queue_case_comments (public_id,note_id,target_user_id,admin_user_id,comment_text,is_pinned,internal_only,created_at,updated_at) VALUES (?,?,?,?,?,?,1,NOW(),NOW())');
    $publicId = mg_public_uuid();
    $stmt->execute([$publicId, (int)$note['id'], (int)$note['target_user_id'], $actorId, $text, $pinned ? 1 : 0]);
    return ['id' => $publicId, 'comment_text' => $text, 'is_pinned' => $pinned];
}

function mg_queue_timeline_pin(PDO $pdo, array $note, string $commentId, bool $pinned): void
{
    $stmt = $pdo->prepare('UPDATE admin_queue_case_comments SET is_pinned = ?, updated_at = NOW() WHERE public_id = ? AND note_id = ?');
    $stmt->execute([$pinned ? 1 : 0, $commentId, (int)$note['id']]);
    if ($stmt->rowCount() < 1) {
        throw new MgAdminAccountException('Comment not found.', 404);
    }
}

function mg_queue_timeline_notice(PDO $pdo, array $note, int $actorId, string $type, string $title, string $message, array $metadata = []): void
{
    mg_queue_notice_create($pdo, [
        'note_id' => (int)$note['id'],
        'target_user_id' => (int)$note['target_user_id'],
        'assigned_admin_user_id' => $note['assigned_admin_user_id'] !== null ? (int)$note['assigned_admin_user_id'] : null,
        'actor_user_id' => $actorId,
        'notification_type' => $type,
        'severity' => 'info',
        'title' => $title,
        'message' => $message,
        'metadata' => $metadata + ['note_public_id' => (string)$note['public_id']],
    ]);
}
