<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/gifts/_gift.php';

function mg_messages_source_type(?string $sourceType, ?string $conversationKey = null, ?string $pppmId = null): string
{
    $sourceType = trim((string)$sourceType);
    $conversationKey = trim((string)$conversationKey);
    if ($sourceType !== '' && $sourceType !== 'messaging') {
        return $sourceType;
    }
    if ($conversationKey !== '' && str_starts_with($conversationKey, 'store_canvas:')) {
        return 'store_canvas_direct';
    }
    if (trim((string)$pppmId) !== '') {
        return 'pppm_message';
    }
    return $sourceType !== '' ? $sourceType : 'messaging';
}

function mg_messages_source_label(string $sourceType): string
{
    return match ($sourceType) {
        'store_canvas_direct' => 'Store Canvas',
        'store_canvas_reply' => 'Store Canvas Reply',
        'action_center_follow_up' => 'Action Center Follow Up',
        'action_center' => 'Action Center',
        'pppm_message' => 'PPPM / IN-OUT Box',
        'messaging' => 'Messages',
        default => ucwords(str_replace(['_', '-'], ' ', $sourceType)),
    };
}

function mg_messages_source_system(string $sourceType): string
{
    if (str_starts_with($sourceType, 'store_canvas')) return 'store_canvas';
    if (str_starts_with($sourceType, 'action_center') || $sourceType === 'pppm_message') return 'in_out_box';
    return 'messages';
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_permission('gift.message.send');
$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '' || strlen($id) !== 36 || !preg_match('/^[a-f0-9-]{36}$/i', $id)) {
    mg_fail('Invalid thread identifier.', 422);
}

$pdo = mg_db();
$stmt = $pdo->prepare(
    'SELECT mt.id,mt.public_id,mt.subject,mt.gift_id,mt.pppm_item_id,mt.conversation_key,
            g.public_id AS gift_public_id,g.title AS gift_title,
            pi.public_id AS pppm_public_id
     FROM message_threads mt
     INNER JOIN message_thread_participants mtp ON mtp.thread_id=mt.id
     LEFT JOIN gifts g ON g.id=mt.gift_id
     LEFT JOIN pppm_items pi ON pi.id=mt.pppm_item_id
     WHERE mt.public_id=? AND mtp.user_id=? LIMIT 1'
);
$stmt->execute([strtolower($id), (int)$user['id']]);
$thread = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$thread) {
    mg_fail('Thread not found.', 404);
}

if ($method === 'GET') {
    $messagesStmt = $pdo->prepare(
        "SELECT m.public_id,m.body,m.created_at,m.sender_user_id,m.recipient_user_id,m.source_type,m.source_reference,
                u.display_name AS sender_name,u.full_name AS sender_full_name
         FROM messages m
         INNER JOIN users u ON u.id=m.sender_user_id
         WHERE m.thread_id=? AND m.moderation_status NOT IN ('hidden','removed')
         ORDER BY m.created_at ASC,m.id ASC"
    );
    $messagesStmt->execute([(int)$thread['id']]);
    $messages = array_map(static function (array $row) use ($user, $thread): array {
        $sourceType = mg_messages_source_type(
            isset($row['source_type']) ? (string)$row['source_type'] : null,
            isset($thread['conversation_key']) ? (string)$thread['conversation_key'] : null,
            isset($thread['pppm_public_id']) ? (string)$thread['pppm_public_id'] : null
        );
        return [
            'id' => (string)$row['public_id'],
            'body' => (string)$row['body'],
            'sender_id' => (int)$row['sender_user_id'],
            'recipient_id' => $row['recipient_user_id'] !== null ? (int)$row['recipient_user_id'] : null,
            'sender_name' => trim((string)($row['sender_name'] ?? $row['sender_full_name'] ?? 'Microgifter user')),
            'created_at' => $row['created_at'] ?? null,
            'mine' => (int)$row['sender_user_id'] === (int)$user['id'],
            'source' => [
                'type' => $sourceType,
                'label' => mg_messages_source_label($sourceType),
                'system' => mg_messages_source_system($sourceType),
                'reference' => isset($row['source_reference']) ? (string)$row['source_reference'] : '',
            ],
        ];
    }, $messagesStmt->fetchAll(PDO::FETCH_ASSOC));

    $latestSourceType = 'messaging';
    if ($messages !== []) {
        $last = $messages[array_key_last($messages)];
        $latestSourceType = (string)($last['source']['type'] ?? 'messaging');
    } else {
        $latestSourceType = mg_messages_source_type(null, (string)($thread['conversation_key'] ?? ''), (string)($thread['pppm_public_id'] ?? ''));
    }

    $readStmt = $pdo->prepare('UPDATE message_thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?');
    $readStmt->execute([(int)$thread['id'], (int)$user['id']]);

    mg_ok(['thread' => [
        'id' => (string)$thread['public_id'],
        'subject' => (string)($thread['subject'] ?? $thread['gift_title'] ?? 'Gift conversation'),
        'gift_id' => $thread['gift_public_id'] !== null ? (string)$thread['gift_public_id'] : null,
        'pppm_id' => $thread['pppm_public_id'] !== null ? (string)$thread['pppm_public_id'] : null,
        'conversation_key' => (string)($thread['conversation_key'] ?? ''),
        'source' => [
            'type' => $latestSourceType,
            'label' => mg_messages_source_label($latestSourceType),
            'system' => mg_messages_source_system($latestSourceType),
        ],
        'messages' => $messages,
    ]]);
}

mg_fail('Method not allowed.', 405);
