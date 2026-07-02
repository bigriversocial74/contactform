<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/api/communications/_communications.php';
require_once dirname(__DIR__) . '/api/messages/_delivery_validation.php';

$pdo = mg_db();
$runId = gmdate('YmdHis') . '-' . bin2hex(random_bytes(3));
$userAEmail = 'social-chat-a-' . $runId . '@example.test';
$userBEmail = 'social-chat-b-' . $runId . '@example.test';
$commitMode = in_array('--commit', $argv, true);

function social_chat_uuid(): string
{
    return function_exists('mg_public_uuid')
        ? mg_public_uuid()
        : sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0,65535), random_int(0,65535), random_int(0,65535), random_int(0,4095)|0x4000, random_int(0,0x3fff)|0x8000, random_int(0,65535), random_int(0,65535), random_int(0,65535));
}

function social_chat_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function social_chat_require_tables(PDO $pdo, array $tables): void
{
    foreach ($tables as $table) {
        if (!social_chat_table_exists($pdo, $table)) {
            throw new RuntimeException('Missing required table: ' . $table);
        }
    }
}

function social_chat_insert_user(PDO $pdo, string $email, string $displayName): int
{
    $password = password_hash('microgifter-social-chat-validation', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email,password_hash,full_name,display_name,status,email_verified_at,created_at,updated_at) VALUES (?,?,?,?, 'active', NOW(), NOW(), NOW())");
    $stmt->execute([$email, $password, $displayName, $displayName]);
    return (int)$pdo->lastInsertId();
}

function social_chat_insert_profile(PDO $pdo, int $userId, string $displayName, string $slug): string
{
    $publicId = social_chat_uuid();
    $stmt = $pdo->prepare("INSERT INTO public_profiles (public_id,user_id,slug,display_name,status,visibility,profile_type,created_at,updated_at) VALUES (?,?,?,?, 'active', 'public', 'profile', NOW(), NOW())");
    $stmt->execute([$publicId, $userId, $slug, $displayName]);
    return $publicId;
}

function social_chat_insert_message(PDO $pdo, int $threadId, int $senderId, int $recipientId, string $conversationKey, string $body): string
{
    $messageId = social_chat_uuid();
    $pdo->prepare('INSERT INTO messages (public_id,thread_id,sender_user_id,recipient_user_id,body,idempotency_key,source_type,source_reference,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([$messageId, $threadId, $senderId, $recipientId, $body, 'social-chat-validation:' . $messageId, 'social_chat', $conversationKey]);
    $pdo->prepare('UPDATE message_threads SET updated_at=NOW() WHERE id=?')->execute([$threadId]);
    return $messageId;
}

function social_chat_notify(PDO $pdo, int $recipientId, int $actorId, int $threadId, string $threadPublicId, string $messageId, string $senderProfileId, string $recipientProfileId, string $conversationKey, string $body): string
{
    return mg_create_notification(
        $pdo,
        $recipientId,
        'message',
        'New Feed Chat message',
        'Social Chat Validation: ' . mb_substr($body, 0, 500),
        '/feed.php?chat=' . rawurlencode($senderProfileId) . '&thread=' . rawurlencode($threadPublicId),
        [
            'actor_user_id' => $actorId,
            'event_key' => 'message.social_chat.validation.' . strtolower($threadPublicId),
            'aggregate' => true,
            'message_id' => $messageId,
            'thread_id' => $threadId,
            'thread_public_id' => $threadPublicId,
            'sender_profile_id' => $senderProfileId,
            'recipient_profile_id' => $recipientProfileId,
            'fallback_url' => '/messages.php?thread=' . rawurlencode($threadPublicId),
            'source_type' => 'social_chat',
            'source_reference' => $conversationKey,
            'source_system' => 'social_feed',
            'source_label' => 'Feed Chat',
            'conversation_key' => $conversationKey,
        ]
    );
}

social_chat_require_tables($pdo, ['users','public_profiles','social_follows','message_threads','message_thread_participants','messages','notifications','notification_delivery_jobs']);

$pdo->beginTransaction();
try {
    $userA = social_chat_insert_user($pdo, $userAEmail, 'Social Chat Sender');
    $userB = social_chat_insert_user($pdo, $userBEmail, 'Social Chat Recipient');
    $profileA = social_chat_insert_profile($pdo, $userA, 'Social Chat Sender', 'social-chat-sender-' . $runId);
    $profileB = social_chat_insert_profile($pdo, $userB, 'Social Chat Recipient', 'social-chat-recipient-' . $runId);

    $pdo->prepare("INSERT INTO social_follows (follower_user_id,followed_user_id,status,created_at,updated_at) VALUES (?,?,'active',NOW(),NOW()),(?,?,'active',NOW(),NOW())")
        ->execute([$userA, $userB, $userB, $userA]);

    $threadPublicId = social_chat_uuid();
    $conversationKey = 'social_direct:' . min($userA, $userB) . ':' . max($userA, $userB);
    $pdo->prepare('INSERT INTO message_threads (public_id,conversation_key,created_by_user_id,subject,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())')
        ->execute([$threadPublicId, $conversationKey, $userA, 'Social chat']);
    $threadId = (int)$pdo->lastInsertId();

    $participant = $pdo->prepare('INSERT IGNORE INTO message_thread_participants (thread_id,user_id,joined_at,last_read_at) VALUES (?,?,NOW(),NULL)');
    $participant->execute([$threadId, $userA]);
    $participant->execute([$threadId, $userB]);

    $messageA = social_chat_insert_message($pdo, $threadId, $userA, $userB, $conversationKey, 'Feed Chat delivery validation message');
    $notificationA = social_chat_notify($pdo, $userB, $userA, $threadId, $threadPublicId, $messageA, $profileA, $profileB, $conversationKey, 'Feed Chat delivery validation message');
    $deliveryA = mg_message_delivery_validate($pdo, [
        'thread_id' => $threadId,
        'thread_public_id' => $threadPublicId,
        'message_id' => $messageA,
        'sender_user_id' => $userA,
        'recipient_user_ids' => [$userB],
        'notification_ids' => [$notificationA],
        'source_type' => 'social_chat',
        'source_reference' => $conversationKey,
        'conversation_key' => $conversationKey,
    ]);
    mg_message_delivery_throw_if_failed($deliveryA);

    $messageB = social_chat_insert_message($pdo, $threadId, $userB, $userA, $conversationKey, 'Feed Chat reply validation message');
    $notificationB = social_chat_notify($pdo, $userA, $userB, $threadId, $threadPublicId, $messageB, $profileB, $profileA, $conversationKey, 'Feed Chat reply validation message');
    $deliveryB = mg_message_delivery_validate($pdo, [
        'thread_id' => $threadId,
        'thread_public_id' => $threadPublicId,
        'message_id' => $messageB,
        'sender_user_id' => $userB,
        'recipient_user_ids' => [$userA],
        'notification_ids' => [$notificationB],
        'source_type' => 'social_chat',
        'source_reference' => $conversationKey,
        'conversation_key' => $conversationKey,
    ]);
    mg_message_delivery_throw_if_failed($deliveryB);

    $output = [
        'ok' => true,
        'mode' => $commitMode ? 'committed' : 'rolled_back',
        'thread_public_id' => $threadPublicId,
        'conversation_key' => $conversationKey,
        'sender_profile_id' => $profileA,
        'recipient_profile_id' => $profileB,
        'initial_message' => $deliveryA,
        'reply_message' => $deliveryB,
    ];

    if ($commitMode) $pdo->commit();
    else $pdo->rollBack();

    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
