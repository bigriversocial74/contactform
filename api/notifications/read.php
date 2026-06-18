<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('POST');
$user = mg_require_permission('notification.view');
$input = mg_input();
mg_require_csrf_for_write($input);
$id = trim((string) ($input['id'] ?? 'all'));
$pdo = mg_db();

if ($id === 'all') {
    $stmt = $pdo->prepare('UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE user_id = ?');
    $stmt->execute([(int) $user['id']]);
} else {
    if (strlen($id) !== 36 || !preg_match('/^[a-f0-9-]{36}$/i', $id)) {
        mg_fail('Invalid notification identifier.', 422);
    }
    $stmt = $pdo->prepare('UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE public_id = ? AND user_id = ?');
    $stmt->execute([strtolower($id), (int) $user['id']]);
}

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
mg_ok(['unread_count' => (int)$countStmt->fetchColumn()], 'Notifications updated.');
