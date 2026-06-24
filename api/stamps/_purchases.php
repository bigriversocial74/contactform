<?php
declare(strict_types=1);

require_once __DIR__ . '/_stamps.php';

function mg_stamp_purchase_payload(PDO $pdo, array $purchase, ?array $credited = null): array
{
    return [
        'purchase' => [
            'id' => (string)$purchase['public_id'],
            'bundle_key' => (string)$purchase['bundle_key'],
            'label' => (string)$purchase['label_snapshot'],
            'stamps' => (int)$purchase['stamps_snapshot'],
            'price_cents' => (int)$purchase['price_cents_snapshot'],
            'currency' => (string)$purchase['currency_snapshot'],
            'status' => (string)$purchase['status'],
            'checkout_reference' => (string)($purchase['checkout_reference'] ?? ''),
            'credited_ledger_entry_id' => (string)($purchase['credited_ledger_entry_public_id'] ?? ''),
            'paid_at' => $purchase['paid_at'] ?? null,
            'credited_at' => $purchase['credited_at'] ?? null,
            'checkout_url' => '/merchant-stamps.php?purchase=' . rawurlencode((string)$purchase['public_id']),
        ],
        'stamp_ledger' => $credited,
        'ledger' => mg_stamp_ledger_payload($pdo, (int)$purchase['account_user_id']),
    ];
}

function mg_stamp_purchase_load(PDO $pdo, int $accountUserId, string $purchaseId = '', string $checkoutReference = '', bool $lock = false): array
{
    $suffix = $lock ? ' FOR UPDATE' : '';
    if ($purchaseId !== '') {
        $stmt = $pdo->prepare('SELECT * FROM stamp_purchases WHERE public_id=? AND account_user_id=? LIMIT 1' . $suffix);
        $stmt->execute([$purchaseId, $accountUserId]);
    } elseif ($checkoutReference !== '') {
        $stmt = $pdo->prepare('SELECT * FROM stamp_purchases WHERE checkout_reference=? AND account_user_id=? LIMIT 1' . $suffix);
        $stmt->execute([$checkoutReference, $accountUserId]);
    } else {
        mg_fail('purchase_id or checkout_reference is required.', 422);
    }
    $purchase = $stmt->fetch();
    if (!$purchase) mg_fail('Stamp purchase not found.', 404);
    return $purchase;
}

function mg_stamp_purchase_complete(PDO $pdo, array $purchase, int $actorUserId, string $providerStatus = 'paid', string $idempotencySuffix = ''): array
{
    $status = (string)$purchase['status'];
    if ($status === 'credited' && !empty($purchase['credited_ledger_entry_public_id'])) {
        return mg_stamp_purchase_payload($pdo, $purchase, null) + ['idempotent' => true];
    }
    if (!in_array($providerStatus, ['paid','succeeded','complete','completed','sandbox_paid'], true)) {
        mg_fail('Payment has not completed.', 409);
    }
    $credit = mg_stamp_credit($pdo, (int)$purchase['account_user_id'], $actorUserId, (int)$purchase['stamps_snapshot'], 'stamp:purchase:' . (string)$purchase['public_id'] . ':' . (string)$purchase['bundle_key'] . ($idempotencySuffix !== '' ? ':' . $idempotencySuffix : ''), [
        'actor_type' => $actorUserId === (int)$purchase['account_user_id'] ? 'merchant' : 'admin',
        'source_type' => 'bulk_stamp_purchase',
        'source_id' => (string)$purchase['public_id'],
        'reference' => (string)$purchase['bundle_key'],
        'reason_code' => 'bundle_purchase_payment_complete',
        'metadata' => [
            'purchase_id' => (string)$purchase['public_id'],
            'bundle_key' => (string)$purchase['bundle_key'],
            'price_cents' => (int)$purchase['price_cents_snapshot'],
            'provider_status' => $providerStatus,
        ],
    ]);
    $pdo->prepare('UPDATE stamp_purchases SET status=?,credited_ledger_entry_public_id=?,paid_at=COALESCE(paid_at,NOW()),credited_at=NOW(),updated_at=NOW() WHERE id=?')
        ->execute(['credited', (string)($credit['entry']['entry_id'] ?? ''), (int)$purchase['id']]);
    $purchase['status'] = 'credited';
    $purchase['credited_ledger_entry_public_id'] = (string)($credit['entry']['entry_id'] ?? '');
    $purchase['paid_at'] = $purchase['paid_at'] ?? date('Y-m-d H:i:s');
    $purchase['credited_at'] = date('Y-m-d H:i:s');
    return mg_stamp_purchase_payload($pdo, $purchase, $credit) + ['idempotent' => !empty($credit['idempotent'])];
}
