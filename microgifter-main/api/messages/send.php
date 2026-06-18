<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/gifts/_gift.php';
require_once dirname(__DIR__) . '/pppm/_activity.php';

mg_require_method('POST');
$user = mg_require_permission('gift.message.send');
$input = mg_input();
mg_require_csrf_for_write($input);
$body = mg_message_validate_body($input['body'] ?? '');
$itemId = trim((string) ($input['gift_id'] ?? ''));
$itemSource = trim((string) ($input['item_source'] ?? 'legacy'));
$threadId = trim((string) ($input['thread_id'] ?? ''));
$pdo = mg_db();
$gift = null;
$pppm = null;

try {
    $pdo->beginTransaction();

    if ($threadId !== '') {
        if (strlen($threadId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/i', $threadId)) {
            mg_fail('Invalid thread identifier.', 422);
        }
        $threadStmt = $pdo->prepare(
            'SELECT mt.id, mt.public_id, mt.gift_id, mt.pppm_item_id
             FROM message_threads mt
             INNER JOIN message_thread_participants mtp ON mtp.thread_id = mt.id
             WHERE mt.public_id = ? AND mtp.user_id = ? LIMIT 1 FOR UPDATE'
        );
        $threadStmt->execute([strtolower($threadId), (int) $user['id']]);
        $thread = $threadStmt->fetch();
        if (!$thread) {
            mg_fail('Thread not found.', 404);
        }
        if (!empty($thread['gift_id'])) {
            $giftStmt = $pdo->prepare('SELECT * FROM gifts WHERE id = ? LIMIT 1');
            $giftStmt->execute([(int) $thread['gift_id']]);
            $gift = $giftStmt->fetch() ?: null;
        }
        if (!empty($thread['pppm_item_id'])) {
            $pppmStmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id = ? LIMIT 1');
            $pppmStmt->execute([(int) $thread['pppm_item_id']]);
            $pppm = $pppmStmt->fetch() ?: null;
        }
    } elseif ($itemSource === 'pppm') {
        if ($itemId === '' || strlen($itemId) > 32 || !preg_match('/^(GFT|PPPM)-[A-Z0-9-]+$/', $itemId)) {
            mg_fail('Invalid PPPM item identifier.', 422);
        }
        $pppm = mg_pppm_activity_find((int) $user['id'], $itemId);
        if (!$pppm) {
            mg_fail('PPPM item not found.', 404);
        }

        $threadLookup = $pdo->prepare(
            'SELECT mt.id, mt.public_id, mt.gift_id, mt.pppm_item_id
             FROM message_threads mt
             INNER JOIN message_thread_participants mtp ON mtp.thread_id = mt.id
             WHERE mt.pppm_item_id = ? AND mtp.user_id = ?
             ORDER BY mt.updated_at DESC LIMIT 1 FOR UPDATE'
        );
        $threadLookup->execute([(int) $pppm['id'], (int) $user['id']]);
        $thread = $threadLookup->fetch();

        if (!$thread) {
            $threadPublicId = mg_public_uuid();
            $subject = mb_substr((string) $pppm['title_snapshot'], 0, 160);
            $insertThread = $pdo->prepare(
                'INSERT INTO message_threads
                 (public_id, gift_id, pppm_item_id, created_by_user_id, subject, created_at, updated_at)
                 VALUES (?, NULL, ?, ?, ?, NOW(), NOW())'
            );
            $insertThread->execute([$threadPublicId, (int) $pppm['id'], (int) $user['id'], $subject]);
            $threadDbId = (int) $pdo->lastInsertId();

            $participant = $pdo->prepare(
                'INSERT IGNORE INTO message_thread_participants (thread_id, user_id, joined_at)
                 VALUES (?, ?, NOW())'
            );
            foreach (['issuer_user_id', 'owner_user_id', 'recipient_user_id'] as $participantField) {
                if (!empty($pppm[$participantField])) {
                    $participant->execute([$threadDbId, (int) $pppm[$participantField]]);
                }
            }
            $thread = [
                'id' => $threadDbId,
                'public_id' => $threadPublicId,
                'gift_id' => null,
                'pppm_item_id' => (int) $pppm['id'],
            ];
        }
    } else {
        $giftPublicId = mg_gift_request_id(['id' => $itemId]);
        $gift = mg_gift_require_accessible((int) $user['id'], $giftPublicId);
        $threadLookup = $pdo->prepare(
            'SELECT mt.id, mt.public_id, mt.gift_id, mt.pppm_item_id
             FROM message_threads mt
             INNER JOIN message_thread_participants mtp ON mtp.thread_id = mt.id
             WHERE mt.gift_id = ? AND mtp.user_id = ?
             ORDER BY mt.updated_at DESC LIMIT 1 FOR UPDATE'
        );
        $threadLookup->execute([(int) $gift['id'], (int) $user['id']]);
        $thread = $threadLookup->fetch();

        if (!$thread) {
            $threadPublicId = mg_public_uuid();
            $subject = mb_substr((string) $gift['title'], 0, 160);
            $insertThread = $pdo->prepare(
                'INSERT INTO message_threads
                 (public_id, gift_id, pppm_item_id, created_by_user_id, subject, created_at, updated_at)
                 VALUES (?, ?, NULL, ?, ?, NOW(), NOW())'
            );
            $insertThread->execute([$threadPublicId, (int) $gift['id'], (int) $user['id'], $subject]);
            $threadDbId = (int) $pdo->lastInsertId();

            $participant = $pdo->prepare(
                'INSERT IGNORE INTO message_thread_participants (thread_id, user_id, joined_at)
                 VALUES (?, ?, NOW())'
            );
            $participant->execute([$threadDbId, (int) $gift['sender_user_id']]);
            if (!empty($gift['recipient_user_id'])) {
                $participant->execute([$threadDbId, (int) $gift['recipient_user_id']]);
            }
            $thread = [
                'id' => $threadDbId,
                'public_id' => $threadPublicId,
                'gift_id' => (int) $gift['id'],
                'pppm_item_id' => null,
            ];
        }
    }

    $messagePublicId = mg_public_uuid();
    $messageStmt = $pdo->prepare(
        'INSERT INTO messages (public_id, thread_id, sender_user_id, body, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $messageStmt->execute([$messagePublicId, (int) $thread['id'], (int) $user['id'], $body]);

    $pdo->prepare('UPDATE message_threads SET updated_at = NOW() WHERE id = ?')->execute([(int) $thread['id']]);
    $pdo->prepare('UPDATE message_thread_participants SET last_read_at = NOW() WHERE thread_id = ? AND user_id = ?')
        ->execute([(int) $thread['id'], (int) $user['id']]);

    $participantStmt = $pdo->prepare('SELECT user_id FROM message_thread_participants WHERE thread_id = ? AND user_id <> ?');
    $participantStmt->execute([(int) $thread['id'], (int) $user['id']]);
    $recipientIds = array_map('intval', array_column($participantStmt->fetchAll(), 'user_id'));

    $notificationStmt = $pdo->prepare(
        'INSERT INTO notifications
         (public_id, user_id, type, title, body, action_url, gift_id, pppm_item_id, thread_id, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    foreach ($recipientIds as $recipientId) {
        $notificationStmt->execute([
            mg_public_uuid(),
            $recipientId,
            'message',
            $pppm ? 'New item message' : 'New gift message',
            mb_substr($body, 0, 500),
            '/inbox.php?thread=' . rawurlencode((string) $thread['public_id']),
            !empty($thread['gift_id']) ? (int) $thread['gift_id'] : null,
            !empty($thread['pppm_item_id']) ? (int) $thread['pppm_item_id'] : null,
            (int) $thread['id'],
        ]);
    }

    if (is_array($gift) && !empty($gift['id'])) {
        mg_gift_event($pdo, (int) $gift['id'], (int) $user['id'], 'message', ['thread_id' => $thread['public_id']]);
    }
    if (is_array($pppm) && !empty($pppm['id'])) {
        $pdo->prepare(
            'INSERT INTO pppm_item_events
             (pppm_item_id, actor_user_id, event_type, from_status, to_status, metadata_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            (int) $pppm['id'],
            (int) $user['id'],
            'message',
            (string) $pppm['status'],
            (string) $pppm['status'],
            json_encode(['thread_id' => $thread['public_id']], JSON_UNESCAPED_SLASHES),
        ]);
    }

    $pdo->commit();
    mg_audit('message.sent', 'message_thread', ['thread_id' => $thread['public_id']], (int) $user['id']);
    mg_event('message.sent', ['thread_id' => $thread['public_id']], (int) $user['id']);
    mg_ok([
        'thread_id' => (string) $thread['public_id'],
        'message_id' => $messagePublicId,
    ], 'Message sent.', 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'message.send_failed', 'Message send failed.', [
        'exception_type' => get_class($e),
    ], (int) $user['id']);
    mg_fail('Unable to send message right now.', 500);
}
