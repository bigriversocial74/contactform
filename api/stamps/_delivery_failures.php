<?php
declare(strict_types=1);

require_once __DIR__ . '/_stamps.php';

function mg_stamp_delivery_failure_find_debit(PDO $pdo, int $accountUserId, array $input, bool $lock = true): array
{
    $entryId = trim((string)($input['entry_id'] ?? $input['stamp_ledger_entry_id'] ?? ''));
    $sourceType = trim((string)($input['source_type'] ?? ''));
    $sourceId = trim((string)($input['source_id'] ?? ''));
    $suffix = $lock ? ' FOR UPDATE' : '';
    if ($entryId !== '') {
        $stmt = $pdo->prepare("SELECT * FROM stamp_ledger_entries WHERE public_id=? AND account_user_id=? AND entry_type='debit' LIMIT 1" . $suffix);
        $stmt->execute([$entryId, $accountUserId]);
    } elseif ($sourceType !== '' && $sourceId !== '') {
        $stmt = $pdo->prepare("SELECT * FROM stamp_ledger_entries WHERE source_type=? AND source_id=? AND account_user_id=? AND entry_type='debit' ORDER BY id DESC LIMIT 1" . $suffix);
        $stmt->execute([$sourceType, $sourceId, $accountUserId]);
    } else {
        mg_fail('entry_id or source_type/source_id is required.', 422);
    }
    $debit = $stmt->fetch();
    if (!$debit) mg_fail('Stamp debit entry not found.', 404);
    return $debit;
}

function mg_stamp_delivery_failure_void(PDO $pdo, int $accountUserId, int $actorUserId, array $input): array
{
    $debit = mg_stamp_delivery_failure_find_debit($pdo, $accountUserId, $input, true);
    $amount = abs((int)$debit['delta']);
    if ($amount < 1) mg_fail('Stamp debit entry cannot be returned.', 409);
    $provider = trim((string)($input['provider'] ?? 'internal')) ?: 'internal';
    $failureCode = trim((string)($input['failure_code'] ?? 'delivery_failed')) ?: 'delivery_failed';
    $failureMessage = trim((string)($input['failure_message'] ?? $input['note'] ?? 'Delivery failed.')) ?: 'Delivery failed.';
    $eventId = trim((string)($input['event_id'] ?? $input['provider_event_id'] ?? $failureCode . ':' . (string)$debit['public_id']));
    $idempotencyKey = 'stamp:delivery-failure:' . $provider . ':' . $eventId . ':' . (string)$debit['public_id'];
    return mg_stamp_credit($pdo, $accountUserId, $actorUserId, $amount, $idempotencyKey, [
        'entry_type' => 'void',
        'actor_type' => 'system',
        'source_type' => 'delivery_failure_void',
        'source_id' => (string)$debit['public_id'],
        'reference' => (string)($debit['reference'] ?? ''),
        'reason_code' => $failureCode,
        'note' => $failureMessage,
        'metadata' => [
            'provider' => $provider,
            'provider_event_id' => $eventId,
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage,
            'voided_entry_id' => (string)$debit['public_id'],
            'voided_source_type' => (string)$debit['source_type'],
            'voided_source_id' => (string)($debit['source_id'] ?? ''),
        ],
    ]);
}
