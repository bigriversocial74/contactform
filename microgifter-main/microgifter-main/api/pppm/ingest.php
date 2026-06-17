<?php
declare(strict_types=1);

require_once __DIR__ . '/_pppm.php';

mg_require_method('POST');
$user = mg_require_permission('pppm.ingest');
$input = mg_input();
mg_require_csrf_for_write($input);

$sourcePublicId = strtolower(trim((string) ($input['source_id'] ?? '')));
$externalEventId = mg_pppm_text($input['external_event_id'] ?? '', 'external event ID', 190);
$eventType = mg_pppm_text($input['event_type'] ?? 'issuance', 'event type', 100);
$source = mg_pppm_source_owned((int) $user['id'], $sourcePublicId);
$quantity = max(1, min(1000, (int) ($input['quantity'] ?? 1)));
$itemType = trim((string) ($input['item_type'] ?? 'gift'));
$fundingType = trim((string) ($input['funding_type'] ?? 'other'));
$title = mg_pppm_text($input['title'] ?? '', 'title', 160);
$description = mg_pppm_text($input['description'] ?? '', 'description', 5000, false);
$sourceReference = mg_pppm_text($input['source_reference'] ?? '', 'source reference', 190, false);
$sourceLineReference = mg_pppm_text($input['source_line_reference'] ?? '', 'source line reference', 190, false);
$recipientName = mg_pppm_text($input['recipient_name'] ?? '', 'recipient name', 160, false);
$recipientExternalId = mg_pppm_text($input['recipient_external_id'] ?? '', 'recipient external ID', 190, false);
$recipientUserId = isset($input['recipient_user_id']) ? (int) $input['recipient_user_id'] : null;
$merchantUserId = isset($input['merchant_user_id']) ? (int) $input['merchant_user_id'] : null;
$unitValue = max(0, (int) ($input['unit_value_cents'] ?? 0));
$currency = strtoupper(substr(trim((string) ($input['currency'] ?? 'USD')), 0, 3));
$termsJson = mg_pppm_json($input['terms'] ?? null);
$metadataJson = mg_pppm_json($input['metadata'] ?? null);
$payloadJson = mg_pppm_json($input);
$payloadHash = hash('sha256', (string) $payloadJson);

$allowedItemTypes = ['gift','prize','reward','voucher','entitlement','reservation','credit','other'];
$allowedFundingTypes = ['customer_purchase','merchant_funded','sponsor_funded','platform_funded','promotional','earned_reward','free','other'];
if (!in_array($itemType, $allowedItemTypes, true) || !in_array($fundingType, $allowedFundingTypes, true)) {
    mg_fail('Invalid PPPM issuance type.', 422);
}

