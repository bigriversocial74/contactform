<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';
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
$purchaseId = trim((string)($input['purchase_id'] ?? $input['id'] ?? ''));
$checkoutReference = trim((string)($input['checkout_reference'] ?? ''));
$providerStatus = strtolower(trim((string)($input['provider_status'] ?? 'paid')));
$idempotencySuffix = trim((string)($input['idempotency_suffix'] ?? ''));
try {
    $pdo->beginTransaction();
    $purchase = mg_stamp_purchase_load($pdo, $accountUserId, $purchaseId, $checkoutReference, true);
    $result = mg_stamp_purchase_complete($pdo, $purchase, (int)$user['id'], $providerStatus, $idempotencySuffix);
    $pdo->commit();
    mg_audit('stamps.purchase_completed', 'stamp_purchase', ['purchase_id'=>$result['purchase']['id'] ?? $purchaseId, 'status'=>$result['purchase']['status'] ?? null, 'ledger_entry_id'=>$result['purchase']['credited_ledger_entry_id'] ?? null], (int)$user['id']);
    mg_ok($result, !empty($result['idempotent']) ? 'Stamp purchase already credited.' : 'Stamp purchase completed and credited.', !empty($result['idempotent']) ? 200 : 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.purchase_complete_failed','Unable to complete Stamp purchase.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to complete Stamp purchase.', 500);
}
