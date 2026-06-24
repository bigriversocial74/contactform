<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';
$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.view') && !mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
mg_require_method('GET');
$pdo = mg_db();
try {
    $stmt = $pdo->query('SELECT public_id,account_user_id,bundle_key,label_snapshot,stamps_snapshot,price_cents_snapshot,currency_snapshot,status,checkout_reference,credited_ledger_entry_public_id,created_at,paid_at,credited_at FROM stamp_purchases ORDER BY created_at DESC,id DESC LIMIT 100');
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'id'=>(string)$row['public_id'],
            'account_user_id'=>(int)$row['account_user_id'],
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
        ];
    }
    mg_ok(['purchases'=>$rows,'count'=>count($rows)]);
} catch (Throwable $error) {
    mg_security_log('warning','stamps.purchase_report_unavailable','Stamp purchase report unavailable.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_ok(['purchases'=>[], 'count'=>0, 'schema_ready'=>false], 'Stamp purchase report unavailable until migration is installed.');
}
