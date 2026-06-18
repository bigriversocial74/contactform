<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('GET');
$user = mg_require_permission('notification.view');
$unreadOnly = (string)($_GET['unread'] ?? '') === '1';
$limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));

// User-scope compatibility contract: WHERE user_id = ?
$where = "n.user_id=? AND COALESCE(np.in_app_enabled,1)=1 AND COALESCE(np.digest_mode,'immediate')<>'off'";
if ($unreadOnly) $where .= ' AND n.read_at IS NULL';

$pdo = mg_db();
$stmt = $pdo->prepare(
    'SELECT n.public_id,n.type,n.title,n.body,n.action_url,n.read_at,n.created_at
     FROM notifications n
     LEFT JOIN notification_preferences np
       ON np.user_id=n.user_id AND np.notification_type=n.type
     WHERE ' . $where . '
     ORDER BY n.created_at DESC,n.id DESC
     LIMIT ' . $limit
);
$stmt->execute([(int)$user['id']]);
$notifications = array_map(static fn(array $row): array => [
    'id'=>(string)$row['public_id'],
    'type'=>(string)$row['type'],
    'title'=>(string)$row['title'],
    'body'=>(string)($row['body'] ?? ''),
    'action_url'=>$row['action_url'] !== null ? (string)$row['action_url'] : null,
    'read'=>$row['read_at'] !== null,
    'read_at'=>$row['read_at'] ?? null,
    'created_at'=>$row['created_at'] ?? null,
], $stmt->fetchAll());

$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM notifications n
     LEFT JOIN notification_preferences np
       ON np.user_id=n.user_id AND np.notification_type=n.type
     WHERE n.user_id=? AND n.read_at IS NULL
       AND COALESCE(np.in_app_enabled,1)=1
       AND COALESCE(np.digest_mode,'immediate')<>'off'"
);
$countStmt->execute([(int)$user['id']]);

mg_ok(['notifications'=>$notifications,'unread_count'=>(int)$countStmt->fetchColumn()]);
