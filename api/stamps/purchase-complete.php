<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';
$user = mg_require_api_user();
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
if (!mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
$pdo = mg_db();
$accountUserId = max(1, (int)($input['account_user_id'] ?? 0));
if ($accountUserId < 1) mg_fail('account_user_id is required for admin Stamp completion.', 422);
$purchaseId = trim((string)($input['purchase_id'] ?? $input['id'] ?? ''));
$checkoutReference = trim((string)($input['checkout_reference'] ?? ''));
$providerStatus = strtolower(trim((string)($input['provider_status'] ?? 'paid')));
$idempotencySuffix = trim((string)($input['idempotency_suffix'] ?? 'admin'));
try {
    $pdo->beginTransaction();
    $purchase = mg_stamp_purchase_load($pdo, $accountUserId, $purchaseId, $checkoutReference, true);
    $result = mg_stamp_purchase_complete_verified($pdo, $purchase, (int)$user['id'], $providerStatus, 'admin-manual-' . (string)$purchase['public_id'], $idempotencySuffix);
    $pdo->commit();
    mg_audit('stamps.purchase_admin_completed', 'stamp_purchase', ['purchase_id'=>$result['purchase']['id'] ?? $purchaseId, 'account_user_id'=>$accountUserId, 'status'=>$result['purchase']['status'] ?? null, 'ledger_entry_id'=>$result['purchase']['credited_ledger_entry_id'] ?? null], (int)$user['id']);
    mg_ok($result, !empty($result['idempotent']) ? 'Stamp purchase already credited.' : 'Stamp purchase manually completed by admin.', !empty($result['idempotent']) ? 200 : 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.purchase_complete_failed','Unable to complete Stamp purchase.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to complete Stamp purchase.', 500);
}
