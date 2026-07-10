<?php
declare(strict_types=1);

/**
 * Store Canvas messaging bridge.
 *
 * Merchant Canvas messages use the existing message_threads/messages and
 * notifications system as the canonical delivery path. The Stage 20
 * mg_agent_messages table remains an optional Store Canvas audit mirror.
 */
require_once __DIR__ . '/_canvas_manual_operations.php';
require_once dirname(__DIR__) . '/messages/_messaging.php';
require_once dirname(__DIR__) . '/messages/_delivery_validation.php';

function mg_store_canvas_core_schema_require(PDO $pdo): void
{
    mg_store_canvas_require_tables($pdo, ['mg_store_sessions','mg_store_session_events','mg_customer_store_history'], 'Store Canvas');
}

function mg_store_canvas_message_source_label(string $sourceType): string
{
    return match ($sourceType) {
        'store_canvas_direct' => 'Store Canvas',
        'store_canvas_reply' => 'Store Canvas Reply',
        'action_center_follow_up' => 'Action Center Follow Up',
        'action_center' => 'Action Center',
        'messaging' => 'Messages',
        default => ucwords(str_replace(['_', '-'], ' ', $sourceType ?: 'Messages')),
    };
}

function mg_store_canvas_conversation_key(int $merchantUserId, int $customerUserId): string
{
    if ($merchantUserId < 1 || $customerUserId < 1 || $merchantUserId === $customerUserId) {
        throw new InvalidArgumentException('A valid Store Canvas conversation pair is required.');
    }
    return 'store_canvas:' . $merchantUserId . ':' . $customerUserId;
}

function mg_store_canvas_thread(PDO $pdo, array $session, int $merchantUserId, string $merchantLabel): array
{
    $customerUserId = (int)($session['customer_user_id'] ?? 0);
    $conversationKey = mg_store_canvas_conversation_key($merchantUserId, $customerUserId);

    $stmt = $pdo->prepare(
        "SELECT mt.id, mt.public_id, mt.subject, mt.conversation_key
         FROM message_threads mt
         INNER JOIN message_thread_participants merchant_participant
           ON merchant_participant.thread_id = mt.id AND merchant_participant.user_id = ?
         INNER JOIN message_thread_participants customer_participant
           ON customer_participant.thread_id = mt.id AND customer_participant.user_id = ?
         WHERE mt.conversation_key = ?
           AND mt.gift_id IS NULL
           AND mt.pppm_item_id IS NULL
           AND mt.microgift_instance_id IS NULL
         ORDER BY mt.id ASC
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$merchantUserId, $customerUserId, $conversationKey]);
    $thread = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$thread) {
        $threadPublicId = mg_public_uuid();
        $subject = 'Store Canvas: ' . mb_substr($merchantLabel, 0, 142);
        $insert = $pdo->prepare(
            'INSERT INTO message_threads
             (public_id,gift_id,pppm_item_id,microgift_instance_id,conversation_key,created_by_user_id,subject,created_at,updated_at)
             VALUES (?,NULL,NULL,NULL,?,?,?,NOW(),NOW())'
        );
        $insert->execute([$threadPublicId, $conversationKey, $merchantUserId, $subject]);
        $thread = [
            'id' => (int)$pdo->lastInsertId(),
            'public_id' => $threadPublicId,
            'subject' => $subject,
            'conversation_key' => $conversationKey,
        ];
    }

    $participant = $pdo->prepare(
        'INSERT IGNORE INTO message_thread_participants (thread_id,user_id,joined_at) VALUES (?,?,NOW())'
    );
    $participant->execute([(int)$thread['id'], $merchantUserId]);
    $participant->execute([(int)$thread['id'], $customerUserId]);

    return $thread;
}

