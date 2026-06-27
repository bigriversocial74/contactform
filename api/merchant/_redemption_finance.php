<?php
declare(strict_types=1);

function mg_redemption_finance_fee_cents(int $amountCents): int
{
    return max(0, (int)round($amountCents * 0.03));
}

function mg_redemption_finance_create_settlement(PDO $pdo, array $receipt): ?array
{
    $receiptId = trim((string)($receipt['public_id'] ?? ''));
    if ($receiptId === '') return null;
    $amount = max(0, (int)($receipt['amount_cents'] ?? 0));
    $fee = mg_redemption_finance_fee_cents($amount);
    $net = max(0, $amount - $fee);
    try {
        $existing = $pdo->prepare('SELECT * FROM redemption_settlement_ledger WHERE receipt_public_id=? LIMIT 1');
        $existing->execute([$receiptId]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
        $publicId = mg_public_uuid();
        $stmt = $pdo->prepare("INSERT INTO redemption_settlement_ledger (public_id,receipt_public_id,receipt_type,gift_public_id,merchant_user_id,scanner_location_id,location_public_id,amount_cents,platform_fee_cents,merchant_net_cents,currency,settlement_status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending',?,NOW(),NOW())");
        $stmt->execute([$publicId, $receiptId, (string)($receipt['receipt_type'] ?? 'microgift'), (string)($receipt['gift_public_id'] ?? ''), (int)($receipt['merchant_user_id'] ?? 0), isset($receipt['scanner_location_id']) ? (int)$receipt['scanner_location_id'] : null, $receipt['location_public_id'] ?? null, $amount, $fee, $net, (string)($receipt['currency'] ?? 'USD'), json_encode(['source' => 'scanner_receipt'], JSON_UNESCAPED_SLASHES)]);
        return ['public_id' => $publicId, 'receipt_public_id' => $receiptId, 'amount_cents' => $amount, 'platform_fee_cents' => $fee, 'merchant_net_cents' => $net, 'settlement_status' => 'pending'];
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) mg_security_log('warning', 'redemption.settlement_failed', 'Unable to create redemption settlement ledger row.', ['receipt_id' => $receiptId, 'exception_class' => $error::class], isset($receipt['merchant_user_id']) ? (int)$receipt['merchant_user_id'] : null);
        return null;
    }
}

function mg_redemption_finance_create_settlement_for_receipt_id(PDO $pdo, string $receiptPublicId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM scanner_redemption_receipts WHERE public_id=? LIMIT 1');
    $stmt->execute([$receiptPublicId]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    return $receipt ? mg_redemption_finance_create_settlement($pdo, $receipt) : null;
}

function mg_redemption_finance_open_dispute(PDO $pdo, array $receipt, int $openedByUserId, string $type, string $reason): array
{
    $type = in_array($type, ['customer_dispute','merchant_void','admin_review','refund_request','duplicate_scan','other'], true) ? $type : 'other';
    $reason = mb_substr(trim($reason) !== '' ? trim($reason) : 'Redemption review requested.', 0, 255);
    $receiptId = (string)$receipt['public_id'];
    $settlement = mg_redemption_finance_create_settlement($pdo, $receipt);
    $stmt = $pdo->prepare("INSERT INTO redemption_disputes (public_id,receipt_public_id,settlement_public_id,dispute_type,reason,status,opened_by_user_id,merchant_user_id,customer_user_id,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,'open',?,?,?,?,NOW(),NOW())");
    $publicId = mg_public_uuid();
    $stmt->execute([$publicId, $receiptId, $settlement['public_id'] ?? null, $type, $reason, $openedByUserId, (int)($receipt['merchant_user_id'] ?? 0), isset($receipt['customer_user_id']) ? (int)$receipt['customer_user_id'] : null, json_encode(['receipt_type' => $receipt['receipt_type'] ?? 'microgift'], JSON_UNESCAPED_SLASHES)]);
    $pdo->prepare("UPDATE redemption_settlement_ledger SET settlement_status='held',hold_reason=?,updated_at=NOW() WHERE receipt_public_id=?")->execute([$reason, $receiptId]);
    return ['id' => $publicId, 'receipt_id' => $receiptId, 'status' => 'open'];
}

function mg_redemption_finance_apply_dispute_status(PDO $pdo, array $dispute, int $adminUserId, string $status, string $note = ''): array
{
    $allowed = ['merchant_review','admin_review','voided','refunded','reversed','dismissed','resolved'];
    if (!in_array($status, $allowed, true)) mg_fail('Invalid dispute status.', 422);
    $timestampColumn = null;
    if ($status === 'voided') $timestampColumn = 'voided_at';
    if ($status === 'refunded') $timestampColumn = 'refunded_at';
    if ($status === 'reversed') $timestampColumn = 'reversed_at';
    if (in_array($status, ['resolved','dismissed'], true)) $timestampColumn = 'resolved_at';
    $sql = 'UPDATE redemption_disputes SET status=?,admin_user_id=?,admin_notes=?,updated_at=NOW()';
    if ($timestampColumn) $sql .= ',' . $timestampColumn . '=NOW()';
    $sql .= ' WHERE id=?';
    $pdo->prepare($sql)->execute([$status, $adminUserId, $note, (int)$dispute['id']]);
    $settlementStatus = in_array($status, ['voided','reversed'], true) ? $status : (in_array($status, ['resolved','dismissed'], true) ? 'ready' : 'held');
    $pdo->prepare('UPDATE redemption_settlement_ledger SET settlement_status=?,updated_at=NOW() WHERE receipt_public_id=?')->execute([$settlementStatus, (string)$dispute['receipt_public_id']]);
    return ['id' => (string)$dispute['public_id'], 'status' => $status, 'settlement_status' => $settlementStatus];
}
