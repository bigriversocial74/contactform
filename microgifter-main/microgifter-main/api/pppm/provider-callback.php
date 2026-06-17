<?php
declare(strict_types=1);
require_once __DIR__ . '/_delivery.php';

mg_require_method('POST');
$raw = file_get_contents('php://input');
$raw = is_string($raw) ? $raw : '';
$secret = (string) getenv('MG_DELIVERY_WEBHOOK_SECRET');
$signature = trim((string) ($_SERVER['HTTP_X_MICROGIFTER_SIGNATURE'] ?? ''));
if ($secret === '' || $signature === '' || !hash_equals(hash_hmac('sha256', $raw, $secret), $signature)) {
    mg_fail('Invalid provider signature.', 401);
}

$input = json_decode($raw, true);
if (!is_array($input)) mg_fail('Invalid provider payload.', 422);
$provider = trim((string) ($input['provider'] ?? ''));
$externalEventId = trim((string) ($input['external_event_id'] ?? ''));
$eventType = trim((string) ($input['event_type'] ?? ''));
$providerReference = trim((string) ($input['provider_reference'] ?? ''));
if ($provider === '' || $externalEventId === '' || $eventType === '' || $providerReference === '') mg_fail('Incomplete provider payload.', 422);

$statusMap = ['sent' => 'sent', 'delivered' => 'delivered', 'failed' => 'failed'];
if (!isset($statusMap[$eventType])) mg_fail('Unsupported provider event.', 422);

$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $existing = $pdo->prepare('SELECT public_id FROM pppm_provider_events WHERE provider = ? AND external_event_id = ? LIMIT 1 FOR UPDATE');
    $existing->execute([$provider, $externalEventId]);
    $existingId = $existing->fetchColumn();
    if ($existingId) {
        $pdo->commit();
        mg_ok(['provider_event_id' => $existingId, 'duplicate' => true], 'Provider event already processed.');
    }

    $attemptStmt = $pdo->prepare(
        'SELECT a.*, d.pppm_item_id, d.id AS delivery_db_id
         FROM pppm_delivery_attempts a
         INNER JOIN pppm_deliveries d ON d.id = a.delivery_id
         WHERE a.provider = ? AND a.provider_reference = ?
         ORDER BY a.id DESC LIMIT 1 FOR UPDATE'
    );
    $attemptStmt->execute([$provider, $providerReference]);
    $attempt = $attemptStmt->fetch();
    if (!$attempt) mg_fail('Delivery attempt not found.', 404);

    $providerEventId = mg_pppm_uuid();
    $payloadHash = hash('sha256', $raw);
    $pdo->prepare(
        "INSERT INTO pppm_provider_events
         (public_id, provider, external_event_id, event_type, delivery_id, delivery_attempt_id,
          payload_json, payload_hash, processing_status, received_at, processed_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'processed', NOW(), NOW(), NOW(), NOW())"
    )->execute([$providerEventId, $provider, $externalEventId, $eventType, (int) $attempt['delivery_db_id'], (int) $attempt['id'], $raw, $payloadHash]);

    $status = $statusMap[$eventType];
    $failureCode = $status === 'failed' ? trim((string) ($input['failure_code'] ?? 'provider_failed')) : null;
    $failureMessage = $status === 'failed' ? trim((string) ($input['failure_message'] ?? 'Delivery failed')) : null;
    $nextRetry = $status === 'failed' && (int) $attempt['attempt_number'] < 5 ? date('Y-m-d H:i:s', time() + (60 * (2 ** (int) $attempt['attempt_number']))) : null;
    $attemptStatus = $nextRetry ? 'retry_scheduled' : $status;

    $pdo->prepare(
        'UPDATE pppm_delivery_attempts
         SET status = ?, failure_code = ?, failure_message = ?, attempted_at = COALESCE(attempted_at, NOW()),
             next_retry_at = ?, completed_at = ?, updated_at = NOW()
         WHERE id = ?'
    )->execute([$attemptStatus, $failureCode, $failureMessage, $nextRetry, $nextRetry ? null : date('Y-m-d H:i:s'), (int) $attempt['id']]);

    $pdo->prepare(
        'UPDATE pppm_deliveries
         SET status = ?, provider_reference = ?, failure_code = ?, failure_message = ?,
             sent_at = CASE WHEN ? = ? THEN NOW() ELSE sent_at END,
             delivered_at = CASE WHEN ? = ? THEN NOW() ELSE delivered_at END,
             failed_at = CASE WHEN ? = ? THEN NOW() ELSE failed_at END,
             updated_at = NOW()
         WHERE id = ?'
    )->execute([$status, $providerReference, $failureCode, $failureMessage, $status, 'sent', $status, 'delivered', $status, 'failed', (int) $attempt['delivery_db_id']]);

    $itemStmt = $pdo->prepare('SELECT * FROM pppm_items WHERE id = ? LIMIT 1');
    $itemStmt->execute([(int) $attempt['pppm_item_id']]);
    $item = $itemStmt->fetch();
    if (in_array($status, ['sent','delivered'], true)) {
        $updated = mg_pppm_delivery_set_status($pdo, $item, $status, 0, 'delivery_' . $status, [
            'provider_event_id' => $providerEventId,
            'provider' => $provider,
        ]);
    } else {
        mg_pppm_record_event($pdo, $item, 'delivery_failed', (string) $item['status'], (string) $item['status'], null, null, [
            'provider_event_id' => $providerEventId,
            'retry_at' => $nextRetry,
        ]);
    }
    $pdo->commit();
    mg_ok(['provider_event_id' => $providerEventId, 'status' => $attemptStatus, 'duplicate' => false], 'Provider event processed.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail('Unable to process provider event.', 500);
}
