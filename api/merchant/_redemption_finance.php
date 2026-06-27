<?php
declare(strict_types=1);

function mg_redemption_finance_decode_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_redemption_finance_normalize_value_type(string $value): string
{
    return in_array($value, ['promotional_reward','merchant_direct_paid_product','platform_checkout','sponsored_campaign'], true) ? $value : 'promotional_reward';
}

function mg_redemption_finance_normalize_cash_movement(string $value): string
{
    return in_array($value, ['none','merchant_direct','stripe_connect','platform_collected'], true) ? $value : 'none';
}

function mg_redemption_finance_fee_cents(int $amountCents, string $cashMovement = 'none'): int
{
    if ($cashMovement !== 'platform_collected') return 0;
    return max(0, (int)round($amountCents * 0.03));
}

function mg_redemption_finance_first_int(array $source, array $keys): int
{
    foreach ($keys as $key) {
        if (isset($source[$key]) && is_numeric($source[$key])) return max(0, (int)$source[$key]);
    }
    return 0;
}

function mg_redemption_finance_first_string(array $source, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($source[$key]) && trim((string)$source[$key]) !== '') return trim((string)$source[$key]);
    }
    return '';
}

function mg_redemption_finance_classify_receipt(array $receipt): array
{
    $meta = mg_redemption_finance_decode_json($receipt['metadata_json'] ?? null);
    $source = array_merge($meta, $receipt);
    $faceValue = mg_redemption_finance_first_int($source, ['face_value_cents','amount_cents','value_cents','reward_value_cents']);
    $customerPaid = mg_redemption_finance_first_int($source, ['customer_paid_cents','paid_amount_cents','order_total_cents']);
    $merchantCollected = mg_redemption_finance_first_int($source, ['merchant_collected_cents','merchant_received_cents']);
    $microgifterCollected = mg_redemption_finance_first_int($source, ['microgifter_collected_cents','platform_collected_cents']);
    $valueType = mg_redemption_finance_normalize_value_type((string)($source['value_type'] ?? ''));
    $cashMovement = mg_redemption_finance_normalize_cash_movement((string)($source['cash_movement'] ?? ''));
    $provider = strtolower((string)($source['provider_key'] ?? $source['payment_provider'] ?? ''));
    $sourceType = strtolower((string)($source['source_type'] ?? $source['receipt_source'] ?? ''));
    $hasOrder = mg_redemption_finance_first_int($source, ['order_id','commerce_order_id']) > 0 || mg_redemption_finance_first_string($source, ['order_public_id','payment_intent_id','provider_payment_id']) !== '';

    if ($valueType === 'promotional_reward' && ($sourceType === 'sponsored_campaign' || !empty($source['sponsor_id']))) $valueType = 'sponsored_campaign';
    if ($hasOrder && in_array($provider, ['stripe','stripe_connect'], true)) {
        $valueType = 'merchant_direct_paid_product';
        $cashMovement = 'stripe_connect';
        $customerPaid = max($customerPaid, $faceValue);
        $merchantCollected = max($merchantCollected, $customerPaid);
    }
    if (($source['platform_collected'] ?? false) || $cashMovement === 'platform_collected') {
        $valueType = 'platform_checkout';
        $cashMovement = 'platform_collected';
        $customerPaid = max($customerPaid, $faceValue);
        $microgifterCollected = max($microgifterCollected, $customerPaid);
    }
    if (in_array($valueType, ['promotional_reward','sponsored_campaign'], true) && $cashMovement !== 'platform_collected') {
        $cashMovement = 'none';
        $customerPaid = 0;
        $merchantCollected = 0;
        $microgifterCollected = 0;
    }

    $fee = mg_redemption_finance_fee_cents($customerPaid, $cashMovement);
    $payoutDue = $cashMovement === 'platform_collected' ? max(0, $customerPaid - $fee) : 0;
    return [
        'value_type' => $valueType,
        'cash_movement' => $cashMovement,
        'face_value_cents' => $faceValue,
        'customer_paid_cents' => $customerPaid,
        'merchant_collected_cents' => $merchantCollected,
        'microgifter_collected_cents' => $microgifterCollected,
        'platform_fee_cents' => $fee,
        'merchant_net_cents' => $payoutDue,
        'payout_due_cents' => $payoutDue,
        'settlement_status' => $payoutDue > 0 ? 'pending' : 'settled',
        'reconciliation_status' => $payoutDue > 0 ? 'pending' : 'not_applicable',
        'campaign_id' => mg_redemption_finance_first_int($source, ['campaign_id']),
        'source_campaign_id' => mg_redemption_finance_first_int($source, ['source_campaign_id','reward_campaign_id']),
        'order_id' => mg_redemption_finance_first_int($source, ['order_id','commerce_order_id']),
        'payment_intent_id' => mg_redemption_finance_first_string($source, ['payment_intent_id','provider_payment_id']),
        'receipt_id' => mg_redemption_finance_first_int($source, ['receipt_id','scanner_receipt_id']),
    ];
}

