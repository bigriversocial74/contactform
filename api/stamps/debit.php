<?php
declare(strict_types=1);
require_once __DIR__ . '/_stamps.php';
$user = mg_require_api_user();
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$actionKey = trim((string)($input['action_key'] ?? ''));
if ($actionKey === '') {
    mg_fail('action_key is required.', 422);
}
$idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
if ($idempotencyKey === '') {
    mg_fail('idempotency_key is required.', 422);
}
$accountUserId = (int)$user['id'];
if (isset($input['account_user_id']) && $input['account_user_id'] !== '') {
    if (!mg_api_user_has_permission($user, 'admin.stamps.manage')) {
        mg_fail('Permission denied.', 403);
    }
    $accountUserId = max(1, (int)$input['account_user_id']);
} elseif (!mg_api_user_has_permission($user, 'stamps.debit') && !mg_api_user_has_permission($user, 'admin.stamps.manage')) {
    mg_fail('Permission denied.', 403);
}
$quantity = max(1, (int)($input['quantity'] ?? 1));
$pdo = mg_db();
$pdo->beginTransaction();
try {
    $result = mg_stamp_debit($pdo, $accountUserId, (int)$user['id'], $actionKey, $quantity, $idempotencyKey, [
        'source_type' => (string)($input['source_type'] ?? 'send'),
        'source_id' => isset($input['source_id']) ? (string)$input['source_id'] : null,
        'reference' => isset($input['reference']) ? (string)$input['reference'] : null,
        'reason_code' => isset($input['reason_code']) ? (string)$input['reason_code'] : null,
        'note' => isset($input['note']) ? (string)$input['note'] : null,
        'allow_negative' => !empty($input['allow_negative']) && mg_api_user_has_permission($user, 'admin.stamps.manage'),
        'metadata' => is_array($input['metadata'] ?? null) ? $input['metadata'] : [],
    ]);
    $pdo->commit();
    mg_ok($result, $result['idempotent'] ? 'Stamp debit already recorded.' : 'Stamps debited.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (function_exists('mg_security_log')) {
        mg_security_log('error', 'stamps.debit_failed', 'Stamp debit failed.', ['exception' => $e->getMessage()], (int)$user['id']);
    }
    mg_fail('Unable to debit Stamps.', 500);
}
