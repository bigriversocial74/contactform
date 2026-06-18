<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
mg_require_method('POST');
$user = mg_require_permission('gift.message.send');
$input = mg_input();
mg_require_csrf_for_write($input);
$threadId = trim((string) ($input['thread_id'] ?? ''));
$action = trim((string) ($input['action'] ?? ''));
if (strlen($threadId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/i', $threadId)) {
    mg_fail('Invalid thread identifier.', 422);
}
$pdo = mg_db();
$stmt = $pdo->prepare('SELECT mt.id FROM message_threads mt INNER JOIN message_thread_participants mtp ON mtp.thread_id=mt.id WHERE mt.public_id=? AND mtp.user_id=? LIMIT 1');
$stmt->execute([strtolower($threadId), (int) $user['id']]);
$dbId = $stmt->fetchColumn();
if (!$dbId) {
    mg_fail('Thread not found.', 404);
}
$pdo->prepare('INSERT IGNORE INTO message_thread_settings (thread_id,user_id,created_at,updated_at) VALUES (?,?,NOW(),NOW())')->execute([(int) $dbId, (int) $user['id']]);
if ($action === 'pin') {
    $pdo->prepare('UPDATE message_thread_settings SET pinned_at=IF(pinned_at IS NULL,NOW(),NULL),updated_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int) $dbId, (int) $user['id']]);
} elseif ($action === 'archive') {
    $pdo->prepare('UPDATE message_thread_settings SET archived_at=NOW(),updated_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int) $dbId, (int) $user['id']]);
} elseif ($action === 'unarchive') {
    $pdo->prepare('UPDATE message_thread_settings SET archived_at=NULL,updated_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int) $dbId, (int) $user['id']]);
} elseif ($action === 'mute') {
    $hours = max(1, min(720, (int) ($input['hours'] ?? 24)));
    $pdo->prepare('UPDATE message_thread_settings SET muted_until=DATE_ADD(NOW(),INTERVAL ? HOUR),updated_at=NOW() WHERE thread_id=? AND user_id=?')->execute([$hours, (int) $dbId, (int) $user['id']]);
} elseif ($action === 'unmute') {
    $pdo->prepare('UPDATE message_thread_settings SET muted_until=NULL,updated_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int) $dbId, (int) $user['id']]);
} else {
    mg_fail('Invalid thread action.', 422);
}
mg_audit('message.thread_setting_updated', 'message_thread', ['thread_id' => $threadId, 'action' => $action], (int) $user['id']);
mg_ok(['thread_id' => $threadId, 'action' => $action], 'Thread updated.');