function mg_redemption_finance_create_settlement(PDO $pdo, array $receipt): ?array
{
    $receiptId = trim((string)($receipt['public_id'] ?? ''));
    if ($receiptId === '') return null;
    try {
        $existing = $pdo->prepare('SELECT * FROM redemption_settlement_ledger WHERE receipt_public_id=? LIMIT 1');
        $existing->execute([$receiptId]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
        $flow = mg_redemption_finance_classify_receipt($receipt);
        $publicId = mg_public_uuid();
        $stmt = $pdo->prepare("INSERT INTO redemption_settlement_ledger (public_id,receipt_public_id,receipt_type,value_type,cash_movement,gift_public_id,merchant_user_id,scanner_location_id,location_public_id,amount_cents,face_value_cents,customer_paid_cents,merchant_collected_cents,microgifter_collected_cents,platform_fee_cents,merchant_net_cents,payout_due_cents,currency,settlement_status,reconciliation_status,campaign_id,source_campaign_id,order_id,payment_intent_id,receipt_id,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
        $stmt->execute([
            $publicId,
            $receiptId,
            (string)($receipt['receipt_type'] ?? 'microgift'),
            $flow['value_type'],
            $flow['cash_movement'],
            (string)($receipt['gift_public_id'] ?? ''),
            (int)($receipt['merchant_user_id'] ?? 0),
            isset($receipt['scanner_location_id']) ? (int)$receipt['scanner_location_id'] : null,
            $receipt['location_public_id'] ?? null,
            $flow['face_value_cents'],
            $flow['face_value_cents'],
            $flow['customer_paid_cents'],
            $flow['merchant_collected_cents'],
            $flow['microgifter_collected_cents'],
            $flow['platform_fee_cents'],
            $flow['merchant_net_cents'],
            $flow['payout_due_cents'],
            (string)($receipt['currency'] ?? 'USD'),
            $flow['settlement_status'],
            $flow['reconciliation_status'],
            $flow['campaign_id'] ?: null,
            $flow['source_campaign_id'] ?: null,
            $flow['order_id'] ?: null,
            $flow['payment_intent_id'] ?: null,
            $flow['receipt_id'] ?: null,
            json_encode(['source' => 'scanner_receipt', 'value_flow' => $flow], JSON_UNESCAPED_SLASHES),
        ]);
        return ['public_id' => $publicId, 'receipt_public_id' => $receiptId] + $flow;
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
    $stmt->execute([$publicId, $receiptId, $settlement['public_id'] ?? null, $type, $reason, $openedByUserId, (int)($receipt['merchant_user_id'] ?? 0), isset($receipt['customer_user_id']) ? (int)$receipt['customer_user_id'] : null, json_encode(['receipt_type' => $receipt['receipt_type'] ?? 'microgift', 'value_type' => $settlement['value_type'] ?? null, 'cash_movement' => $settlement['cash_movement'] ?? null], JSON_UNESCAPED_SLASHES)]);
    $pdo->prepare("UPDATE redemption_settlement_ledger SET settlement_status='held',reconciliation_status='in_review',hold_reason=?,updated_at=NOW() WHERE receipt_public_id=?")->execute([$reason, $receiptId]);
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
    $reconciliationStatus = match ($status) {
        'voided' => 'voided',
        'reversed' => 'reversed',
        'resolved','dismissed' => 'reconciled',
        default => 'in_review',
    };
    $pdo->prepare('UPDATE redemption_settlement_ledger SET settlement_status=?,reconciliation_status=?,updated_at=NOW() WHERE receipt_public_id=?')->execute([$settlementStatus, $reconciliationStatus, (string)$dispute['receipt_public_id']]);
    return ['id' => (string)$dispute['public_id'], 'status' => $status, 'settlement_status' => $settlementStatus, 'reconciliation_status' => $reconciliationStatus];
}
