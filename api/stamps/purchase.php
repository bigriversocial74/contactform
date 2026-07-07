<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';
$user = mg_require_api_user();
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();
$accountUserId = (int)$user['id'];
$bundleId = trim((string)($input['bundle_id'] ?? $input['id'] ?? ''));
$bundleKey = trim((string)($input['bundle_key'] ?? ''));
$idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
if (($bundleId === '' && $bundleKey === '') || $idempotencyKey === '') mg_fail('Stamp bundle and idempotency key are required.', 422);
try {
    $created = false;
    $pdo->beginTransaction();
    $existing = $pdo->prepare('SELECT sp.* FROM stamp_purchases sp WHERE sp.account_user_id=? AND sp.idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([$accountUserId, $idempotencyKey]);
    $purchase = $existing->fetch();
    if (!$purchase) {
        if ($bundleId !== '') {
            $stmt = $pdo->prepare('SELECT * FROM stamp_bundles WHERE public_id=? AND status=? LIMIT 1 FOR UPDATE');
            $stmt->execute([$bundleId, 'active']);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM stamp_bundles WHERE bundle_key=? AND status=? LIMIT 1 FOR UPDATE');
            $stmt->execute([$bundleKey, 'active']);
        }
        $bundle = $stmt->fetch();
        if (!$bundle) throw new RuntimeException('Stamp bundle not found.');
        $purchasePublicId = mg_public_uuid();
        $checkoutReference = 'stamp:purchase:' . $purchasePublicId;
        $pdo->prepare('INSERT INTO stamp_purchases (public_id,account_user_id,bundle_id,bundle_key,label_snapshot,stamps_snapshot,price_cents_snapshot,currency_snapshot,status,checkout_reference,idempotency_key,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,JSON_OBJECT(),NOW(),NOW())')
            ->execute([$purchasePublicId,$accountUserId,(int)$bundle['id'],(string)$bundle['bundle_key'],(string)$bundle['label'],(int)$bundle['stamps'],(int)$bundle['price_cents'],(string)$bundle['currency'],'checkout_created',$checkoutReference,$idempotencyKey]);
        $existing->execute([$accountUserId, $idempotencyKey]);
        $purchase = $existing->fetch();
        $created = true;
    }
    $intent = mg_stamp_purchase_create_intent($pdo, $purchase, $idempotencyKey);
    $notification = $created ? mg_stamp_receipt_notify_merchant($pdo, $purchase, 'created', $accountUserId, ['payment_intent_id'=>(string)($intent['public_id'] ?? '')]) : ['created'=>false,'reason'=>'existing_purchase'];
    $result = mg_stamp_purchase_payload($pdo, $purchase, null, $intent) + ['receipt_notification'=>$notification];
    $pdo->commit();
    mg_audit('stamps.purchase_checkout_created', 'stamp_purchase', ['purchase_type'=>'bulk_stamp_purchase','purchase_id'=>(string)$purchase['public_id'],'bundle_key'=>(string)$purchase['bundle_key'],'status'=>(string)$purchase['status'],'payment_intent_id'=>(string)($intent['public_id'] ?? ''),'receipt_notification'=>$notification], $accountUserId);
    mg_ok($result, 'Stamp bundle checkout created. Complete payment before ledger credit.', 202);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 409);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.purchase_failed','Unable to create Stamp purchase checkout.', ['exception_class'=>$error::class], $accountUserId);
    mg_fail('Unable to create Stamp purchase checkout.', 500);
}
