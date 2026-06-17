<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/gifts/_gift.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission('gift.message.send');
$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '' || strlen($id) !== 36 || !preg_match('/^[a-f0-9-]{36}$/i', $id)) {
    mg_fail('Invalid thread identifier.', 422);
}

$stmt = mg_db()->prepare(
    'SELECT mt.id, mt.public_id, mt.subject, mt.gift_id, g.public_id AS gift_public_id, g.title AS gift_title
     FROM message_threads mt
     INNER JOIN message_thread_participants mtp ON mtp.thread_id = mt.id
     LEFT JOIN gifts g ON g.id = mt.gift_id
     WHERE mt.public_id = ? AND mtp.user_id = ? LIMIT 1'
);
$stmt->execute([strtolower($id), (int) $user['id']]);
$thread = $stmt->fetch();
if (!$thread) {
    mg_fail('Thread not found.', 404);
}

if ($method === 'GET') {
    $messagesStmt = mg_db()->prepare(
        'SELECT m.public_id, m.body, m.created_at, m.sender_user_id,
                u.display_name AS sender_name, u.full_name AS sender_full_name
         FROM messages m
         INNER JOIN users u ON u.id = m.sender_user_id
         WHERE m.thread_id = ?
         ORDER BY m.created_at ASC, m.id ASC'
    );
    $messagesStmt->execute([(int) $thread['id']]);
    $messages = array_map(static fn(array $row): array => [
        'id' => (string) $row['public_id'],
        'body' => (string) $row['body'],
        'sender_id' => (int) $row['sender_user_id'],
        'sender_name' => trim((string) ($row['sender_name'] ?? $row['sender_full_name'] ?? 'Microgifter user')),
        'created_at' => $row['created_at'] ?? null,
        'mine' => (int) $row['sender_user_id'] === (int) $user['id'],
    ], $messagesStmt->fetchAll());

    $readStmt = mg_db()->prepare('UPDATE message_thread_participants SET last_read_at = NOW() WHERE thread_id = ? AND user_id = ?');
    $readStmt->execute([(int) $thread['id'], (int) $user['id']]);

    mg_ok(['thread' => [
        'id' => (string) $thread['public_id'],
        'subject' => (string) ($thread['subject'] ?? $thread['gift_title'] ?? 'Gift conversation'),
        'gift_id' => $thread['gift_public_id'] !== null ? (string) $thread['gift_public_id'] : null,
        'messages' => $messages,
    ]]);
}

mg_fail('Method not allowed.', 405);
