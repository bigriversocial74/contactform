<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';

$user = mg_require_api_user();
mg_require_method('GET');

$purchaseId = trim((string)($_GET['purchase_id'] ?? $_GET['purchase'] ?? ''));
if ($purchaseId === '') mg_fail('purchase_id is required.', 422);

function mg_stamp_receipt_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function mg_stamp_receipt_ledger_entry(PDO $pdo, array $purchase): ?array
{
    if (!mg_stamp_receipt_table_exists($pdo, 'stamp_ledger_entries')) return null;
    $ledgerId = trim((string)($purchase['credited_ledger_entry_public_id'] ?? ''));
    if ($ledgerId !== '') {
        $stmt = $pdo->prepare('SELECT public_id,account_user_id,actor_type,entry_type,delta,balance_after,source_type,source_id,reference,reason_code,created_at FROM stamp_ledger_entries WHERE public_id=? AND account_user_id=? LIMIT 1');
        $stmt->execute([$ledgerId, (int)$purchase['account_user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }
    $stmt = $pdo->prepare("SELECT public_id,account_user_id,actor_type,entry_type,delta,balance_after,source_type,source_id,reference,reason_code,created_at FROM stamp_ledger_entries WHERE account_user_id=? AND source_type='bulk_stamp_purchase' AND source_id=? ORDER BY created_at DESC,id DESC LIMIT 1");
    $stmt->execute([(int)$purchase['account_user_id'], (string)$purchase['public_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

$pdo = mg_db();
try {
    $accountUserId = (int)$user['id'];
    $purchase = mg_stamp_purchase_load($pdo, $accountUserId, $purchaseId, '', false);
    $intent = mg_stamp_purchase_find_intent($pdo, (string)$purchase['public_id']);
    $ledgerEntry = mg_stamp_receipt_ledger_entry($pdo, $purchase);
    $issuedAt = $purchase['credited_at'] ?? $purchase['paid_at'] ?? $purchase['created_at'] ?? date('Y-m-d H:i:s');

    mg_ok([
        'receipt'=>[
            'receipt_id'=>'STAMP-' . date('Ym', strtotime((string)$issuedAt)) . '-' . substr((string)$purchase['public_id'], 0, 8),
            'purchase_id'=>(string)$purchase['public_id'],
            'account_user_id'=>$accountUserId,
            'issued_at'=>$issuedAt,
            'receipt_url'=>'/stamp-receipt.php?purchase=' . rawurlencode((string)$purchase['public_id']),
            'print_label'=>'Print / Save PDF',
        ],
        'purchase'=>[
            'id'=>(string)$purchase['public_id'],
            'bundle_key'=>(string)$purchase['bundle_key'],
            'label'=>(string)$purchase['label_snapshot'],
            'stamps'=>(int)$purchase['stamps_snapshot'],
            'price_cents'=>(int)$purchase['price_cents_snapshot'],
            'currency'=>(string)$purchase['currency_snapshot'],
            'status'=>(string)$purchase['status'],
            'checkout_reference'=>(string)($purchase['checkout_reference'] ?? ''),
            'credited_ledger_entry_id'=>(string)($purchase['credited_ledger_entry_public_id'] ?? ''),
            'created_at'=>$purchase['created_at'] ?? null,
            'paid_at'=>$purchase['paid_at'] ?? null,
            'credited_at'=>$purchase['credited_at'] ?? null,
            'checkout_url'=>'/stamp-checkout.php?purchase=' . rawurlencode((string)$purchase['public_id']),
        ],
        'payment_intent'=>$intent ? [
            'id'=>(string)($intent['public_id'] ?? ''),
            'provider_key'=>(string)($intent['provider_key'] ?? ''),
            'provider_intent_reference'=>(string)($intent['provider_intent_reference'] ?? ''),
            'amount_cents'=>(int)($intent['amount_cents'] ?? 0),
            'currency'=>(string)($intent['currency'] ?? ''),
            'status'=>(string)($intent['status'] ?? ''),
            'created_at'=>$intent['created_at'] ?? null,
            'updated_at'=>$intent['updated_at'] ?? null,
        ] : null,
        'ledger_entry'=>$ledgerEntry ? [
            'entry_id'=>(string)$ledgerEntry['public_id'],
            'entry_type'=>(string)$ledgerEntry['entry_type'],
            'delta'=>(int)$ledgerEntry['delta'],
            'balance_after'=>(int)$ledgerEntry['balance_after'],
            'source_type'=>(string)$ledgerEntry['source_type'],
            'source_id'=>(string)($ledgerEntry['source_id'] ?? ''),
            'reference'=>(string)($ledgerEntry['reference'] ?? ''),
            'reason_code'=>(string)($ledgerEntry['reason_code'] ?? ''),
            'created_at'=>$ledgerEntry['created_at'] ?? null,
        ] : null,
        'line_items'=>[[
            'description'=>(string)$purchase['label_snapshot'],
            'quantity'=>1,
            'stamps'=>(int)$purchase['stamps_snapshot'],
            'amount_cents'=>(int)$purchase['price_cents_snapshot'],
            'currency'=>(string)$purchase['currency_snapshot'],
        ]],
        'owner_scoped'=>true,
        'admin_links'=>[],
    ], 'Stamp purchase receipt loaded.');
} catch (RuntimeException $error) {
    $status = (int)$error->getCode();
    if ($status < 400 || $status > 599) $status = 404;
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    mg_security_log('warning','stamps.purchase_receipt_unavailable','Stamp purchase receipt unavailable.', ['purchase_id'=>$purchaseId,'exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to load Stamp purchase receipt.', 500);
}
