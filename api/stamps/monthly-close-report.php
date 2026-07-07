<?php
declare(strict_types=1);
require_once __DIR__ . '/_stamps.php';

$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.view') && !mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
mg_require_method('GET');

function mg_stamp_close_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function mg_stamp_close_period(): array
{
    $period = trim((string)($_GET['period'] ?? date('Y-m')));
    if (!preg_match('/^\d{4}-\d{2}$/', $period)) mg_fail('period must be YYYY-MM.', 422);
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $period . '-01 00:00:00');
    if (!$start) mg_fail('Invalid monthly close period.', 422);
    $end = $start->modify('first day of next month');
    return [$period, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
}

function mg_stamp_close_state(array $row): array
{
    $purchaseStatus = (string)($row['status'] ?? '');
    $intentStatus = (string)($row['payment_intent_status'] ?? '');
    $ledgerId = trim((string)($row['credited_ledger_entry_public_id'] ?? ''));
    $intentId = trim((string)($row['payment_intent_id'] ?? ''));
    $amountMismatch = $intentId !== '' && ((int)($row['payment_amount_cents'] ?? 0) !== (int)($row['price_cents_snapshot'] ?? 0) || !hash_equals((string)($row['payment_currency'] ?? ''), (string)($row['currency_snapshot'] ?? '')));
    if ($intentId === '') return ['state'=>'missing_intent','severity'=>'error','label'=>'Missing payment intent'];
    if ($amountMismatch) return ['state'=>'amount_review','severity'=>'error','label'=>'Amount/currency review'];
    if ($purchaseStatus === 'credited') {
        if ($ledgerId === '') return ['state'=>'ledger_review','severity'=>'warning','label'=>'Credited without ledger ID'];
        if ($intentStatus !== 'succeeded') return ['state'=>'payment_review','severity'=>'warning','label'=>'Ledger credited, payment not succeeded'];
        return ['state'=>'reconciled','severity'=>'success','label'=>'Reconciled'];
    }
    if ($intentStatus === 'succeeded') return ['state'=>'paid_uncredited','severity'=>'warning','label'=>'Paid provider intent, not credited'];
    if (in_array($purchaseStatus, ['failed','cancelled'], true) || in_array($intentStatus, ['failed','cancelled'], true)) return ['state'=>'failed_payment','severity'=>'error','label'=>'Failed/cancelled payment'];
    if (in_array($intentStatus, ['requires_action','processing','created','requires_payment_method'], true) || in_array($purchaseStatus, ['pending','checkout_created'], true)) return ['state'=>'awaiting_webhook','severity'=>'info','label'=>'Awaiting payment/webhook'];
    return ['state'=>'review','severity'=>'warning','label'=>'Needs review'];
}

function mg_stamp_close_money_cents(array $rows): int
{
    $total = 0;
    foreach ($rows as $row) $total += (int)($row['price_cents_snapshot'] ?? 0);
    return $total;
}

