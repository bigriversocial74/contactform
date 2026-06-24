<?php
declare(strict_types=1);
require_once __DIR__ . '/_stamps.php';
$user = mg_require_api_user();
mg_require_method('POST');
if (!mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
$input = mg_input();
mg_require_csrf_for_write($input);
$accountUserId = max(1, (int)($input['account_user_id'] ?? 0));
$delta = (int)($input['delta'] ?? 0);
$idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
$reasonCode = trim((string)($input['reason_code'] ?? ''));
$note = trim((string)($input['note'] ?? ''));
if ($accountUserId < 1 || $delta === 0 || $idempotencyKey === '' || $reasonCode === '') mg_fail('account_user_id, non-zero delta, idempotency_key, and reason_code are required.', 422);
$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $result = mg_stamp_post_entry($pdo, $accountUserId, (int)$user['id'], 'admin', 'adjustment', $delta, [
        'idempotency_key' => 'stamp:adjustment:' . $idempotencyKey,
        'stamp_value' => abs($delta),
        'quantity' => 1,
        'source_type' => 'admin_adjustment',
        'source_id' => (string)$user['id'],
        'reference' => 'admin_adjustment',
        'reason_code' => $reasonCode,
        'note' => $note,
        'allow_negative' => !empty($input['allow_negative']),
        'metadata' => ['requested_delta'=>$delta],
    ]);
    $pdo->commit();
    mg_audit('stamps.admin_adjustment','stamp_ledger',['entry_id'=>$result['entry']['entry_id'] ?? null,'account_user_id'=>$accountUserId,'delta'=>$delta,'reason_code'=>$reasonCode],(int)$user['id']);
    mg_ok($result, $result['idempotent'] ? 'Stamp adjustment already recorded.' : 'Stamp adjustment recorded.', $result['idempotent'] ? 200 : 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.adjustment_failed','Unable to record Stamp adjustment.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to record Stamp adjustment.', 500);
}
