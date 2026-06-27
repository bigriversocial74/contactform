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
$merchantEmail = 'delivery-merchant-' . $runId . '@example.test';
$customerEmail = 'delivery-customer-' . $runId . '@example.test';
$commitMode = in_array('--commit', $argv, true);

function delivery_uuid(): string
{
    return function_exists('mg_public_uuid')
        ? mg_public_uuid()
        : sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0,65535), random_int(0,65535), random_int(0,65535), random_int(0,4095)|0x4000, random_int(0,0x3fff)|0x8000, random_int(0,65535), random_int(0,65535), random_int(0,65535));
}

function delivery_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function delivery_require_tables(PDO $pdo, array $tables): void
{
    foreach ($tables as $table) {
        if (!delivery_table_exists($pdo, $table)) {
            throw new RuntimeException('Missing required table: ' . $table);
        }
    }
}

function delivery_insert_user(PDO $pdo, string $email, string $displayName): int
{
    $password = password_hash('microgifter-delivery-validation', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email,password_hash,full_name,display_name,status,email_verified_at,created_at,updated_at) VALUES (?,?,?,?, 'active', NOW(), NOW(), NOW())");
    $stmt->execute([$email, $password, $displayName, $displayName]);
    return (int)$pdo->lastInsertId();
}

function delivery_notification(PDO $pdo, int $userId, int $actorId, int $threadId, string $threadPublicId, string $messageId, string $type, string $title, string $body, array $extra = []): string
{
    return mg_create_notification(
        $pdo,
        $userId,
        $type,
        $title,
        $body,
        '/messages.php?thread=' . rawurlencode($threadPublicId),
        array_merge([
            'actor_user_id' => $actorId,
            'event_key' => 'delivery.validation.' . strtolower($messageId),
            'thread_id' => $threadId,
            'thread_public_id' => $threadPublicId,
            'message_id' => $messageId,
        ], $extra)
    );
}

