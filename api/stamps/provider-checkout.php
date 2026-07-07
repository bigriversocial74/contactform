<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';

$user = mg_require_api_user();
mg_require_method('GET');
$purchaseId = trim((string)($_GET['purchase_id'] ?? $_GET['id'] ?? ''));
if ($purchaseId === '') mg_fail('purchase_id is required.', 422);
$pdo = mg_db();
try {
    $purchase = mg_stamp_purchase_load($pdo, (int)$user['id'], $purchaseId, '', false);
    $intent = mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id']);
    if (!$intent) mg_fail('Stamp purchase payment intent is missing.', 409);
    $providerCheckout = mg_stamp_purchase_provider_checkout_payload($pdo, $purchase, $intent);
    $payload = mg_stamp_purchase_payload($pdo, $purchase, null, $intent);
    $payload['provider_checkout'] = $providerCheckout;
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie, Authorization');
    mg_ok($payload, 'Stamp provider checkout loaded.');
} catch (Throwable $error) {
    mg_security_log('error','stamps.provider_checkout_failed','Unable to load Stamp provider checkout.', ['purchase_id'=>$purchaseId,'exception_class'=>$error::class,'message'=>$error->getMessage()], (int)$user['id']);
    mg_fail('Unable to load Stamp provider checkout.', 500);
}
