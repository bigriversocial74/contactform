<?php
declare(strict_types=1);

function mg_personal_agent_thread_title_from_message(string $message): string
{
    $message = mg_personal_agent_text($message, 500);
    if ($message === '') return 'New chat';
    if (function_exists('mg_personal_agent_message_has_secret_request') && mg_personal_agent_message_has_secret_request($message)) {
        return 'Private account question';
    }
    $message = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/iu', '[private detail]', $message) ?? $message;
    $message = preg_replace('/\b\d{4,}\b/u', '••••', $message) ?? $message;
    $message = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
    $title = mb_substr($message, 0, 72);
    if (mb_strlen($message) > 72) $title = rtrim($title, " \t\n\r\0\x0B.,;:-") . '…';
    return $title !== '' ? $title : 'New chat';
}

function mg_personal_agent_thread_summary(array $row): array
{
    return [
        'id' => (string) ($row['public_id'] ?? ''),
        'title' => (string) ($row['title'] ?? 'New chat'),
        'context_type' => (string) ($row['selected_context_type'] ?? 'none'),
        'context_id' => (string) ($row['selected_context_public_id'] ?? ''),
        'message_count' => (int) ($row['message_count'] ?? 0),
        'preview' => mg_personal_agent_text($row['preview'] ?? '', 180),
        'last_message_at' => $row['last_message_at'] ?: null,
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

function mg_personal_agent_threads(PDO $pdo, int $userId, int $limit = 120): array
{
    mg_personal_agent_require_schema($pdo);
    $limit = max(1, min(250, $limit));
    $stmt = $pdo->prepare("SELECT t.public_id,t.title,t.selected_context_type,t.selected_context_public_id,t.last_message_at,t.created_at,t.updated_at,
        (SELECT COUNT(*) FROM user_agent_messages m WHERE m.thread_id=t.id AND m.owner_user_id=t.owner_user_id) message_count,
        (SELECT m.body FROM user_agent_messages m WHERE m.thread_id=t.id AND m.owner_user_id=t.owner_user_id ORDER BY m.id DESC LIMIT 1) preview
        FROM user_agent_threads t
        WHERE t.owner_user_id=? AND t.cleared_at IS NULL
        ORDER BY COALESCE(t.last_message_at,t.updated_at,t.created_at) DESC,t.id DESC
        LIMIT {$limit}");
    $stmt->execute([$userId]);
    return array_map('mg_personal_agent_thread_summary', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_personal_agent_thread_detail(PDO $pdo, int $userId, string $threadPublicId): array
{
    mg_personal_agent_require_schema($pdo);
    $threadPublicId = mg_personal_agent_text($threadPublicId, 80);
    if ($threadPublicId === '') throw new InvalidArgumentException('Chat is required.');
    $stmt = $pdo->prepare("SELECT id,public_id,title,selected_context_type,selected_context_public_id,last_message_at,created_at,updated_at,
        (SELECT COUNT(*) FROM user_agent_messages m WHERE m.thread_id=user_agent_threads.id AND m.owner_user_id=user_agent_threads.owner_user_id) message_count,
        (SELECT m.body FROM user_agent_messages m WHERE m.thread_id=user_agent_threads.id AND m.owner_user_id=user_agent_threads.owner_user_id ORDER BY m.id DESC LIMIT 1) preview
        FROM user_agent_threads WHERE owner_user_id=? AND public_id=? AND cleared_at IS NULL LIMIT 1");
    $stmt->execute([$userId, $threadPublicId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Chat not found.');
    return [
        'thread' => mg_personal_agent_thread_summary($row),
        'messages' => mg_personal_agent_messages($pdo, $userId, (int) $row['id'], 60),
    ];
}

function mg_personal_agent_create_thread(PDO $pdo, int $userId): array
{
    mg_personal_agent_require_schema($pdo);
    $publicId = mg_public_uuid();
    $pdo->prepare("INSERT INTO user_agent_threads
        (public_id,owner_user_id,title,selected_context_type,selected_context_public_id,last_message_at,cleared_at,created_at,updated_at)
        VALUES (?,?,'New chat','none',NULL,NULL,NULL,NOW(),NOW())")
        ->execute([$publicId, $userId]);
    mg_audit('user_agent.thread_created', 'user_agent_thread', ['thread_id' => $publicId], $userId);
    return mg_personal_agent_thread_detail($pdo, $userId, $publicId)['thread'];
}

function mg_personal_agent_autotitle_thread(PDO $pdo, int $userId, string $threadPublicId, string $message): array
{
    $threadPublicId = mg_personal_agent_text($threadPublicId, 80);
    if ($threadPublicId === '') throw new RuntimeException('Chat not found.');
    $title = mg_personal_agent_thread_title_from_message($message);
    $stmt = $pdo->prepare("UPDATE user_agent_threads SET title=?,updated_at=NOW()
        WHERE owner_user_id=? AND public_id=? AND cleared_at IS NULL
          AND title IN ('New chat','Personal gifting conversation')");
    $stmt->execute([$title, $userId, $threadPublicId]);
    return mg_personal_agent_thread_detail($pdo, $userId, $threadPublicId)['thread'];
}

function mg_personal_agent_delete_thread(PDO $pdo, int $userId, string $threadPublicId): array
{
    mg_personal_agent_require_schema($pdo);
    $threadPublicId = mg_personal_agent_text($threadPublicId, 80);
    if ($threadPublicId === '') throw new InvalidArgumentException('Chat is required.');
    $stmt = $pdo->prepare('DELETE FROM user_agent_threads WHERE owner_user_id=? AND public_id=?');
    $stmt->execute([$userId, $threadPublicId]);
    if ($stmt->rowCount() !== 1) throw new RuntimeException('Chat not found.');
    mg_audit('user_agent.thread_deleted', 'user_agent_thread', ['thread_id' => $threadPublicId], $userId);
    return ['deleted' => true, 'thread_id' => $threadPublicId];
}
