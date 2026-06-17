<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

mg_require_method('POST');
$user = mg_require_permission('notification.view');
$input = mg_input();
mg_require_csrf_for_write($input);
$id = trim((string) ($input['id'] ?? 'all'));

if ($id === 'all') {
    $stmt = mg_db()->prepare('UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE user_id = ?');
    $stmt->execute([(int) $user['id']]);
} else {
    if (strlen($id) !== 36 || !preg_match('/^[a-f0-9-]{36}$/i', $id)) {
        mg_fail('Invalid notification identifier.', 422);
    }
    $stmt = mg_db()->prepare('UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE public_id = ? AND user_id = ?');
    $stmt->execute([strtolower($id), (int) $user['id']]);
}

$countStmt = mg_db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL');
$countStmt->execute([(int) $user['id']]);
mg_ok(['unread_count' => (int) $countStmt->fetchColumn()], 'Notifications updated.');
