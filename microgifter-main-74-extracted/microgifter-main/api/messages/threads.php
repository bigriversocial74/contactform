<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/gifts/_gift.php';

mg_require_method('GET');
$user = mg_require_permission('gift.message.send');
$limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));

$stmt = mg_db()->prepare(
    'SELECT mt.id, mt.public_id, mt.subject, mt.gift_id, mt.updated_at,
            g.public_id AS gift_public_id, g.title AS gift_title,
            latest.body AS latest_body, latest.created_at AS latest_created_at,
            sender.display_name AS latest_sender_name, sender.full_name AS latest_sender_full_name,
            mtp.last_read_at,
            CASE WHEN latest.created_at IS NOT NULL AND (mtp.last_read_at IS NULL OR latest.created_at > mtp.last_read_at)
                 AND latest.sender_user_id <> ? THEN 1 ELSE 0 END AS unread
     FROM message_thread_participants mtp
     INNER JOIN message_threads mt ON mt.id = mtp.thread_id
     LEFT JOIN gifts g ON g.id = mt.gift_id
     LEFT JOIN messages latest ON latest.id = (
       SELECT m2.id FROM messages m2 WHERE m2.thread_id = mt.id ORDER BY m2.created_at DESC, m2.id DESC LIMIT 1
     )
     LEFT JOIN users sender ON sender.id = latest.sender_user_id
     WHERE mtp.user_id = ?
     ORDER BY COALESCE(latest.created_at, mt.updated_at) DESC, mt.id DESC
     LIMIT ' . $limit
);
$stmt->execute([(int) $user['id'], (int) $user['id']]);

$threads = array_map(static fn(array $row): array => [
    'id' => (string) $row['public_id'],
    'subject' => (string) ($row['subject'] ?? $row['gift_title'] ?? 'Gift conversation'),
    'gift_id' => $row['gift_public_id'] !== null ? (string) $row['gift_public_id'] : null,
    'latest_message' => (string) ($row['latest_body'] ?? ''),
    'latest_sender' => trim((string) ($row['latest_sender_name'] ?? $row['latest_sender_full_name'] ?? '')),
    'latest_at' => $row['latest_created_at'] ?? $row['updated_at'] ?? null,
    'unread' => (bool) $row['unread'],
], $stmt->fetchAll());

$unreadCount = count(array_filter($threads, static fn(array $thread): bool => $thread['unread']));
mg_ok(['threads' => $threads, 'unread_count' => $unreadCount]);