$pdo = mg_db();
try {
    $pdo->beginTransaction();

    $existingStmt = $pdo->prepare(
        'SELECT pse.public_id AS event_public_id, pir.public_id AS request_public_id
         FROM pppm_source_events pse
         LEFT JOIN pppm_issuance_requests pir ON pir.source_event_id = pse.id
         WHERE pse.source_id = ? AND pse.external_event_id = ? LIMIT 1 FOR UPDATE'
    );
    $existingStmt->execute([(int) $source['id'], $externalEventId]);
    $existing = $existingStmt->fetch();
    if ($existing) {
        $pdo->commit();
        mg_ok([
            'event_id' => (string) $existing['event_public_id'],
            'issuance_request_id' => $existing['request_public_id'] !== null ? (string) $existing['request_public_id'] : null,
            'duplicate' => true,
        ], 'Source event already processed.');
    }

    $eventPublicId = mg_pppm_uuid();
    $eventStmt = $pdo->prepare(
        "INSERT INTO pppm_source_events
         (public_id, source_id, external_event_id, event_type, payload_json, payload_hash, processing_status, received_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, 'validated', NOW(), NOW(), NOW())"
    );
    $eventStmt->execute([$eventPublicId, (int) $source['id'], $externalEventId, $eventType, $payloadJson, $payloadHash]);
    $sourceEventDbId = (int) $pdo->lastInsertId();

    $requestPublicId = mg_pppm_uuid();
    $requestStmt = $pdo->prepare(
        "INSERT INTO pppm_issuance_requests
         (public_id, source_id, source_event_id, issuer_user_id, merchant_user_id, source_reference, source_line_reference,
          item_type, funding_type, quantity, unit_value_cents, currency, recipient_user_id, recipient_external_id,
          recipient_name, title, description, terms_snapshot_json, metadata_json, status, issued_count,
          requested_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'issuing', 0, NOW(), NOW(), NOW())"
    );
    $requestStmt->execute([
        $requestPublicId, (int) $source['id'], $sourceEventDbId, (int) $user['id'], $merchantUserId,
        $sourceReference, $sourceLineReference, $itemType, $fundingType, $quantity, $unitValue, $currency,
        $recipientUserId, $recipientExternalId, $recipientName, $title, $description, $termsJson, $metadataJson,
    ]);
    $requestDbId = (int) $pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        "INSERT INTO pppm_items
         (public_id, issuance_request_id, source_id, unit_sequence, item_type, funding_type, issuer_user_id,
          merchant_user_id, owner_user_id, recipient_user_id, recipient_external_id, source_reference,
          source_line_reference, title_snapshot, description_snapshot, value_cents_snapshot, currency_snapshot,
          terms_snapshot_json, metadata_snapshot_json, status, version_no, issued_at, assigned_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, NOW(), NOW())"
    );

    $items = [];
    for ($sequence = 1; $sequence <= $quantity; $sequence++) {
        $itemPublicId = mg_pppm_item_id();
        $status = ($recipientUserId || $recipientExternalId) ? 'assigned' : 'available';
        $assignedAt = $status === 'assigned' ? date('Y-m-d H:i:s') : null;
        $itemStmt->execute([
            $itemPublicId, $requestDbId, (int) $source['id'], $sequence, $itemType, $fundingType,
            (int) $user['id'], $merchantUserId, (int) $user['id'], $recipientUserId, $recipientExternalId,
            $sourceReference, $sourceLineReference, $title, $description, $unitValue, $currency,
            $termsJson, $metadataJson, $status, $assignedAt,
        ]);
        $itemDbId = (int) $pdo->lastInsertId();
        $item = $pdo->query('SELECT * FROM pppm_items WHERE id = ' . $itemDbId)->fetch();
        mg_pppm_record_event($pdo, $item, 'issued', null, $status, (int) $user['id'], $sourceEventDbId, [
            'issuance_request_id' => $requestPublicId,
            'unit_sequence' => $sequence,
        ]);
        $items[] = mg_pppm_public_item($item);
    }

    $pdo->prepare("UPDATE pppm_issuance_requests SET status = 'issued', issued_count = ?, completed_at = NOW(), updated_at = NOW() WHERE id = ?")
        ->execute([$quantity, $requestDbId]);
    $pdo->prepare("UPDATE pppm_source_events SET processing_status = 'processed', processed_at = NOW(), updated_at = NOW() WHERE id = ?")
        ->execute([$sourceEventDbId]);

    $pdo->commit();
    mg_audit('pppm.issuance_completed', 'pppm_issuance_request', [
        'issuance_request_id' => $requestPublicId,
        'source_id' => $sourcePublicId,
        'quantity' => $quantity,
    ], (int) $user['id']);
    mg_ok([
        'event_id' => $eventPublicId,
        'issuance_request_id' => $requestPublicId,
        'items' => $items,
        'duplicate' => false,
    ], 'PPPM items issued.', 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    mg_security_log('error', 'pppm.ingestion_failed', 'PPPM ingestion failed.', [
        'source_id' => $sourcePublicId,
        'external_event_id' => $externalEventId,
        'exception_type' => get_class($e),
    ], (int) $user['id']);
    mg_fail('Unable to process the PPPM source event.', 500);
}
