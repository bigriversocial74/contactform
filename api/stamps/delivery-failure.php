<?php
declare(strict_types=1);
require_once __DIR__ . '/_delivery_failures.php';
$user = mg_require_api_user();
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();
$accountUserId = (int)$user['id'];
if (isset($input['account_user_id']) && $input['account_user_id'] !== '') {
    if (!mg_api_user_has_permission($user, 'admin.stamps.manage') && !mg_api_user_has_permission($user, 'admin.stamps.debit')) mg_fail('Permission denied.', 403);
    $accountUserId = max(1, (int)$input['account_user_id']);
}
try {
    $pdo->beginTransaction();
    $result = mg_stamp_delivery_failure_void($pdo, $accountUserId, (int)$user['id'], $input);
    $pdo->commit();
    mg_audit('stamps.delivery_failure_void', 'stamp_ledger', ['entry_id'=>$result['entry']['entry_id'] ?? null, 'account_user_id'=>$accountUserId, 'reason_code'=>$result['entry']['reason_code'] ?? null], (int)$user['id']);
    mg_ok($result, $result['idempotent'] ? 'Delivery failure return already recorded.' : 'Delivery failure returned Stamps.', $result['idempotent'] ? 200 : 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.delivery_failure_return_failed','Unable to return Stamps for delivery failure.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to return Stamps for delivery failure.', 500);
}
