<?php
declare(strict_types=1);
require_once __DIR__ . '/_stamps.php';
$user = mg_require_api_user();
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();
$accountUserId = (int)$user['id'];
if (isset($input['account_user_id']) && $input['account_user_id'] !== '') {
    if (!mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
    $accountUserId = max(1, (int)$input['account_user_id']);
}
$entryId = trim((string)($input['entry_id'] ?? ''));
$sourceType = trim((string)($input['source_type'] ?? ''));
$sourceId = trim((string)($input['source_id'] ?? ''));
$idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
$reasonCode = trim((string)($input['reason_code'] ?? 'failed_send_void'));
$note = trim((string)($input['note'] ?? ''));
if ($idempotencyKey === '') mg_fail('idempotency_key is required.', 422);
if ($entryId === '' && ($sourceType === '' || $sourceId === '')) mg_fail('entry_id or source_type/source_id is required.', 422);

try {
    $pdo->beginTransaction();
    if ($entryId !== '') {
        $stmt = $pdo->prepare("SELECT * FROM stamp_ledger_entries WHERE public_id=? AND account_user_id=? AND entry_type='debit' LIMIT 1 FOR UPDATE");
        $stmt->execute([$entryId, $accountUserId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM stamp_ledger_entries WHERE source_type=? AND source_id=? AND account_user_id=? AND entry_type='debit' ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $stmt->execute([$sourceType, $sourceId, $accountUserId]);
    }
    $debit = $stmt->fetch();
    if (!$debit) throw new RuntimeException('Debit entry not found.');
    $amount = abs((int)$debit['delta']);
    if ($amount < 1) throw new RuntimeException('Debit entry cannot be voided.');
    $result = mg_stamp_credit($pdo, $accountUserId, (int)$user['id'], $amount, 'stamp:void:' . $idempotencyKey, [
        'entry_type' => 'void',
        'actor_type' => mg_api_user_has_permission($user, 'admin.stamps.manage') ? 'admin' : 'merchant',
        'source_type' => 'failed_send_void',
        'source_id' => (string)$debit['public_id'],
        'reference' => (string)($debit['reference'] ?? ''),
        'reason_code' => $reasonCode,
        'note' => $note !== '' ? $note : 'Void for failed or reversed send.',
        'metadata' => ['voided_entry_id'=>(string)$debit['public_id'],'voided_source_type'=>(string)$debit['source_type'],'voided_source_id'=>(string)($debit['source_id'] ?? '')],
    ]);
    $pdo->commit();
    mg_audit('stamps.voided','stamp_ledger',['voided_entry_id'=>(string)$debit['public_id'],'void_entry_id'=>$result['entry']['entry_id'] ?? null,'account_user_id'=>$accountUserId],(int)$user['id']);
    mg_ok($result, $result['idempotent'] ? 'Stamp void already recorded.' : 'Stamp debit voided.', $result['idempotent'] ? 200 : 201);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 409);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.void_failed','Unable to void Stamp debit.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to void Stamp debit.', 500);
}
