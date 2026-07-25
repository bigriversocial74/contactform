<?php
declare(strict_types=1);

require_once __DIR__ . '/_homeserver.php';

mg_require_method('POST');
$device = mg_homeserver_require_device('homeserver.sync.write');
$input = mg_homeserver_input();
$operations = $input['operations'] ?? null;
if (!is_array($operations) || $operations === []) mg_fail('At least one synchronization operation is required.', 422);
if (count($operations) > MG_HOMESERVER_MAX_SYNC_OPERATIONS) mg_fail('Synchronization batch is too large.', 422);

$validated = [];
foreach ($operations as $index => $operation) {
    if (!is_array($operation)) mg_fail('Synchronization operation is invalid.', 422, ['index' => $index]);
    $idempotencyKey = trim((string)($operation['idempotency_key'] ?? ''));
    $operationType = strtolower(trim((string)($operation['operation_type'] ?? ''));
    $payload = is_array($operation['payload'] ?? null) ? $operation['payload'] : [];
    if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 190 || preg_match('/^[A-Za-z0-9_.:-]+$/', $idempotencyKey) !== 1) {
        mg_fail('Synchronization idempotency key is invalid.', 422, ['index' => $index]);
    }
    if ($operationType === '' || mb_strlen($operationType) > 100 || preg_match('/^[a-z0-9_.-]+$/', $operationType) !== 1) {
        mg_fail('Synchronization operation type is invalid.', 422, ['index' => $index]);
    }
    $validated[] = [
        'idempotency_key' => $idempotencyKey,
        'operation_type' => $operationType,
        'payload' => $payload,
        'request_hash' => hash('sha256', mg_homeserver_json([
            'operation_type' => $operationType,
            'payload' => $payload,
        ])),
    ];
}

$pdo = mg_db();
$receipts = [];

try {
    $pdo->beginTransaction();
    foreach ($validated as $operation) {
        $idempotencyKey = $operation['idempotency_key'];
        $operationType = $operation['operation_type'];
        $payload = $operation['payload'];
        $requestHash = $operation['request_hash'];
        $existingStmt = $pdo->prepare('SELECT * FROM homeserver_sync_receipts WHERE device_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
        $existingStmt->execute([(int)$device['id'], $idempotencyKey]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if (!hash_equals((string)$existing['request_hash'], $requestHash)) {
                $receipts[] = [
                    'idempotency_key' => $idempotencyKey,
                    'operation_type' => $operationType,
                    'disposition' => 'rejected',
                    'reason_code' => 'idempotency_conflict',
                    'duplicate' => true,
                    'response' => ['accepted' => false],
                ];
                continue;
            }
            $response = json_decode((string)($existing['response_json'] ?? '{}'), true);
            $receipts[] = [
                'receipt_id' => (string)$existing['public_id'],
                'idempotency_key' => $idempotencyKey,
                'operation_type' => (string)$existing['operation_type'],
                'disposition' => (string)$existing['disposition'],
                'reason_code' => $existing['reason_code'],
                'duplicate' => true,
                'response' => is_array($response) ? $response : [],
            ];
            continue;
        }

        $result = mg_homeserver_sync_disposition($operationType, $payload);
        $receiptId = mg_homeserver_public_uuid();
        $pdo->prepare('INSERT INTO homeserver_sync_receipts (public_id,device_id,idempotency_key,operation_type,request_hash,disposition,reason_code,response_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')
            ->execute([
                $receiptId,
                (int)$device['id'],
                $idempotencyKey,
                $operationType,
                $requestHash,
                $result['disposition'],
                $result['reason_code'],
                mg_homeserver_json($result['response']),
            ]);
        $receipts[] = [
            'receipt_id' => $receiptId,
            'idempotency_key' => $idempotencyKey,
            'operation_type' => $operationType,
            'disposition' => $result['disposition'],
            'reason_code' => $result['reason_code'],
            'duplicate' => false,
            'response' => $result['response'],
        ];
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail_unexpected($error, 'homeserver.sync_failed', 'Unable to process HomeServer synchronization.', 500, ['device_id' => $device['public_id'] ?? null], (int)($device['owner_user_id'] ?? 0));
}

mg_audit('homeserver.sync_batch_processed', 'homeserver_device', [
    'device_id' => (string)$device['public_id'],
    'operation_count' => count($validated),
    'receipt_count' => count($receipts),
], (int)$device['owner_user_id']);

mg_ok(['receipts' => $receipts, 'cloud_time_utc' => gmdate(DATE_ATOM)]);
