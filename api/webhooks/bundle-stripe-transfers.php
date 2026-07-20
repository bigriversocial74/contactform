<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/bundles/_provider_reconciliation.php';

$pdo = mg_db();
$payload = file_get_contents('php://input');
$signature = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

if (!is_string($payload) || $payload === '' || !mg_payment_verify_signature('stripe', $payload, $signature, $pdo)) {
    mg_fail('Invalid Stripe signature.', 400);
}

try {
    $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    $eventId = trim((string)($event['id'] ?? ''));
    $eventType = trim((string)($event['type'] ?? ''));
    $object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];
    $providerReference = trim((string)($object['id'] ?? ''));
    if ($eventId === '' || $eventType === '') {
        throw new InvalidArgumentException('Stripe event identity is missing.');
    }

    $pdo->beginTransaction();
    $existing = $pdo->prepare("SELECT processing_status FROM gift_bundle_provider_events WHERE provider_key='stripe' AND provider_event_reference=? LIMIT 1 FOR UPDATE");
    $existing->execute([$eventId]);
    if ($status = $existing->fetchColumn()) {
        $pdo->commit();
        mg_ok(['received' => true, 'duplicate' => true, 'status' => $status]);
    }

    $transfer = null;
    if ($providerReference !== '') {
        $stmt = $pdo->prepare("SELECT * FROM gift_bundle_settlement_transfers WHERE provider_key='stripe' AND provider_transfer_reference=? LIMIT 1 FOR UPDATE");
        $stmt->execute([$providerReference]);
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $eventPublicId = mg_public_uuid();
    $pdo->prepare("INSERT INTO gift_bundle_provider_events
        (public_id,provider_key,provider_event_reference,event_type,transfer_id,provider_transfer_reference,payload_json,processing_status,received_at)
        VALUES (?,'stripe',?,?,?,?,?,'received',NOW())")
        ->execute([$eventPublicId, $eventId, $eventType, $transfer['id'] ?? null, $providerReference !== '' ? $providerReference : null, $payload]);
    $providerEventId = (int)$pdo->lastInsertId();

    $processingStatus = 'ignored';
    if ($transfer && in_array($eventType, ['transfer.created', 'transfer.updated'], true)) {
        mg_bundle_provider_mark_succeeded($pdo, $transfer, $object);
        $processingStatus = 'processed';
    } elseif ($transfer && $eventType === 'transfer.reversed') {
        $pdo->prepare("UPDATE gift_bundle_settlement_transfers SET transfer_status='reversed',last_reconciled_at=NOW(),updated_at=NOW() WHERE id=?")
            ->execute([(int)$transfer['id']]);
        $pdo->prepare("UPDATE gift_bundle_component_settlements SET readiness_status='reversed',reversed_amount_cents=payable_amount_cents,reversed_at=COALESCE(reversed_at,NOW()),updated_at=NOW() WHERE id=?")
            ->execute([(int)$transfer['settlement_id']]);
        $pdo->prepare("UPDATE gift_bundle_settlement_adjustments SET adjustment_status='succeeded',provider_reversal_reference=?,response_snapshot_json=?,succeeded_at=COALESCE(succeeded_at,NOW()),updated_at=NOW() WHERE transfer_id=? AND adjustment_type IN ('reversal_request','reversal') AND adjustment_status IN ('dispatch_pending','submitted','approved')")
            ->execute([$providerReference, $payload, (int)$transfer['id']]);
        $processingStatus = 'processed';
    }

    $pdo->prepare("UPDATE gift_bundle_provider_events SET processing_status=?,processed_at=NOW() WHERE id=?")
        ->execute([$processingStatus, $providerEventId]);
    $pdo->commit();
    mg_ok(['received' => true, 'duplicate' => false, 'status' => $processingStatus]);
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail_unexpected($error, 'bundle.provider.webhook.failure', 'Unable to process the Stripe transfer event.', 500);
}
