<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';

$user = mg_require_api_user();
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
if (mg_payment_is_live() || mg_payment_provider_key() !== 'sandbox') {
    mg_fail('Sandbox Stamp checkout confirmation is unavailable.', 403);
}
$purchaseId = trim((string)($input['purchase_id'] ?? $input['id'] ?? ''));
if ($purchaseId === '') mg_fail('purchase_id is required.', 422);
$pdo = mg_db();
try {
    $pdo->beginTransaction();
    $purchase = mg_stamp_purchase_load($pdo, (int)$user['id'], $purchaseId, '', true);
    $intent = mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id'], true);
    if (!$intent) throw new RuntimeException('Stamp purchase payment intent is missing.');
    if ((string)$intent['provider_key'] !== 'sandbox') mg_fail('Stamp purchase is not a sandbox checkout.', 403);
    if ((int)$intent['amount_cents'] !== (int)$purchase['price_cents_snapshot'] || !hash_equals((string)$intent['currency'], (string)$purchase['currency_snapshot'])) {
        throw new RuntimeException('Stamp purchase payment intent does not match purchase snapshot.');
    }
    if (in_array((string)$intent['status'], ['failed','cancelled'], true)) throw new RuntimeException('Stamp purchase payment intent cannot be confirmed.');
    $providerReference = 'sandbox_stamp_' . bin2hex(random_bytes(8));
    $result = mg_stamp_purchase_complete_verified($pdo, $purchase, (int)$user['id'], 'paid', $providerReference, 'sandbox:' . (string)$purchase['public_id']);
    $pdo->commit();
    mg_audit('stamps.purchase_sandbox_completed','stamp_purchase', ['purchase_id'=>(string)$purchase['public_id'],'payment_intent_id'=>(string)($intent['public_id'] ?? ''),'provider_reference'=>$providerReference], (int)$user['id']);
    mg_ok($result, !empty($result['idempotent']) ? 'Sandbox Stamp purchase already credited.' : 'Sandbox Stamp payment completed and credited.', !empty($result['idempotent']) ? 200 : 201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.sandbox_checkout_failed','Unable to confirm sandbox Stamp checkout.', ['purchase_id'=>$purchaseId,'exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to confirm sandbox Stamp checkout.', 500);
}
