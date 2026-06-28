<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_runtime.php';
require_once dirname(__DIR__) . '/messages/_messaging.php';
require_once dirname(__DIR__) . '/messages/_delivery_validation.php';

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$user = mg_require_api_user();
$pdo = mg_db();

try {
    mg_rate_limit('store.chat_reply', 'user:' . (int)$user['id'], 90, 60);
    mg_store_runtime_require_schema($pdo);
    $body = mg_message_validate_body($input['message'] ?? $input['body'] ?? '');
    $session = mg_store_runtime_active_session_for_customer($pdo, (int)$user['id']);
    if (!$session) throw new RuntimeException('You are not currently inside a merchant store.');

    $customerUserId = (int)$user['id'];
    $merchantUserId = (int)$session['merchant_user_id'];
    if ($merchantUserId < 1 || $merchantUserId === $customerUserId) {
        throw new RuntimeException('Active merchant conversation is not available.');
    }
    $conversationKey = mg_store_canvas_conversation_key($merchantUserId, $customerUserId);

    $pdo->beginTransaction();
    try {
        $threadStmt = $pdo->prepare(
            'SELECT mt.id,mt.public_id,mt.subject,mt.conversation_key
             FROM message_threads mt
             INNER JOIN message_thread_participants customer_participant ON customer_participant.thread_id=mt.id AND customer_participant.user_id=?
             INNER JOIN message_thread_participants merchant_participant ON merchant_participant.thread_id=mt.id AND merchant_participant.user_id=?
             WHERE mt.conversation_key=?
             ORDER BY mt.id ASC LIMIT 1 FOR UPDATE'
        );
        $threadStmt->execute([$customerUserId, $merchantUserId, $conversationKey]);
        $thread = $threadStmt->fetch(PDO::FETCH_ASSOC);
        if (!$thread) {
            throw new RuntimeException('The merchant has not started a Store Canvas chat yet.');
        }

        $messagePublicId = mg_public_uuid();
        $sourceType = 'store_canvas_reply';
        $sourceReference = $conversationKey;
        $pdo->prepare(
            'INSERT INTO messages
             (public_id,thread_id,sender_user_id,recipient_user_id,body,source_type,source_reference,created_at)
             VALUES (?,?,?,?,?,?,?,NOW())'
        )->execute([
            $messagePublicId,
            (int)$thread['id'],
            $customerUserId,
            $merchantUserId,
            $body,
            $sourceType,
            $sourceReference,
        ]);
        $pdo->prepare('UPDATE message_threads SET updated_at=NOW() WHERE id=?')->execute([(int)$thread['id']]);
        $pdo->prepare('UPDATE message_thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int)$thread['id'], $customerUserId]);

        $customerName = mg_notification_user_label($pdo, $customerUserId);
        $notificationId = mg_create_notification(
            $pdo,
            $merchantUserId,
            'message',
            'New Store Canvas reply',
            $customerName . ': ' . mb_substr($body, 0, 500),
            '/merchant-canvas.php',
            [
                'actor_user_id' => $customerUserId,
                'event_key' => 'message.store_canvas.reply.' . strtolower($messagePublicId),
                'message_id' => $messagePublicId,
                'thread_id' => (int)$thread['id'],
                'thread_public_id' => (string)$thread['public_id'],
                'merchant_user_id' => $merchantUserId,
                'customer_user_id' => $customerUserId,
                'store_session_id' => (string)$session['public_id'],
                'conversation_key' => $conversationKey,
                'source_type' => $sourceType,
                'source_reference' => $sourceReference,
                'source_system' => 'store_canvas',
                'source_label' => 'Store Canvas Reply',
            ]
        );

        $deliveryValidation = mg_message_delivery_validate($pdo, [
            'thread_id' => (int)$thread['id'],
            'thread_public_id' => (string)$thread['public_id'],
            'message_id' => $messagePublicId,
            'sender_user_id' => $customerUserId,
            'recipient_user_ids' => [$merchantUserId],
            'notification_ids' => $notificationId !== '' ? [$notificationId] : [],
            'source_type' => $sourceType,
            'source_reference' => $sourceReference,
            'conversation_key' => $conversationKey,
        ]);
        mg_message_delivery_throw_if_failed($deliveryValidation);
        mg_store_log_event($pdo, $session, 'customer_chat_reply', 'Customer replied to merchant chat', ['thread_id'=>(string)$thread['public_id'],'message_id'=>$messagePublicId,'source_system'=>'store_canvas']);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    mg_event('store_canvas.customer_chat_reply', ['thread_id'=>(string)$thread['public_id'],'message_id'=>$messagePublicId,'merchant_user_id'=>$merchantUserId], $customerUserId);
    mg_ok([
        'thread_id' => (string)$thread['public_id'],
        'message_id' => $messagePublicId,
        'source_type' => $sourceType,
        'notification_id' => $notificationId ?: null,
        'delivery_validation' => $deliveryValidation,
    ], 'Reply sent to merchant.');
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 400);
} catch (Throwable $error) {
    mg_security_log('error', 'store.chat_reply_failed', 'Store chat reply failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], (int)$user['id']);
    mg_fail('Unable to send store chat reply.', 500);
}
