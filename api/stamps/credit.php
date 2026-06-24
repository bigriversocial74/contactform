<?php
declare(strict_types=1);
require_once __DIR__ . '/_stamps.php';
$user = mg_require_api_user();
mg_require_method('POST');
if (!mg_api_user_has_permission($user, 'admin.stamps.manage') && !mg_api_user_has_permission($user, 'stamps.credit')) {
    mg_fail('Permission denied.', 403);
}
$input = mg_input();
mg_require_csrf_for_write($input);
$accountUserId = max(1, (int)($input['account_user_id'] ?? 0));
if ($accountUserId < 1) {
    mg_fail('account_user_id is required.', 422);
}
$stamps = max(1, (int)($input['stamps'] ?? 0));
$idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
if ($idempotencyKey === '') {
    mg_fail('idempotency_key is required.', 422);
}
$entryType = (string)($input['entry_type'] ?? 'credit');
if (!in_array($entryType, ['credit','void','adjustment'], true)) {
    mg_fail('entry_type must be credit, void, or adjustment.', 422);
}
$reasonCode = trim((string)($input['reason_code'] ?? ''));
if ($reasonCode === '') {
    mg_fail('reason_code is required.', 422);
}
$pdo = mg_db();
$pdo->beginTransaction();
try {
    $result = mg_stamp_credit($pdo, $accountUserId, (int)$user['id'], $stamps, $idempotencyKey, [
        'entry_type' => $entryType,
        'actor_type' => 'admin',
        'source_type' => (string)($input['source_type'] ?? 'admin_adjustment'),
        'source_id' => isset($input['source_id']) ? (string)$input['source_id'] : null,
        'reference' => isset($input['reference']) ? (string)$input['reference'] : null,
        'reason_code' => $reasonCode,
        'note' => isset($input['note']) ? (string)$input['note'] : null,
        'metadata' => is_array($input['metadata'] ?? null) ? $input['metadata'] : [],
    ]);
    $pdo->commit();
    mg_ok($result, $result['idempotent'] ? 'Stamp credit already recorded.' : 'Stamps credited.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (function_exists('mg_security_log')) {
        mg_security_log('error', 'stamps.credit_failed', 'Stamp credit failed.', ['exception' => $e->getMessage()], (int)$user['id']);
    }
    mg_fail('Unable to credit Stamps.', 500);
}
