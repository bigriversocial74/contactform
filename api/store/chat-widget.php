<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_runtime.php';
require_once __DIR__ . '/_canvas_messaging.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();

function mg_store_chat_profile(PDO $pdo, int $userId): array
{
    $profile = [
        'user_id' => $userId,
        'name' => 'Merchant Store',
        'avatar_url' => null,
        'slug' => '',
    ];
    try {
        $stmt = $pdo->prepare('SELECT display_name,avatar_url,slug FROM public_profiles WHERE user_id=? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $profile['name'] = trim((string)($row['display_name'] ?? '')) ?: $profile['name'];
            $profile['avatar_url'] = mg_store_avatar_url($row['avatar_url'] ?? null);
            $profile['slug'] = trim((string)($row['slug'] ?? ''));
        }
    } catch (Throwable) {}
    return $profile;
}

function mg_store_chat_thread(PDO $pdo, int $customerUserId, int $merchantUserId): ?array
{
    $conversationKey = mg_store_canvas_conversation_key($merchantUserId, $customerUserId);
    $stmt = $pdo->prepare(
        'SELECT mt.id,mt.public_id,mt.subject,mt.conversation_key,mtp.last_read_at
         FROM message_threads mt
         INNER JOIN message_thread_participants mtp ON mtp.thread_id=mt.id AND mtp.user_id=?
         WHERE mt.conversation_key=?
         ORDER BY mt.id ASC LIMIT 1'
    );
    $stmt->execute([$customerUserId, $conversationKey]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);
    return $thread ?: null;
}

function mg_store_chat_messages(PDO $pdo, array $thread, int $viewerId): array
{
    $stmt = $pdo->prepare(
        "SELECT m.public_id,m.body,m.created_at,m.sender_user_id,m.recipient_user_id,m.source_type,u.display_name,u.full_name,u.email
         FROM messages m
         INNER JOIN users u ON u.id=m.sender_user_id
         WHERE m.thread_id=? AND m.moderation_status NOT IN ('hidden','removed')
         ORDER BY m.created_at DESC,m.id DESC LIMIT 20"
    );
    $stmt->execute([(int)$thread['id']]);
    $rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    return array_map(static function(array $row) use ($viewerId): array {
        $name = trim((string)($row['display_name'] ?? $row['full_name'] ?? ''));
        if ($name === '') $name = trim((string)($row['email'] ?? 'Microgifter user')) ?: 'Microgifter user';
        return [
            'id' => (string)$row['public_id'],
            'body' => (string)$row['body'],
            'created_at' => (string)$row['created_at'],
            'sender_id' => (int)$row['sender_user_id'],
            'recipient_id' => $row['recipient_user_id'] !== null ? (int)$row['recipient_user_id'] : null,
            'sender_name' => $name,
            'mine' => (int)$row['sender_user_id'] === $viewerId,
            'source_type' => (string)($row['source_type'] ?? 'store_canvas_direct'),
        ];
    }, $rows);
}

try {
    mg_rate_limit('store.chat_widget', 'user:' . (int)$user['id'], 240, 60);
    $schemaReady = mg_store_runtime_schema_ready($pdo);
    $active = $schemaReady ? mg_store_runtime_active_session_for_customer($pdo, (int)$user['id']) : null;
    if (!$active) {
        mg_ok(['active' => false, 'schema_ready' => $schemaReady]);
        return;
    }

    $merchantUserId = (int)$active['merchant_user_id'];
    $thread = mg_store_chat_thread($pdo, (int)$user['id'], $merchantUserId);
    $messages = $thread ? mg_store_chat_messages($pdo, $thread, (int)$user['id']) : [];
    $lastReadAt = $thread ? trim((string)($thread['last_read_at'] ?? '')) : '';
    $unread = 0;
    if ($thread) {
        $unreadStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM messages
             WHERE thread_id=? AND sender_user_id<>? AND moderation_status NOT IN ('hidden','removed')
               AND (?='' OR created_at > ?)"
        );
        $unreadStmt->execute([(int)$thread['id'], (int)$user['id'], $lastReadAt, $lastReadAt]);
        $unread = (int)$unreadStmt->fetchColumn();
    }

    mg_ok([
        'active' => true,
        'schema_ready' => true,
        'session' => mg_store_project_session($active),
        'merchant' => mg_store_chat_profile($pdo, $merchantUserId),
        'thread' => $thread ? [
            'id' => (string)$thread['public_id'],
            'subject' => (string)($thread['subject'] ?? 'Store Canvas'),
            'conversation_key' => (string)$thread['conversation_key'],
            'unread' => $unread,
        ] : null,
        'messages' => $messages,
        'can_reply' => $thread !== null,
        'checked_at' => date('Y-m-d H:i:s'),
    ]);
} catch (Throwable $error) {
    mg_security_log('error', 'store.chat_widget_failed', 'Store chat widget status failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], (int)$user['id']);
    mg_fail('Unable to load store chat.', 500);
}
