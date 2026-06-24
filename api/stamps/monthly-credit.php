<?php
declare(strict_types=1);
require_once __DIR__ . '/_stamps.php';
$user = mg_require_api_user();
mg_require_method('POST');
if (!mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
$input = mg_input();
mg_require_csrf_for_write($input);
$accountUserId = max(1, (int)($input['account_user_id'] ?? 0));
$planId = strtolower(trim((string)($input['plan_id'] ?? '')));
$override = isset($input['stamps']) && $input['stamps'] !== '' ? max(1, (int)$input['stamps']) : null;
if ($accountUserId < 1 || $planId === '') mg_fail('account_user_id and plan_id are required.', 422);
$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $result = mg_stamp_credit_monthly_allowance($pdo, $accountUserId, (int)$user['id'], $planId, $override);
    $pdo->commit();
    mg_ok($result, $result['idempotent'] ? 'Monthly Stamp allowance already credited.' : 'Monthly Stamp allowance credited.', 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'stamps.monthly_credit_failed', 'Unable to credit monthly Stamp allowance.', ['exception_class' => $error::class], (int)$user['id']);
    mg_fail('Unable to credit monthly Stamp allowance.', 500);
}
