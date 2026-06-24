<?php
declare(strict_types=1);
require_once __DIR__ . '/_stamps.php';
$user = mg_require_api_user();
mg_require_method('GET');
$pdo = mg_db();
$accountUserId = (int)$user['id'];
if (isset($_GET['account_user_id']) && $_GET['account_user_id'] !== '') {
    if (!mg_api_user_has_permission($user, 'admin.stamps.view') && !mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
    $accountUserId = max(1, (int)$_GET['account_user_id']);
}
try {
    $stmt = $pdo->prepare('SELECT public_id,bundle_key,label_snapshot,stamps_snapshot,price_cents_snapshot,currency_snapshot,status,checkout_reference,credited_ledger_entry_public_id,created_at,paid_at,credited_at FROM stamp_purchases WHERE account_user_id=? ORDER BY created_at DESC,id DESC LIMIT 50');
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
    ], $stmt->fetchAll());
    mg_ok(['purchases'=>$items,'count'=>count($items)]);
} catch (Throwable $error) {
    mg_security_log('warning','stamps.purchases_unavailable','Stamp purchase history unavailable.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_ok(['purchases'=>[],'count'=>0,'schema_ready'=>false], 'Stamp purchase history unavailable until migration is installed.');
}
