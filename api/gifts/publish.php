<?php
declare(strict_types=1);

require_once __DIR__ . '/_gift.php';

mg_require_method('POST');
$user = mg_require_permission('gift.publish');
$input = mg_input();
mg_require_csrf_for_write($input);
$id = mg_gift_request_id($input);
$channel = trim((string) ($input['channel'] ?? 'link'));

if (!in_array($channel, ['email', 'sms', 'link', 'manual'], true)) {
    mg_fail('Invalid delivery channel.', 422);
}

$destination = trim((string) ($input['destination'] ?? ''));
if (mb_strlen($destination) > 255) {
    mg_fail('Invalid delivery destination.', 422);
}

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $gift = mg_gift_require_accessible((int) $user['id'], $id);

    if ((int) $gift['sender_user_id'] !== (int) $user['id']) {
        mg_fail('Only the gift owner can publish this gift.', 403);
    }
    if (($gift['status'] ?? '') !== 'draft') {
        mg_fail('Only draft gifts can be published.', 409);
    }

    $deliveryPublicId = mg_public_uuid();
    $delivery = $pdo->prepare(
        'INSERT INTO gift_deliveries (public_id, gift_id, channel, destination, status, queued_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())'
    );
    $delivery->execute([
        $deliveryPublicId,
        (int) $gift['id'],
        $channel,
        $destination !== '' ? $destination : null,
        'queued',
    ]);

    $update = $pdo->prepare(
        "UPDATE gifts
         SET status = 'sent', visibility = 'published', published_at = NOW(), sent_at = NOW(), updated_at = NOW()
         WHERE id = ? AND sender_user_id = ?"
    );
    $update->execute([(int) $gift['id'], (int) $user['id']]);

    mg_gift_event($pdo, (int) $gift['id'], (int) $user['id'], 'sent', [
        'delivery_id' => $deliveryPublicId,
        'channel' => $channel,
    ]);

    $pdo->commit();
    mg_audit('gift.published', 'gift', ['gift_id' => $id], (int) $user['id']);
    mg_ok([
        'gift_id' => $id,
        'delivery_id' => $deliveryPublicId,
        'status' => 'sent',
    ], 'Gift published.', 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'gift.publish_failed', 'Gift publish failed.', ['gift_id' => $id], (int) $user['id']);
    mg_fail('Unable to publish the gift right now.', 500);
}