function mg_store_send_direct_message_via_messaging(PDO $pdo, int $merchantUserId, string $sessionPublicId, string $body, string $clientIdempotencyKey = ''): array
{
    mg_store_canvas_core_schema_require($pdo);
    mg_store_manual_ops_require_schema($pdo);
    $sessionPublicId = mg_store_safe_public_id($sessionPublicId, 'Store session');
    $body = mg_message_validate_body($body);
    $clientIdempotencyKey = mg_store_manual_ops_idempotency_key($clientIdempotencyKey);
    $requestHash = mg_store_manual_ops_request_hash([
        'merchant_user_id' => $merchantUserId,
        'session_id' => $sessionPublicId,
        'message' => $body,
    ]);

    $merchantLabel = 'Merchant';
    try {
        $merchant = $pdo->prepare('SELECT display_name FROM public_profiles WHERE user_id=? LIMIT 1');
        $merchant->execute([$merchantUserId]);
        $merchantLabel = trim((string)($merchant->fetchColumn() ?: 'Merchant')) ?: 'Merchant';
    } catch (Throwable) {
        $merchantLabel = 'Merchant';
    }

    $messagePublicId = '';
    $thread = [];
    $notificationPublicId = '';
    $deliveryValidation = [];
    $customerUserId = 0;
    $result = [];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM mg_store_sessions
             WHERE public_id=?
               AND merchant_user_id=?
               AND active_key IS NOT NULL
               AND status IN ('entered','active','idle')
               AND exited_at IS NULL
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$sessionPublicId, $merchantUserId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) throw new RuntimeException('Active customer session is not available.');
        if (strtotime((string)$session['last_active_at']) < (time() - (MG_STORE_EXPIRE_MINUTES * 60))) {
            throw new RuntimeException('Customer session has expired. Refresh the Store Canvas.');
        }

        $customerUserId = (int)$session['customer_user_id'];
        if ($customerUserId < 1 || $customerUserId === $merchantUserId) {
            throw new RuntimeException('Active customer session is not messageable.');
        }

        $receipt = mg_store_manual_ops_receipt_claim(
            $pdo,
            $merchantUserId,
            $customerUserId,
            (int)$session['id'],
            $merchantUserId,
            'manual_message',
            $clientIdempotencyKey,
            $requestHash
        );
        if (!empty($receipt['duplicate'])) {
            $result = is_array($receipt['response']) ? $receipt['response'] : [];
            $result['duplicate'] = true;
            $pdo->commit();
            return $result;
        }

        mg_store_manual_ops_assert_message_allowed($pdo, $merchantUserId, $customerUserId, true);

        $thread = mg_store_canvas_thread($pdo, $session, $merchantUserId, $merchantLabel);
        $conversationKey = (string)$thread['conversation_key'];
        $messagePublicId = mg_public_uuid();
        $sourceType = 'store_canvas_direct';
        $sourceReference = 'store_session:' . $sessionPublicId;
        $canonicalIdempotencyKey = substr('store_canvas_manual:' . $merchantUserId . ':' . hash('sha256', $clientIdempotencyKey), 0, 190);

        $messageInsert = $pdo->prepare(
            'INSERT INTO messages
             (public_id,thread_id,sender_user_id,recipient_user_id,body,idempotency_key,source_type,source_reference,created_at)
             VALUES (?,?,?,?,?,?,?,?,NOW())'
        );
        $messageInsert->execute([
            $messagePublicId,
            (int)$thread['id'],
            $merchantUserId,
            $customerUserId,
            $body,
            $canonicalIdempotencyKey,
            $sourceType,
            $sourceReference,
        ]);

        $pdo->prepare('UPDATE message_threads SET updated_at=NOW() WHERE id=?')->execute([(int)$thread['id']]);
        $pdo->prepare('UPDATE message_thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int)$thread['id'], $merchantUserId]);

        $auditPublicId = '';
        if (mg_store_canvas_table_exists($pdo, 'mg_agent_messages')) {
            try {
                $auditPublicId = mg_public_uuid();
                $audit = $pdo->prepare(
                    "INSERT INTO mg_agent_messages
                     (public_id,store_session_id,sender_user_id,recipient_user_id,merchant_user_id,sender_role,message_type,subject,body,status,metadata_json,created_at,updated_at)
                     VALUES (?,?,?,?,?,'merchant','direct',? ,?,'sent',?,NOW(),NOW())"
                );
                $audit->execute([
                    $auditPublicId,
                    (int)$session['id'],
                    $merchantUserId,
                    $customerUserId,
                    $merchantUserId,
                    'Message from ' . mb_substr($merchantLabel, 0, 120),
                    $body,
                    json_encode([
                        'source_system' => 'store_canvas',
                        'source_channel' => 'merchant_canvas',
                        'canonical_system' => 'messages',
                        'thread_id' => (string)$thread['public_id'],
                        'message_id' => $messagePublicId,
                        'conversation_key' => $conversationKey,
                        'store_session_id' => $sessionPublicId,
                        'initiated_by_user_id' => $merchantUserId,
                        'action_receipt_id' => (string)$receipt['public_id'],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
            } catch (Throwable $auditError) {
                mg_security_log('error', 'store_canvas.message_audit_failed', 'Store canvas message audit mirror failed.', ['exception'=>$auditError->getMessage()], $merchantUserId);
            }
        }

        mg_store_log_event($pdo, $session, 'message_received', 'Merchant sent direct message', [
            'source_system' => 'messages',
            'source_channel' => 'merchant_canvas',
            'thread_id' => (string)$thread['public_id'],
            'message_id' => $messagePublicId,
            'agent_message_id' => $auditPublicId ?: null,
            'initiated_by_user_id' => $merchantUserId,
            'action_receipt_id' => (string)$receipt['public_id'],
        ]);

        $notificationPublicId = mg_create_notification(
            $pdo,
            $customerUserId,
            'message',
            'New message from ' . $merchantLabel,
            mb_substr($body, 0, 240),
            '/messages.php?thread=' . rawurlencode((string)$thread['public_id']),
            [
                'actor_user_id' => $merchantUserId,
                'event_key' => 'message.store_canvas.' . strtolower($messagePublicId),
                'message_id' => $messagePublicId,
                'thread_id' => (int)$thread['id'],
                'thread_public_id' => (string)$thread['public_id'],
                'merchant_user_id' => $merchantUserId,
                'store_session_id' => $sessionPublicId,
                'conversation_key' => $conversationKey,
                'source_system' => 'store_canvas',
                'source_channel' => 'merchant_canvas',
                'source_label' => 'Merchant Store Canvas',
                'action_receipt_id' => (string)$receipt['public_id'],
            ]
        );

        $deliveryValidation = mg_message_delivery_validate($pdo, [
            'thread_id' => (int)$thread['id'],
            'thread_public_id' => (string)$thread['public_id'],
            'message_id' => $messagePublicId,
            'sender_user_id' => $merchantUserId,
            'recipient_user_ids' => [$customerUserId],
            'notification_ids' => $notificationPublicId !== '' ? [$notificationPublicId] : [],
            'source_type' => $sourceType,
            'source_reference' => $sourceReference,
            'conversation_key' => $conversationKey,
        ]);
        mg_message_delivery_throw_if_failed($deliveryValidation);

        $result = [
            'id' => $messagePublicId,
            'thread_id' => (string)$thread['public_id'],
            'notification_id' => $notificationPublicId ?: null,
            'source_system' => 'messages',
            'source_type' => 'store_canvas_direct',
            'source_label' => 'Store Canvas',
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
            'delivery_validation' => $deliveryValidation,
            'duplicate' => false,
            'action_receipt_id' => (string)$receipt['public_id'],
        ];
        mg_store_manual_ops_receipt_complete($pdo, (int)$receipt['id'], $messagePublicId, $result);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    mg_event('store_canvas.direct_message_sent', [
        'message_id' => $messagePublicId,
        'thread_id' => (string)($thread['public_id'] ?? ''),
        'session_id' => $sessionPublicId,
        'customer_user_id' => $customerUserId,
        'source_system' => 'messages',
        'delivery_validation' => $deliveryValidation['status'] ?? 'unknown',
    ], $merchantUserId);

    return $result;
}
