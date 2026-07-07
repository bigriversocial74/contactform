<?php
declare(strict_types=1);
require_once __DIR__ . '/_stamps.php';

$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.view') && !mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
mg_require_method('GET');

function mg_stamp_export_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function mg_stamp_export_period(): array
{
    $period = trim((string)($_GET['period'] ?? date('Y-m')));
    if (!preg_match('/^\d{4}-\d{2}$/', $period)) mg_fail('period must be YYYY-MM.', 422);
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $period . '-01 00:00:00');
    if (!$start) mg_fail('Invalid export period.', 422);
    $end = $start->modify('first day of next month');
    return [$period, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
}

function mg_stamp_export_state(array $row): string
{
    $purchaseStatus = (string)($row['status'] ?? '');
    $intentStatus = (string)($row['payment_intent_status'] ?? '');
    $ledgerId = trim((string)($row['credited_ledger_entry_public_id'] ?? ''));
    $intentId = trim((string)($row['payment_intent_id'] ?? ''));
    $amountMismatch = $intentId !== '' && ((int)($row['payment_amount_cents'] ?? 0) !== (int)($row['price_cents_snapshot'] ?? 0) || !hash_equals((string)($row['payment_currency'] ?? ''), (string)($row['currency_snapshot'] ?? '')));
    if ($intentId === '') return 'missing_intent';
    if ($amountMismatch) return 'amount_review';
    if ($purchaseStatus === 'credited') {
        if ($ledgerId === '') return 'ledger_review';
        if ($intentStatus !== 'succeeded') return 'payment_review';
        return 'reconciled';
    }
    if ($intentStatus === 'succeeded') return 'paid_uncredited';
    if (in_array($purchaseStatus, ['failed','cancelled'], true) || in_array($intentStatus, ['failed','cancelled'], true)) return 'failed_payment';
    if (in_array($intentStatus, ['requires_action','processing','created','requires_payment_method'], true) || in_array($purchaseStatus, ['pending','checkout_created'], true)) return 'awaiting_webhook';
    return 'review';
}

function mg_stamp_export_csv(string $filename, array $headers, iterable $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $out = fopen('php://output', 'wb');
    fputcsv($out, $headers);
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
}

$pdo = mg_db();
try {
    [$period, $start, $end] = mg_stamp_export_period();
    $type = strtolower(trim((string)($_GET['type'] ?? 'ledger')));
    if (!in_array($type, ['ledger','reconciliation'], true)) mg_fail('type must be ledger or reconciliation.', 422);

    if ($type === 'ledger') {
        if (!mg_stamp_export_table_exists($pdo, 'stamp_ledger_entries')) mg_fail('Stamp ledger table is unavailable.', 409);
        $stmt = $pdo->prepare('SELECT public_id,account_user_id,actor_user_id,actor_type,entry_type,action_key,stamp_value,quantity,delta,balance_after,source_type,source_id,reference,reason_code,note,idempotency_key,created_at FROM stamp_ledger_entries WHERE created_at >= ? AND created_at < ? ORDER BY created_at ASC,id ASC LIMIT 5000');
        $stmt->execute([$start, $end]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                (string)$row['public_id'],
                (int)$row['account_user_id'],
                isset($row['actor_user_id']) ? (int)$row['actor_user_id'] : '',
                (string)$row['actor_type'],
                (string)$row['entry_type'],
                (string)($row['action_key'] ?? ''),
                (int)$row['stamp_value'],
                (int)$row['quantity'],
                (int)$row['delta'],
                (int)$row['balance_after'],
                (string)$row['source_type'],
                (string)($row['source_id'] ?? ''),
                (string)($row['reference'] ?? ''),
                (string)($row['reason_code'] ?? ''),
                (string)($row['note'] ?? ''),
                (string)$row['idempotency_key'],
                (string)$row['created_at'],
            ];
        }
        mg_stamp_export_csv('stamp-ledger-' . $period . '.csv', ['entry_id','account_user_id','actor_user_id','actor_type','entry_type','action_key','stamp_value','quantity','delta','balance_after','source_type','source_id','reference','reason_code','note','idempotency_key','created_at'], $rows);
        return;
    }

    foreach (['stamp_purchases','payment_intents'] as $table) {
        if (!mg_stamp_export_table_exists($pdo, $table)) mg_fail('Stamp purchase reconciliation tables are unavailable.', 409);
    }
    $stmt = $pdo->prepare("SELECT sp.public_id,sp.account_user_id,sp.bundle_key,sp.label_snapshot,sp.stamps_snapshot,sp.price_cents_snapshot,sp.currency_snapshot,sp.status,sp.checkout_reference,sp.credited_ledger_entry_public_id,sp.created_at,sp.updated_at,sp.paid_at,sp.credited_at,
            pi.public_id payment_intent_id,pi.provider_key,pi.provider_intent_reference,pi.amount_cents payment_amount_cents,pi.currency payment_currency,pi.status payment_intent_status,pi.failure_code,pi.failure_message
        FROM stamp_purchases sp
        LEFT JOIN payment_intents pi ON pi.source_type='stamp_purchase' AND pi.source_reference=sp.public_id
        WHERE sp.created_at >= ? AND sp.created_at < ?
        ORDER BY sp.created_at ASC,sp.id ASC
        LIMIT 5000");
    $stmt->execute([$start, $end]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            (string)$row['public_id'],
            (int)$row['account_user_id'],
            (string)$row['bundle_key'],
            (string)$row['label_snapshot'],
            (int)$row['stamps_snapshot'],
            (int)$row['price_cents_snapshot'],
            (string)$row['currency_snapshot'],
            (string)$row['status'],
            (string)($row['payment_intent_id'] ?? ''),
            (string)($row['provider_key'] ?? ''),
            (string)($row['provider_intent_reference'] ?? ''),
            (int)($row['payment_amount_cents'] ?? 0),
            (string)($row['payment_currency'] ?? ''),
            (string)($row['payment_intent_status'] ?? ''),
            (string)($row['failure_code'] ?? ''),
            (string)($row['failure_message'] ?? ''),
            (string)($row['credited_ledger_entry_public_id'] ?? ''),
            mg_stamp_export_state($row),
            (string)($row['created_at'] ?? ''),
            (string)($row['paid_at'] ?? ''),
            (string)($row['credited_at'] ?? ''),
        ];
    }
    mg_stamp_export_csv('stamp-reconciliation-' . $period . '.csv', ['purchase_id','account_user_id','bundle_key','label','stamps','price_cents','currency','purchase_status','payment_intent_id','provider_key','provider_intent_reference','payment_amount_cents','payment_currency','payment_intent_status','failure_code','failure_message','credited_ledger_entry_id','reconciliation_state','created_at','paid_at','credited_at'], $rows);
} catch (RuntimeException $error) {
    $status = (int)$error->getCode();
    if ($status < 400 || $status > 599) $status = 409;
    mg_fail($error->getMessage(), $status);
} catch (Throwable $error) {
    mg_security_log('warning','stamps.ledger_export_failed','Stamp ledger export failed.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_fail('Unable to export Stamp ledger.', 500);
}
