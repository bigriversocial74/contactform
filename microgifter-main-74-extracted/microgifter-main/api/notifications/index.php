<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
$user = mg_require_permission('notification.view');
$unreadOnly = (string) ($_GET['unread'] ?? '') === '1';
$limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));

$where = 'user_id = ?';
if ($unreadOnly) {
    $where .= ' AND read_at IS NULL';
}

$stmt = mg_db()->prepare(
    'SELECT public_id, type, title, body, action_url, read_at, created_at
     FROM notifications
     WHERE ' . $where . '
     ORDER BY created_at DESC, id DESC
     LIMIT ' . $limit
);
$stmt->execute([(int) $user['id']]);
$notifications = array_map(static fn(array $row): array => [
    'id' => (string) $row['public_id'],
    'type' => (string) $row['type'],
    'title' => (string) $row['title'],
    'body' => (string) ($row['body'] ?? ''),
    'action_url' => $row['action_url'] !== null ? (string) $row['action_url'] : null,
    'read' => $row['read_at'] !== null,
    'read_at' => $row['read_at'] ?? null,
    'created_at' => $row['created_at'] ?? null,
], $stmt->fetchAll());

$countStmt = mg_db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
$countStmt->execute([(int) $user['id']]);
$unreadCount = (int) $countStmt->fetchColumn();

mg_ok(['notifications' => $notifications, 'unread_count' => $unreadCount]);
