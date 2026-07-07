<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';
require_once dirname(__DIR__) . '/payments/_webhook.php';

$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

$action = strtolower(trim((string)($input['action'] ?? '')));
$purchaseId = trim((string)($input['purchase_id'] ?? ''));
$providerEventId = trim((string)($input['provider_event_id'] ?? $input['event_id'] ?? ''));
$provider = strtolower(trim((string)($input['provider_key'] ?? 'stripe')));
if (!in_array($action, ['webhook_detail','reprocess_webhook','sync_provider_status','flag_paid_uncredited'], true)) mg_fail('Valid webhook recovery action is required.', 422);

function mg_stamp_recovery_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function mg_stamp_recovery_load_purchase(PDO $pdo, string $purchaseId, bool $lock = false): array
{
    if ($purchaseId === '') throw new RuntimeException('purchase_id is required.', 422);
    return mg_stamp_purchase_load_any($pdo, $purchaseId, $lock);
}

function mg_stamp_recovery_load_event(PDO $pdo, string $provider, string $providerEventId, bool $lock = false): array
{
    if ($providerEventId === '') throw new RuntimeException('provider_event_id is required.', 422);
    $stmt = $pdo->prepare('SELECT * FROM payment_webhook_events WHERE provider_key=? AND provider_event_id=? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$provider, $providerEventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) throw new RuntimeException('Webhook event not found.', 404);
    return $event;
}

function mg_stamp_recovery_event_payload(array $row): array
{
    $payload = (string)($row['payload_json'] ?? '');
    $event = json_decode($payload, true);
    if (!is_array($event)) $event = [];
    $ids = mg_payment_webhook_identifiers((string)$row['provider_key'], $event);
    return [
        'provider_key'=>(string)$row['provider_key'],
        'provider_event_id'=>(string)$row['provider_event_id'],
        'event_type'=>(string)$row['event_type'],
        'status'=>(string)$row['status'],
        'signature_valid'=>(int)($row['signature_valid'] ?? 0) === 1,
        'payload_hash'=>(string)($row['payload_hash'] ?? ''),
        'received_at'=>$row['received_at'] ?? null,
        'processed_at'=>$row['processed_at'] ?? null,
        'failure_message'=>(string)($row['failure_message'] ?? ''),
        'identifiers'=>[
            'source_type'=>(string)($ids['source_type'] ?? ''),
            'source_reference'=>(string)($ids['source_reference'] ?? ''),
            'stamp_purchase_id'=>(string)($ids['stamp_purchase_id'] ?? ''),
            'payment_intent_id'=>(string)($ids['payment_intent_id'] ?? ''),
            'checkout_session_id'=>(string)($ids['checkout_session_id'] ?? ''),
            'provider_intent_reference'=>(string)($ids['provider_intent_reference'] ?? ''),
            'provider_session_reference'=>(string)($ids['provider_session_reference'] ?? ''),
            'amount_cents'=>(int)($ids['amount_cents'] ?? 0),
            'currency'=>(string)($ids['currency'] ?? ''),
            'payment_status'=>(string)($ids['payment_status'] ?? ''),
        ],
        'payload_summary'=>[
            'id'=>(string)($event['id'] ?? ''),
            'type'=>(string)($event['type'] ?? ''),
            'object'=>(string)($ids['object']['object'] ?? ''),
            'object_id'=>(string)($ids['object']['id'] ?? ''),
        ],
    ];
}

$pdo = mg_db();
try {
    if (!mg_stamp_recovery_table_exists($pdo, 'payment_webhook_events')) throw new RuntimeException('Webhook event table is not available.', 409);

    if ($action === 'webhook_detail') {
        $event = mg_stamp_recovery_load_event($pdo, $provider, $providerEventId, false);
        mg_ok(['webhook_event'=>mg_stamp_recovery_event_payload($event)], 'Webhook event detail loaded.');
        return;
    }

    if ($action === 'sync_provider_status') {
        $purchase = mg_stamp_recovery_load_purchase($pdo, $purchaseId, false);
        $intent = mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id']);
        if (!$intent) throw new RuntimeException('Stamp purchase payment intent is missing.', 409);
        $providerKey = strtolower((string)($intent['provider_key'] ?? ''));
        $providerReference = trim((string)($intent['provider_intent_reference'] ?? ''));
        if ($providerKey === '' || $providerReference === '') throw new RuntimeException('Provider reference is missing for this payment intent.', 409);
        $providerIntent = mg_payment_provider_retrieve_intent($providerKey, $providerReference, $pdo);
        $status = mg_payment_normalize_intent_status((string)($providerIntent['status'] ?? 'created'));
        if ((string)$intent['status'] !== 'succeeded') {
            $pdo->prepare('UPDATE payment_intents SET status=?,updated_at=NOW() WHERE id=? AND status<>\'succeeded\'')->execute([$status, (int)$intent['id']]);
        }
        $updatedIntent = mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id']);
        $paidUncredited = $status === 'succeeded' && (string)$purchase['status'] !== 'credited';
        mg_audit('stamps.provider_status_sync', 'stamp_purchase', ['purchase_id'=>(string)$purchase['public_id'], 'payment_intent_id'=>(string)($intent['public_id'] ?? ''), 'provider_key'=>$providerKey, 'provider_intent_reference'=>$providerReference, 'provider_status'=>(string)($providerIntent['status'] ?? ''), 'normalized_status'=>$status, 'paid_uncredited'=>$paidUncredited], (int)$user['id']);
        mg_ok(['provider_intent'=>$providerIntent, 'payment_intent'=>$updatedIntent, 'paid_uncredited'=>$paidUncredited], $paidUncredited ? 'Provider reports paid; Stamp purchase still needs verified webhook or admin-only credit review.' : 'Provider payment status synced.');
        return;
    }

    if ($action === 'flag_paid_uncredited') {
        $purchase = mg_stamp_recovery_load_purchase($pdo, $purchaseId, false);
        $intent = mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id']);
        if (!$intent || (string)$intent['status'] !== 'succeeded' || (string)$purchase['status'] === 'credited') throw new RuntimeException('This Stamp purchase is not a paid-but-uncredited candidate.', 409);
        mg_audit('stamps.paid_uncredited_review_flagged', 'stamp_purchase', ['purchase_id'=>(string)$purchase['public_id'], 'account_user_id'=>(int)$purchase['account_user_id'], 'payment_intent_id'=>(string)($intent['public_id'] ?? ''), 'provider_intent_reference'=>(string)($intent['provider_intent_reference'] ?? '')], (int)$user['id']);
        mg_ok(['purchase'=>mg_stamp_purchase_payload($pdo, $purchase, null, $intent)], 'Paid-but-uncredited Stamp purchase flagged for admin review.');
        return;
    }

    if ($action === 'reprocess_webhook') {
        $pdo->beginTransaction();
        $eventRow = mg_stamp_recovery_load_event($pdo, $provider, $providerEventId, true);
        if ((int)($eventRow['signature_valid'] ?? 0) !== 1) throw new RuntimeException('Only signed webhook events can be reprocessed.', 409);
        if ((string)$eventRow['status'] === 'processed') throw new RuntimeException('Processed webhook events are already complete.', 409);
        if (!in_array((string)$eventRow['status'], ['failed','ignored','processing'], true)) throw new RuntimeException('Only failed, ignored, or processing webhook events can be reprocessed.', 409);
        $payload = (string)($eventRow['payload_json'] ?? '');
        $event = json_decode($payload, true);
        if (!is_array($event)) throw new RuntimeException('Stored webhook payload is invalid.', 409);
        if ((string)$eventRow['status'] === 'ignored') {
            $pdo->prepare("UPDATE payment_webhook_events SET status='processing',failure_message=NULL,processed_at=NULL WHERE provider_key=? AND provider_event_id=?")->execute([$provider, $providerEventId]);
        }
        $result = mg_payment_process_webhook_event($pdo, $provider, $event, $payload);
        mg_audit('stamps.webhook_reprocessed', 'payment_webhook_event', ['provider_key'=>$provider, 'provider_event_id'=>$providerEventId, 'result'=>$result], (int)$user['id']);
        $pdo->commit();
        mg_ok(['webhook_result'=>$result], 'Webhook event reprocessed.');
        return;
    }

    mg_fail('Unsupported webhook recovery action.', 422);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $status = (int)$error->getCode();
    if ($status < 400 || $status > 599) $status = 409;
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.webhook_recovery_failed','Stamp webhook recovery action failed.', ['action'=>$action,'purchase_id'=>$purchaseId,'provider_event_id'=>$providerEventId,'exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to complete Stamp webhook recovery action.', 500);
}
