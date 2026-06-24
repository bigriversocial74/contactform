<?php
declare(strict_types=1);
require_once __DIR__ . '/_stamps.php';
$user = mg_require_api_user();
mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();
$accountUserId = (int)$user['id'];
$bundleId = trim((string)($input['bundle_id'] ?? $input['id'] ?? ''));
$bundleKey = trim((string)($input['bundle_key'] ?? ''));
$idempotencyKey = trim((string)($input['idempotency_key'] ?? ''));
$confirm = !empty($input['confirm']) || !empty($input['sandbox_confirm']);
if (($bundleId === '' && $bundleKey === '') || $idempotencyKey === '') mg_fail('Stamp bundle and idempotency key are required.', 422);

try {
    $pdo->beginTransaction();
    $existing = $pdo->prepare('SELECT sp.*, sb.public_id bundle_public_id FROM stamp_purchases sp INNER JOIN stamp_bundles sb ON sb.id=sp.bundle_id WHERE sp.account_user_id=? AND sp.idempotency_key=? LIMIT 1 FOR UPDATE');
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
        $checkoutReference = 'stamp-checkout:' . $purchasePublicId;
        $pdo->prepare('INSERT INTO stamp_purchases (public_id,account_user_id,bundle_id,bundle_key,label_snapshot,stamps_snapshot,price_cents_snapshot,currency_snapshot,status,checkout_reference,idempotency_key,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,JSON_OBJECT(),NOW(),NOW())')
            ->execute([$purchasePublicId,$accountUserId,(int)$bundle['id'],(string)$bundle['bundle_key'],(string)$bundle['label'],(int)$bundle['stamps'],(int)$bundle['price_cents'],(string)$bundle['currency'],'checkout_created',$checkoutReference,$idempotencyKey]);
        $existing->execute([$accountUserId, $idempotencyKey]);
        $purchase = $existing->fetch();
    }

    $credited = null;
    if ($confirm && !in_array((string)$purchase['status'], ['credited'], true)) {
        $credit = mg_stamp_credit($pdo, $accountUserId, $accountUserId, (int)$purchase['stamps_snapshot'], 'stamp:purchase:' . (string)$purchase['public_id'] . ':' . (string)$purchase['bundle_key'], [
            'actor_type' => 'merchant',
            'source_type' => 'bulk_stamp_purchase',
            'source_id' => (string)$purchase['public_id'],
            'reference' => (string)$purchase['bundle_key'],
            'reason_code' => 'bundle_purchase',
            'metadata' => ['purchase_id'=>(string)$purchase['public_id'],'bundle_key'=>(string)$purchase['bundle_key'],'price_cents'=>(int)$purchase['price_cents_snapshot']],
        ]);
        $credited = $credit;
        $pdo->prepare('UPDATE stamp_purchases SET status=?,credited_ledger_entry_public_id=?,paid_at=COALESCE(paid_at,NOW()),credited_at=NOW(),updated_at=NOW() WHERE id=?')
            ->execute(['credited', (string)($credit['entry']['entry_id'] ?? ''), (int)$purchase['id']]);
        $purchase['status'] = 'credited';
        $purchase['credited_ledger_entry_public_id'] = (string)($credit['entry']['entry_id'] ?? '');
    }

    $pdo->commit();
    mg_audit('stamps.purchase_created', 'stamp_purchase', ['purchase_id'=>(string)$purchase['public_id'],'bundle_key'=>(string)$purchase['bundle_key'],'status'=>(string)$purchase['status']], $accountUserId);
    mg_ok([
        'purchase' => [
            'id' => (string)$purchase['public_id'],
            'bundle_key' => (string)$purchase['bundle_key'],
            'label' => (string)$purchase['label_snapshot'],
            'stamps' => (int)$purchase['stamps_snapshot'],
            'price_cents' => (int)$purchase['price_cents_snapshot'],
            'currency' => (string)$purchase['currency_snapshot'],
            'status' => (string)$purchase['status'],
            'checkout_reference' => (string)($purchase['checkout_reference'] ?? ''),
            'checkout_url' => '/merchant-stamps.php?purchase=' . rawurlencode((string)$purchase['public_id']),
        ],
        'stamp_ledger' => $credited,
        'ledger' => mg_stamp_ledger_payload($pdo, $accountUserId),
    ], $credited ? 'Stamp bundle credited.' : 'Stamp bundle checkout created.', $credited ? 201 : 202);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 409);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','stamps.purchase_failed','Unable to create Stamp purchase.', ['exception_class'=>$error::class], $accountUserId);
    mg_fail('Unable to create Stamp purchase.', 500);
}
