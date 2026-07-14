<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$config = rd_config();
$secret = trim((string)$config['webhook_secret']);
$body = file_get_contents('php://input') ?: '';
$timestamp = rd_server_header('X-Microgifter-Timestamp');
$signature = rd_server_header('X-Microgifter-Signature');
$eventTypeHeader = rd_server_header('X-Microgifter-Event');
$deliveryId = rd_server_header('X-Microgifter-Delivery');

$verified = $secret !== '' && $timestamp !== '' && $signature !== '';
if ($verified) {
    $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    $verified = ctype_digit($timestamp)
        && abs(time() - (int)$timestamp) <= 300
        && hash_equals($expected, $signature);
}

if (!$verified) {
    rd_json(['ok' => false, 'message' => 'Invalid Microgifter webhook signature.'], 401);
}

$payload = json_decode($body, true);
if (!is_array($payload)) rd_json(['ok' => false, 'message' => 'Invalid webhook payload.'], 422);
$eventId = trim((string)($payload['id'] ?? ''));
$eventType = trim((string)($payload['type'] ?? $eventTypeHeader));
$data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
$rewardId = trim((string)($data['reward_id'] ?? ''));
$externalEventId = trim((string)($data['external_event_id'] ?? ''));
if ($eventId === '' || $eventType === '') rd_json(['ok' => false, 'message' => 'Webhook event identity is required.'], 422);

$pdo = mg_db();
$pdo->beginTransaction();
try {
    $receipt = $pdo->prepare("INSERT IGNORE INTO reward_drop_webhook_receipts (event_public_id,delivery_public_id,event_type,reward_public_id,verified,payload_checksum,received_at,created_at) VALUES (?,?,?,?,1,?,NOW(),NOW())");
    $receipt->execute([$eventId, $deliveryId !== '' ? $deliveryId : null, $eventType, $rewardId !== '' ? $rewardId : null, hash('sha256', $body)]);
    if ($receipt->rowCount() === 0) {
        $pdo->commit();
        http_response_code(204);
        exit;
    }

    $where = '';
    $params = [];
    if ($rewardId !== '') {
        $where = 'reward_public_id=?';
        $params[] = $rewardId;
    } elseif ($externalEventId !== '') {
        $where = 'external_event_id=?';
        $params[] = $externalEventId;
    }

    if ($where !== '') {
        $status = null;
        $rewarded = false;
        $error = null;
        if (in_array($eventType, ['reward.delivered','reward.issued','reward.completed'], true)) {
            $status = 'delivered';
            $rewarded = true;
        } elseif (in_array($eventType, ['reward.failed','reward.cancelled','reward.expired'], true)) {
            $status = 'failed';
            $error = trim((string)($data['message'] ?? $data['reason'] ?? 'Reward delivery failed.'));
        } elseif (in_array($eventType, ['reward.queued','reward.processing'], true)) {
            $status = 'queued';
        }

        if ($status !== null) {
            $sql = "UPDATE reward_drop_runs SET status=?,api_status=?,webhook_event_public_id=?,webhook_delivery_public_id=?,webhook_received_at=NOW(),error_message=?,updated_at=NOW()";
            if ($rewarded) $sql .= ',rewarded_at=NOW()';
            $sql .= ' WHERE ' . $where;
            $pdo->prepare($sql)->execute(array_merge([
                $status,
                $eventType,
                $eventId,
                $deliveryId !== '' ? $deliveryId : null,
                $error !== null ? mb_substr($error, 0, 500) : null,
            ], $params));
        }
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    rd_json(['ok' => false, 'message' => 'Unable to record the webhook.'], 500);
}

http_response_code(204);