$pdo = mg_db();
try {
    [$period, $start, $end] = mg_stamp_close_period();
    foreach (['stamp_ledger_entries','account_stamp_balances','stamp_purchases','payment_intents'] as $table) {
        if (!mg_stamp_close_table_exists($pdo, $table)) {
            mg_ok(['schema_ready'=>false,'period'=>$period,'read_only'=>true], 'Monthly close unavailable until Stamp ledger tables are installed.');
            return;
        }
    }

    $ledgerStmt = $pdo->prepare('SELECT entry_type,COUNT(*) entries,COALESCE(SUM(delta),0) net_delta,COALESCE(SUM(CASE WHEN delta > 0 THEN delta ELSE 0 END),0) total_credits,COALESCE(SUM(CASE WHEN delta < 0 THEN ABS(delta) ELSE 0 END),0) total_debits FROM stamp_ledger_entries WHERE created_at >= ? AND created_at < ? GROUP BY entry_type ORDER BY entry_type');
    $ledgerStmt->execute([$start, $end]);
    $ledgerSummary = $ledgerStmt->fetchAll(PDO::FETCH_ASSOC);

    $ledgerTotalsStmt = $pdo->prepare('SELECT COUNT(*) entries,COUNT(DISTINCT account_user_id) accounts,COALESCE(SUM(delta),0) net_delta,COALESCE(SUM(CASE WHEN delta > 0 THEN delta ELSE 0 END),0) total_credits,COALESCE(SUM(CASE WHEN delta < 0 THEN ABS(delta) ELSE 0 END),0) total_debits FROM stamp_ledger_entries WHERE created_at >= ? AND created_at < ?');
    $ledgerTotalsStmt->execute([$start, $end]);
    $ledgerTotals = $ledgerTotalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $balanceStmt = $pdo->prepare('SELECT COUNT(*) accounts,COALESCE(SUM(balance),0) current_balance,COALESCE(SUM(included_monthly_stamps),0) included_monthly_stamps,COALESCE(SUM(purchased_stamps),0) purchased_stamps,COALESCE(SUM(used_stamps),0) used_stamps,COALESCE(SUM(voided_stamps),0) voided_stamps FROM account_stamp_balances WHERE current_period_key=?');
    $balanceStmt->execute([$period]);
    $balanceSummary = $balanceStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $purchaseStmt = $pdo->prepare("SELECT sp.public_id,sp.account_user_id,sp.bundle_key,sp.label_snapshot,sp.stamps_snapshot,sp.price_cents_snapshot,sp.currency_snapshot,sp.status,sp.credited_ledger_entry_public_id,sp.created_at,sp.updated_at,sp.paid_at,sp.credited_at,
            pi.public_id payment_intent_id,pi.provider_key,pi.provider_intent_reference,pi.amount_cents payment_amount_cents,pi.currency payment_currency,pi.status payment_intent_status,pi.failure_code,pi.failure_message
        FROM stamp_purchases sp
        LEFT JOIN payment_intents pi ON pi.source_type='stamp_purchase' AND pi.source_reference=sp.public_id
        WHERE sp.created_at >= ? AND sp.created_at < ?
        ORDER BY sp.created_at DESC,sp.id DESC
        LIMIT 500");
    $purchaseStmt->execute([$start, $end]);
    $purchaseRows = $purchaseStmt->fetchAll(PDO::FETCH_ASSOC);
    $reconSummary = ['total'=>0,'reconciled'=>0,'awaiting_webhook'=>0,'failed_payment'=>0,'missing_intent'=>0,'amount_review'=>0,'ledger_review'=>0,'payment_review'=>0,'paid_uncredited'=>0,'review'=>0,'needs_attention'=>0];
    $exceptions = [];
    foreach ($purchaseRows as $row) {
        $state = mg_stamp_close_state($row);
        $key = (string)$state['state'];
        $reconSummary['total']++;
        if (!array_key_exists($key, $reconSummary)) $reconSummary[$key] = 0;
        $reconSummary[$key]++;
        if ($key !== 'reconciled') {
            $reconSummary['needs_attention']++;
            $exceptions[] = [
                'id'=>(string)$row['public_id'],
                'account_user_id'=>(int)$row['account_user_id'],
                'bundle_key'=>(string)$row['bundle_key'],
                'stamps'=>(int)$row['stamps_snapshot'],
                'price_cents'=>(int)$row['price_cents_snapshot'],
                'currency'=>(string)$row['currency_snapshot'],
                'purchase_status'=>(string)$row['status'],
                'payment_status'=>(string)($row['payment_intent_status'] ?? ''),
                'provider_key'=>(string)($row['provider_key'] ?? ''),
                'provider_intent_reference'=>(string)($row['provider_intent_reference'] ?? ''),
                'created_at'=>$row['created_at'] ?? null,
                'reconciliation_state'=>$state,
                'reconciliation_url'=>'/stamp-payment-reconciliation.php?filter=' . rawurlencode($key) . '&q=' . rawurlencode((string)$row['public_id']),
            ];
        }
    }

    $recentLedgerStmt = $pdo->prepare('SELECT public_id,account_user_id,actor_user_id,actor_type,entry_type,action_key,delta,balance_after,source_type,source_id,reference,reason_code,created_at FROM stamp_ledger_entries WHERE created_at >= ? AND created_at < ? ORDER BY created_at DESC,id DESC LIMIT 25');
    $recentLedgerStmt->execute([$start, $end]);

    mg_ok([
        'schema_ready'=>true,
        'period'=>$period,
        'start_at'=>$start,
        'end_at'=>$end,
        'read_only'=>true,
        'ledger_totals'=>[
            'entries'=>(int)($ledgerTotals['entries'] ?? 0),
            'accounts'=>(int)($ledgerTotals['accounts'] ?? 0),
            'net_delta'=>(int)($ledgerTotals['net_delta'] ?? 0),
            'total_credits'=>(int)($ledgerTotals['total_credits'] ?? 0),
            'total_debits'=>(int)($ledgerTotals['total_debits'] ?? 0),
        ],
        'ledger_summary'=>array_map(static fn(array $row): array => [
            'entry_type'=>(string)$row['entry_type'],
            'entries'=>(int)$row['entries'],
            'net_delta'=>(int)$row['net_delta'],
            'total_credits'=>(int)$row['total_credits'],
            'total_debits'=>(int)$row['total_debits'],
        ], $ledgerSummary),
        'balance_summary'=>[
            'accounts'=>(int)($balanceSummary['accounts'] ?? 0),
            'current_balance'=>(int)($balanceSummary['current_balance'] ?? 0),
            'included_monthly_stamps'=>(int)($balanceSummary['included_monthly_stamps'] ?? 0),
            'purchased_stamps'=>(int)($balanceSummary['purchased_stamps'] ?? 0),
            'used_stamps'=>(int)($balanceSummary['used_stamps'] ?? 0),
            'voided_stamps'=>(int)($balanceSummary['voided_stamps'] ?? 0),
        ],
        'purchase_summary'=>[
            'count'=>count($purchaseRows),
            'gross_cents'=>mg_stamp_close_money_cents($purchaseRows),
            'currency'=>'USD',
        ],
        'reconciliation_summary'=>$reconSummary,
        'exceptions'=>array_slice($exceptions, 0, 50),
        'recent_ledger_entries'=>array_map(static fn(array $row): array => [
            'entry_id'=>(string)$row['public_id'],
            'account_user_id'=>(int)$row['account_user_id'],
            'actor_user_id'=>isset($row['actor_user_id']) ? (int)$row['actor_user_id'] : null,
            'actor_type'=>(string)$row['actor_type'],
            'entry_type'=>(string)$row['entry_type'],
            'action_key'=>(string)($row['action_key'] ?? ''),
            'delta'=>(int)$row['delta'],
            'balance_after'=>(int)$row['balance_after'],
            'source_type'=>(string)$row['source_type'],
            'source_id'=>(string)($row['source_id'] ?? ''),
            'reference'=>(string)($row['reference'] ?? ''),
            'reason_code'=>(string)($row['reason_code'] ?? ''),
            'created_at'=>$row['created_at'] ?? null,
        ], $recentLedgerStmt->fetchAll(PDO::FETCH_ASSOC)),
        'export_urls'=>[
            'ledger'=>'/api/stamps/ledger-export.php?type=ledger&period=' . rawurlencode($period),
            'reconciliation'=>'/api/stamps/ledger-export.php?type=reconciliation&period=' . rawurlencode($period),
        ],
        'generated_at'=>date('Y-m-d H:i:s'),
        'source_of_truth'=>'Stamp ledger entries, account balances, Stamp purchases, and payment intents remain the source of truth. This is a read-only monthly close report.',
    ], 'Stamp monthly close report loaded.');
} catch (Throwable $error) {
    mg_security_log('warning','stamps.monthly_close_unavailable','Stamp monthly close report unavailable.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_ok(['schema_ready'=>false,'read_only'=>true], 'Stamp monthly close report unavailable.');
}
