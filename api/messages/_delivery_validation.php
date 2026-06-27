<?php
declare(strict_types=1);

/**
 * Message delivery validation helpers.
 *
 * These checks are intentionally lightweight and safe to run inside an active
 * transaction after a message is inserted. They validate the canonical delivery
 * path used by Messages, Merchant CRM, Store Canvas, gifts, and PPPM:
 *
 * message_threads -> message_thread_participants -> messages -> notifications
 */
function mg_message_delivery_bool(PDO $pdo, string $sql, array $params = []): bool
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function mg_message_delivery_notification_public_id(PDO $pdo, string $publicId): ?int
{
    $publicId = trim($publicId);
    if ($publicId === '') return null;
    $stmt = $pdo->prepare('SELECT id FROM notifications WHERE public_id=? LIMIT 1');
    $stmt->execute([$publicId]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (int)$value : null;
}

function mg_message_delivery_validate(PDO $pdo, array $input): array
{
    $threadId = (int)($input['thread_id'] ?? 0);
    $threadPublicId = trim((string)($input['thread_public_id'] ?? ''));
    $messagePublicId = trim((string)($input['message_id'] ?? ''));
    $senderUserId = (int)($input['sender_user_id'] ?? 0);
    $sourceType = trim((string)($input['source_type'] ?? ''));
    $sourceReference = array_key_exists('source_reference', $input) && $input['source_reference'] !== null
        ? trim((string)$input['source_reference'])
        : null;
    $conversationKey = array_key_exists('conversation_key', $input) && $input['conversation_key'] !== null
        ? trim((string)$input['conversation_key'])
        : null;

    $recipientUserIds = [];
    foreach ((array)($input['recipient_user_ids'] ?? []) as $recipientUserId) {
        $recipientUserId = (int)$recipientUserId;
        if ($recipientUserId > 0) $recipientUserIds[$recipientUserId] = $recipientUserId;
    }
    $recipientUserIds = array_values($recipientUserIds);

    $notificationPublicIds = [];
    foreach ((array)($input['notification_ids'] ?? []) as $notificationPublicId) {
        $notificationPublicId = trim((string)$notificationPublicId);
        if ($notificationPublicId !== '') $notificationPublicIds[] = $notificationPublicId;
    }

    $checks = [
        'thread_exists' => false,
        'message_exists' => false,
        'sender_participant' => false,
        'recipient_participants' => true,
        'source_metadata' => true,
        'conversation_key' => true,
        'notifications_resolved' => true,
    ];
    $details = [
        'thread_id' => $threadPublicId,
        'message_id' => $messagePublicId,
        'source_type' => $sourceType,
        'source_reference' => $sourceReference,
        'conversation_key' => $conversationKey,
        'recipient_user_ids' => $recipientUserIds,
        'notification_ids' => $notificationPublicIds,
        'missing_recipient_participants' => [],
        'missing_notifications' => [],
    ];
    $warnings = [];

    if ($threadId > 0 && $threadPublicId !== '') {
        $checks['thread_exists'] = mg_message_delivery_bool(
            $pdo,
            'SELECT 1 FROM message_threads WHERE id=? AND public_id=? LIMIT 1',
            [$threadId, $threadPublicId]
        );
    }

    if ($threadId > 0 && $messagePublicId !== '') {
        $params = [$messagePublicId, $threadId];
        $sql = 'SELECT 1 FROM messages WHERE public_id=? AND thread_id=?';
        if ($sourceType !== '') {
            $sql .= ' AND source_type=?';
            $params[] = $sourceType;
        }
        if ($sourceReference !== null) {
            $sql .= ' AND source_reference=?';
            $params[] = $sourceReference;
        }
        $sql .= ' LIMIT 1';
        $checks['message_exists'] = mg_message_delivery_bool($pdo, $sql, $params);
    }

    if ($threadId > 0 && $senderUserId > 0) {
        $checks['sender_participant'] = mg_message_delivery_bool(
            $pdo,
            'SELECT 1 FROM message_thread_participants WHERE thread_id=? AND user_id=? LIMIT 1',
            [$threadId, $senderUserId]
        );
    }

    foreach ($recipientUserIds as $recipientUserId) {
        $hasParticipant = mg_message_delivery_bool(
            $pdo,
            'SELECT 1 FROM message_thread_participants WHERE thread_id=? AND user_id=? LIMIT 1',
            [$threadId, $recipientUserId]
        );
        if (!$hasParticipant) {
            $checks['recipient_participants'] = false;
            $details['missing_recipient_participants'][] = $recipientUserId;
        }
    }

    if ($messagePublicId !== '' && count($recipientUserIds) === 1) {
        $recipientMatches = mg_message_delivery_bool(
            $pdo,
            'SELECT 1 FROM messages WHERE public_id=? AND recipient_user_id=? LIMIT 1',
            [$messagePublicId, $recipientUserIds[0]]
        );
        if (!$recipientMatches) {
            $warnings[] = 'Message recipient_user_id does not match the single resolved recipient; thread participants still determine visibility.';
        }
    }

    if ($threadId > 0 && $conversationKey !== null && $conversationKey !== '') {
        $checks['conversation_key'] = mg_message_delivery_bool(
            $pdo,
            'SELECT 1 FROM message_threads WHERE id=? AND conversation_key=? LIMIT 1',
            [$threadId, $conversationKey]
        );
    }

    if ($messagePublicId !== '' && $sourceType !== '') {
        $checks['source_metadata'] = mg_message_delivery_bool(
            $pdo,
            'SELECT 1 FROM messages WHERE public_id=? AND source_type=? LIMIT 1',
            [$messagePublicId, $sourceType]
        );
    }

    foreach ($notificationPublicIds as $notificationPublicId) {
        $notificationId = mg_message_delivery_notification_public_id($pdo, $notificationPublicId);
        if ($notificationId === null) {
            $checks['notifications_resolved'] = false;
            $details['missing_notifications'][] = $notificationPublicId;
        }
    }

    if ($recipientUserIds !== [] && $notificationPublicIds === []) {
        $warnings[] = 'No notification public ID was returned; recipient may have notifications disabled or suppressed.';
    }

    $ok = !in_array(false, $checks, true);
    return [
        'ok' => $ok,
        'status' => $ok ? 'validated' : 'failed',
        'checks' => $checks,
        'warnings' => $warnings,
        'details' => $details,
    ];
}

function mg_message_delivery_throw_if_failed(array $validation): void
{
    if (!empty($validation['ok'])) return;
    throw new RuntimeException('Message delivery validation failed: ' . json_encode($validation['checks'] ?? [], JSON_UNESCAPED_SLASHES));
}
