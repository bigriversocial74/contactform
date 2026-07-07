<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';
$user = mg_require_api_user();
mg_require_method('GET');
$pdo = mg_db();
$accountUserId = (int)$user['id'];
if (isset($_GET['account_user_id']) && $_GET['account_user_id'] !== '') {
    if (!mg_api_user_has_permission($user, 'admin.stamps.view') && !mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
    $accountUserId = max(1, (int)$_GET['account_user_id']);
}
try {
    $stmt = $pdo->prepare("SELECT sp.public_id,sp.bundle_key,sp.label_snapshot,sp.stamps_snapshot,sp.price_cents_snapshot,sp.currency_snapshot,sp.status,sp.checkout_reference,sp.credited_ledger_entry_public_id,sp.created_at,sp.paid_at,sp.credited_at,
            pi.public_id payment_intent_id,pi.provider_key,pi.provider_intent_reference,pi.amount_cents payment_amount_cents,pi.currency payment_currency,pi.status payment_intent_status,pi.failure_code,pi.failure_message,pi.updated_at payment_updated_at
        FROM stamp_purchases sp
        LEFT JOIN payment_intents pi ON pi.source_type='stamp_purchase' AND pi.source_reference=sp.public_id
        WHERE sp.account_user_id=?
        ORDER BY sp.created_at DESC,sp.id DESC
        LIMIT 50");
    $stmt->execute([$accountUserId]);
    $items = array_map(static fn(array $row): array => [
        'id'=>(string)$row['public_id'],
        'bundle_key'=>(string)$row['bundle_key'],
        'label'=>(string)$row['label_snapshot'],
        'stamps'=>(int)$row['stamps_snapshot'],
        'price_cents'=>(int)$row['price_cents_snapshot'],
        'currency'=>(string)$row['currency_snapshot'],
        'status'=>(string)$row['status'],
        'checkout_reference'=>(string)($row['checkout_reference'] ?? ''),
        'credited_ledger_entry_id'=>(string)($row['credited_ledger_entry_public_id'] ?? ''),
        'created_at'=>$row['created_at'] ?? null,
        'paid_at'=>$row['paid_at'] ?? null,
        'credited_at'=>$row['credited_at'] ?? null,
        'checkout_url'=>'/stamp-checkout.php?purchase=' . rawurlencode((string)$row['public_id']),
        'receipt_url'=>'/stamp-receipt.php?purchase=' . rawurlencode((string)$row['public_id']),
        'payment_intent'=>[
            'id'=>(string)($row['payment_intent_id'] ?? ''),
            'provider_key'=>(string)($row['provider_key'] ?? ''),
            'provider_intent_reference'=>(string)($row['provider_intent_reference'] ?? ''),
            'amount_cents'=>(int)($row['payment_amount_cents'] ?? 0),
            'currency'=>(string)($row['payment_currency'] ?? ''),
            'status'=>(string)($row['payment_intent_status'] ?? ''),
            'failure_code'=>(string)($row['failure_code'] ?? ''),
            'failure_message'=>(string)($row['failure_message'] ?? ''),
            'updated_at'=>$row['payment_updated_at'] ?? null,
        ],
        'owner_scoped'=>!isset($_GET['account_user_id']) || $_GET['account_user_id'] === '',
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    mg_ok(['purchases'=>$items,'count'=>count($items),'owner_scoped'=>!isset($_GET['account_user_id']) || $_GET['account_user_id'] === '']);
} catch (Throwable $error) {
    mg_security_log('warning','stamps.purchases_unavailable','Stamp purchase history unavailable.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_ok(['purchases'=>[],'count'=>0,'schema_ready'=>false], 'Stamp purchase history unavailable until migration is installed.');
}
