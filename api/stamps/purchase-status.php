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
    mg_ok(mg_stamp_purchase_payload($pdo, $purchase, null, $intent), 'Stamp purchase checkout loaded.');
} catch (Throwable $error) {
    mg_security_log('warning','stamps.purchase_status_failed','Unable to load Stamp purchase checkout status.', ['purchase_id'=>$purchaseId,'exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to load Stamp purchase checkout.', 500);
}
