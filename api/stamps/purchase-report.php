<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';
$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.view') && !mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
mg_require_method('GET');

function mg_stamp_purchase_report_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function mg_stamp_purchase_report_state(array $row): array
{
    $purchaseStatus = (string)($row['status'] ?? '');
    $intentStatus = (string)($row['payment_intent_status'] ?? '');
    $ledgerId = trim((string)($row['credited_ledger_entry_public_id'] ?? ''));
    $intentId = trim((string)($row['payment_intent_id'] ?? ''));
    $amountMismatch = $intentId !== '' && ((int)($row['payment_amount_cents'] ?? 0) !== (int)($row['price_cents_snapshot'] ?? 0) || !hash_equals((string)($row['payment_currency'] ?? ''), (string)($row['currency_snapshot'] ?? '')));

    if ($intentId === '') return ['state' => 'missing_intent', 'severity' => 'error', 'label' => 'Missing payment intent'];
    if ($amountMismatch) return ['state' => 'amount_review', 'severity' => 'error', 'label' => 'Amount/currency review'];
    if ($purchaseStatus === 'credited') {
        if ($ledgerId === '') return ['state' => 'ledger_review', 'severity' => 'warning', 'label' => 'Credited without ledger ID'];
        if ($intentStatus !== 'succeeded') return ['state' => 'payment_review', 'severity' => 'warning', 'label' => 'Ledger credited, payment not succeeded'];
        return ['state' => 'reconciled', 'severity' => 'success', 'label' => 'Reconciled'];
    }
    if (in_array($purchaseStatus, ['failed', 'cancelled'], true) || in_array($intentStatus, ['failed', 'cancelled'], true)) return ['state' => 'failed_payment', 'severity' => 'error', 'label' => 'Failed/cancelled payment'];
    if (in_array($intentStatus, ['requires_action', 'processing', 'created', 'requires_payment_method'], true) || in_array($purchaseStatus, ['pending', 'checkout_created'], true)) return ['state' => 'awaiting_webhook', 'severity' => 'info', 'label' => 'Awaiting payment/webhook'];
    return ['state' => 'review', 'severity' => 'warning', 'label' => 'Needs review'];
}

function mg_stamp_purchase_report_webhook(PDO $pdo, bool $hasWebhooks, string $purchaseId, string $providerIntent): array
{
    if (!$hasWebhooks) return ['available' => false, 'status' => '', 'event_type' => '', 'provider_event_id' => '', 'received_at' => null, 'processed_at' => null, 'failure_message' => ''];
    $likePurchase = '%' . $purchaseId . '%';
    $likeIntent = $providerIntent !== '' ? '%' . $providerIntent . '%' : $likePurchase;
    $stmt = $pdo->prepare('SELECT provider_event_id,event_type,status,received_at,processed_at,failure_message FROM payment_webhook_events WHERE payload_json LIKE ? OR payload_json LIKE ? ORDER BY received_at DESC,id DESC LIMIT 1');
    $stmt->execute([$likePurchase, $likeIntent]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return ['available' => true, 'status' => 'none', 'event_type' => '', 'provider_event_id' => '', 'received_at' => null, 'processed_at' => null, 'failure_message' => ''];
    return [
        'available' => true,
        'status' => (string)$row['status'],
        'event_type' => (string)$row['event_type'],
        'provider_event_id' => (string)$row['provider_event_id'],
        'received_at' => $row['received_at'] ?? null,
        'processed_at' => $row['processed_at'] ?? null,
        'failure_message' => (string)($row['failure_message'] ?? ''),
    ];
}

$pdo = mg_db();
try {
    $hasWebhooks = mg_stamp_purchase_report_table_exists($pdo, 'payment_webhook_events');
    $stmt = $pdo->query("SELECT sp.public_id,sp.account_user_id,sp.bundle_key,sp.label_snapshot,sp.stamps_snapshot,sp.price_cents_snapshot,sp.currency_snapshot,sp.status,sp.checkout_reference,sp.credited_ledger_entry_public_id,sp.created_at,sp.updated_at,sp.paid_at,sp.credited_at,
            pi.public_id payment_intent_id,pi.provider_key,pi.provider_intent_reference,pi.amount_cents payment_amount_cents,pi.currency payment_currency,pi.status payment_intent_status,pi.failure_code,pi.failure_message,pi.created_at payment_created_at,pi.updated_at payment_updated_at
        FROM stamp_purchases sp
        LEFT JOIN payment_intents pi ON pi.source_type='stamp_purchase' AND pi.source_reference=sp.public_id
        ORDER BY sp.created_at DESC,sp.id DESC
        LIMIT 100");
    $rows = [];
    $summary = ['total'=>0,'reconciled'=>0,'awaiting_webhook'=>0,'failed_payment'=>0,'missing_intent'=>0,'amount_review'=>0,'ledger_review'=>0,'payment_review'=>0,'review'=>0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $state = mg_stamp_purchase_report_state($row);
        $key = (string)$state['state'];
        $summary['total']++;
        if (!array_key_exists($key, $summary)) $summary[$key] = 0;
        $summary[$key]++;
        $providerIntent = (string)($row['provider_intent_reference'] ?? '');
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
            'updated_at'=>$row['updated_at'] ?? null,
            'paid_at'=>$row['paid_at'] ?? null,
            'credited_at'=>$row['credited_at'] ?? null,
            'payment_intent'=>[
                'id'=>(string)($row['payment_intent_id'] ?? ''),
                'provider_key'=>(string)($row['provider_key'] ?? ''),
                'provider_intent_reference'=>$providerIntent,
                'amount_cents'=>(int)($row['payment_amount_cents'] ?? 0),
                'currency'=>(string)($row['payment_currency'] ?? ''),
                'status'=>(string)($row['payment_intent_status'] ?? ''),
                'failure_code'=>(string)($row['failure_code'] ?? ''),
                'failure_message'=>(string)($row['failure_message'] ?? ''),
                'created_at'=>$row['payment_created_at'] ?? null,
                'updated_at'=>$row['payment_updated_at'] ?? null,
            ],
            'webhook_event'=>mg_stamp_purchase_report_webhook($pdo, $hasWebhooks, (string)$row['public_id'], $providerIntent),
            'reconciliation_state'=>$state,
        ];
    }
    mg_ok(['purchases'=>$rows,'summary'=>$summary,'count'=>count($rows),'schema_ready'=>true]);
} catch (Throwable $error) {
    mg_security_log('warning','stamps.purchase_report_unavailable','Stamp purchase report unavailable.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_ok(['purchases'=>[], 'summary'=>['total'=>0], 'count'=>0, 'schema_ready'=>false], 'Stamp purchase report unavailable until migration is installed.');
}