function delivery_insert_message(PDO $pdo, int $threadId, int $senderId, int $recipientId, string $body, string $sourceType, string $sourceReference, string $prefix): string
{
    $messageId = delivery_uuid();
    $pdo->prepare('INSERT INTO messages (public_id,thread_id,sender_user_id,recipient_user_id,body,idempotency_key,source_type,source_reference,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([$messageId, $threadId, $senderId, $recipientId, $body, $prefix . ':' . $messageId, $sourceType, $sourceReference]);
    $pdo->prepare('UPDATE message_threads SET updated_at=NOW() WHERE id=?')->execute([$threadId]);
    return $messageId;
}

delivery_require_tables($pdo, ['users','message_threads','message_thread_participants','messages','notifications','notification_delivery_jobs','campaigns','campaign_contacts']);

$pdo->beginTransaction();
try {
    $merchantId = delivery_insert_user($pdo, $merchantEmail, 'Delivery Merchant');
    $customerId = delivery_insert_user($pdo, $customerEmail, 'Delivery Customer');

    $campaignPublicId = delivery_uuid();
    $pdo->prepare("INSERT INTO campaigns (public_id,merchant_user_id,campaign_type,title,status,created_at,updated_at) VALUES (?,?, 'newsletter_signup', 'Delivery Validation Campaign', 'active', NOW(), NOW())")
        ->execute([$campaignPublicId, $merchantId]);
    $campaignId = (int)$pdo->lastInsertId();

    $contactPublicId = delivery_uuid();
    $pdo->prepare("INSERT INTO campaign_contacts (public_id,merchant_user_id,campaign_id,user_id,email,name,source,opt_in_status,created_at,updated_at) VALUES (?,?,?,?,?,?,'newsletter_signup','opted_in',NOW(),NOW())")
        ->execute([$contactPublicId, $merchantId, $campaignId, $customerId, $customerEmail, 'Delivery Customer']);
    $contactId = (int)$pdo->lastInsertId();

    $threadPublicId = delivery_uuid();
    $conversationKey = 'crm:' . $contactPublicId;
    $pdo->prepare('INSERT INTO message_threads (public_id,conversation_key,created_by_user_id,subject,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())')
        ->execute([$threadPublicId, $conversationKey, $merchantId, 'CRM: Delivery Customer']);
    $threadId = (int)$pdo->lastInsertId();

    $participant = $pdo->prepare('INSERT IGNORE INTO message_thread_participants (thread_id,user_id,joined_at,last_read_at) VALUES (?,?,NOW(),NULL)');
    $participant->execute([$threadId, $merchantId]);
    $participant->execute([$threadId, $customerId]);

    $merchantMessageId = delivery_insert_message($pdo, $threadId, $merchantId, $customerId, 'Merchant CRM delivery validation message', 'merchant_crm_message', $contactPublicId, 'crm-delivery-validation');
    $merchantNotificationId = delivery_notification($pdo, $customerId, $merchantId, $threadId, $threadPublicId, $merchantMessageId, 'message', 'New merchant CRM message', 'Merchant CRM delivery validation message', [
        'campaign_id' => $campaignId,
        'contact_id' => $contactId,
        'source_system' => 'merchant_crm',
        'source_channel' => 'campaign_contacts',
        'source_label' => 'Merchant CRM',
    ]);

    $crmDelivery = mg_message_delivery_validate($pdo, [
        'thread_id' => $threadId,
        'thread_public_id' => $threadPublicId,
        'message_id' => $merchantMessageId,
        'sender_user_id' => $merchantId,
        'recipient_user_ids' => [$customerId],
        'notification_ids' => [$merchantNotificationId],
        'source_type' => 'merchant_crm_message',
        'source_reference' => $contactPublicId,
        'conversation_key' => $conversationKey,
    ]);
    mg_message_delivery_throw_if_failed($crmDelivery);

    $replyMessageId = delivery_insert_message($pdo, $threadId, $customerId, $merchantId, 'Customer CRM reply validation message', 'merchant_crm_message', $contactPublicId, 'crm-reply-delivery-validation');
    $replyNotificationId = delivery_notification($pdo, $merchantId, $customerId, $threadId, $threadPublicId, $replyMessageId, 'merchant_crm_message', 'New Merchant CRM reply', 'Customer CRM reply validation message', [
        'campaign_id' => $campaignId,
        'contact_id' => $contactId,
        'source_system' => 'merchant_crm',
        'source_channel' => 'messages',
        'source_label' => 'Merchant CRM',
    ]);

    $replyDelivery = mg_message_delivery_validate($pdo, [
        'thread_id' => $threadId,
        'thread_public_id' => $threadPublicId,
        'message_id' => $replyMessageId,
        'sender_user_id' => $customerId,
        'recipient_user_ids' => [$merchantId],
        'notification_ids' => [$replyNotificationId],
        'source_type' => 'merchant_crm_message',
        'source_reference' => $contactPublicId,
        'conversation_key' => $conversationKey,
    ]);
    mg_message_delivery_throw_if_failed($replyDelivery);

    $storeDelivery = null;
    if (delivery_table_exists($pdo, 'mg_store_sessions') && delivery_table_exists($pdo, 'mg_store_session_events') && delivery_table_exists($pdo, 'mg_customer_store_history')) {
        $storeThreadPublicId = delivery_uuid();
        $storeConversationKey = 'store_canvas:' . $merchantId . ':' . $customerId;
        $pdo->prepare('INSERT INTO message_threads (public_id,conversation_key,created_by_user_id,subject,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())')
            ->execute([$storeThreadPublicId, $storeConversationKey, $merchantId, 'Store Canvas: Delivery Merchant']);
        $storeThreadId = (int)$pdo->lastInsertId();
        $participant->execute([$storeThreadId, $merchantId]);
        $participant->execute([$storeThreadId, $customerId]);

        $storeSessionPublicId = delivery_uuid();
        $pdo->prepare("INSERT INTO mg_store_sessions (public_id,customer_user_id,merchant_user_id,status,active_key,entered_at,last_active_at,metadata_json,created_at,updated_at) VALUES (?,?,?,'active',?,NOW(),NOW(),?,NOW(),NOW())")
            ->execute([$storeSessionPublicId, $customerId, $merchantId, $customerId, json_encode(['validation' => true], JSON_UNESCAPED_SLASHES)]);
        $storeSessionId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO mg_customer_store_history (public_id,customer_user_id,merchant_user_id,store_session_id,summary,started_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),?,NOW(),NOW())')
            ->execute([delivery_uuid(), $customerId, $merchantId, $storeSessionId, 'Delivery validation visit', json_encode(['validation' => true], JSON_UNESCAPED_SLASHES)]);
        $pdo->prepare('INSERT INTO mg_store_session_events (public_id,store_session_id,customer_user_id,merchant_user_id,event_type,event_label,event_data_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
            ->execute([delivery_uuid(), $storeSessionId, $customerId, $merchantId, 'entered_store', 'Entered store', json_encode(['validation' => true], JSON_UNESCAPED_SLASHES)]);

        $storeMessageId = delivery_insert_message($pdo, $storeThreadId, $merchantId, $customerId, 'Store Canvas delivery validation message', 'store_canvas_direct', 'store_session:' . $storeSessionPublicId, 'store-canvas-delivery-validation');
        $storeNotificationId = delivery_notification($pdo, $customerId, $merchantId, $storeThreadId, $storeThreadPublicId, $storeMessageId, 'message', 'New message from Delivery Merchant', 'Store Canvas delivery validation message', [
            'merchant_user_id' => $merchantId,
            'store_session_id' => $storeSessionPublicId,
            'conversation_key' => $storeConversationKey,
            'source_system' => 'store_canvas',
            'source_channel' => 'merchant_canvas',
            'source_label' => 'Merchant Store Canvas',
        ]);

        $storeDelivery = mg_message_delivery_validate($pdo, [
            'thread_id' => $storeThreadId,
            'thread_public_id' => $storeThreadPublicId,
            'message_id' => $storeMessageId,
            'sender_user_id' => $merchantId,
            'recipient_user_ids' => [$customerId],
            'notification_ids' => [$storeNotificationId],
            'source_type' => 'store_canvas_direct',
            'source_reference' => 'store_session:' . $storeSessionPublicId,
            'conversation_key' => $storeConversationKey,
        ]);
        mg_message_delivery_throw_if_failed($storeDelivery);
    }

    $output = [
        'ok' => true,
        'mode' => $commitMode ? 'committed' : 'rolled_back',
        'merchant_user_id' => $merchantId,
        'customer_user_id' => $customerId,
        'crm' => $crmDelivery,
        'crm_reply' => $replyDelivery,
        'store_canvas' => $storeDelivery ?? ['status' => 'skipped', 'reason' => 'Store Canvas core tables are not installed.'],
    ];

    if ($commitMode) $pdo->commit();
    else $pdo->rollBack();

    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
