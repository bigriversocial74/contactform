<?php
declare(strict_types=1);
require_once __DIR__ . '/_purchases.php';

$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.view') && !mg_api_user_has_permission($user, 'admin.stamps.manage')) mg_fail('Permission denied.', 403);
mg_require_method('GET');

function mg_stamp_ops_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

function mg_stamp_ops_reconciliation_state(array $row): array
{
    $purchaseStatus = (string)($row['status'] ?? '');
    $intentStatus = (string)($row['payment_intent_status'] ?? '');
    $ledgerId = trim((string)($row['credited_ledger_entry_public_id'] ?? ''));
    $intentId = trim((string)($row['payment_intent_id'] ?? ''));
    $amountMismatch = $intentId !== '' && ((int)($row['payment_amount_cents'] ?? 0) !== (int)($row['price_cents_snapshot'] ?? 0) || !hash_equals((string)($row['payment_currency'] ?? ''), (string)($row['currency_snapshot'] ?? '')));
    if ($intentId === '') return ['state'=>'missing_intent','severity'=>'error','label'=>'Missing payment intent','priority'=>10];
    if ($amountMismatch) return ['state'=>'amount_review','severity'=>'error','label'=>'Amount/currency review','priority'=>20];
    if ($purchaseStatus === 'credited') {
        if ($ledgerId === '') return ['state'=>'ledger_review','severity'=>'warning','label'=>'Credited without ledger ID','priority'=>30];
        if ($intentStatus !== 'succeeded') return ['state'=>'payment_review','severity'=>'warning','label'=>'Ledger credited, payment not succeeded','priority'=>40];
        return ['state'=>'reconciled','severity'=>'success','label'=>'Reconciled','priority'=>90];
    }
    if ($intentStatus === 'succeeded') return ['state'=>'paid_uncredited','severity'=>'warning','label'=>'Paid provider intent, not credited','priority'=>15];
    if (in_array($purchaseStatus, ['failed','cancelled'], true) || in_array($intentStatus, ['failed','cancelled'], true)) return ['state'=>'failed_payment','severity'=>'error','label'=>'Failed/cancelled payment','priority'=>50];
    if (in_array($intentStatus, ['requires_action','processing','created','requires_payment_method'], true) || in_array($purchaseStatus, ['pending','checkout_created'], true)) return ['state'=>'awaiting_webhook','severity'=>'info','label'=>'Awaiting payment/webhook','priority'=>60];
    return ['state'=>'review','severity'=>'warning','label'=>'Needs review','priority'=>35];
}

function mg_stamp_ops_age_minutes(?string $timestamp): int
{
    if (!$timestamp) return 0;
    $then = strtotime($timestamp);
    if (!$then) return 0;
    return max(0, (int)floor((time() - $then) / 60));
}

function mg_stamp_ops_json(?string $json): array
{
    if ($json === null || trim($json) === '') return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

$pdo = mg_db();
try {
    foreach (['stamp_purchases','payment_intents'] as $requiredTable) {
        if (!mg_stamp_ops_table_exists($pdo, $requiredTable)) {
            mg_ok(['schema_ready'=>false,'summary'=>[],'risky_records'=>[],'recent_recovery_actions'=>[],'quick_links'=>[]], 'Stamp operations dashboard unavailable until Stamp purchase tables are installed.');
            return;
        }
    }

    $stmt = $pdo->query("SELECT sp.public_id,sp.account_user_id,sp.bundle_key,sp.label_snapshot,sp.stamps_snapshot,sp.price_cents_snapshot,sp.currency_snapshot,sp.status,sp.checkout_reference,sp.credited_ledger_entry_public_id,sp.created_at,sp.updated_at,sp.paid_at,sp.credited_at,
            pi.public_id payment_intent_id,pi.provider_key,pi.provider_intent_reference,pi.amount_cents payment_amount_cents,pi.currency payment_currency,pi.status payment_intent_status,pi.failure_code,pi.failure_message,pi.created_at payment_created_at,pi.updated_at payment_updated_at
        FROM stamp_purchases sp
        LEFT JOIN payment_intents pi ON pi.source_type='stamp_purchase' AND pi.source_reference=sp.public_id
        ORDER BY sp.created_at DESC,sp.id DESC
        LIMIT 250");

    $summary = ['total'=>0,'awaiting_webhook'=>0,'paid_uncredited'=>0,'failed_payment'=>0,'missing_intent'=>0,'amount_review'=>0,'ledger_review'=>0,'payment_review'=>0,'review'=>0,'reconciled'=>0,'needs_attention'=>0];
    $risky = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $state = mg_stamp_ops_reconciliation_state($row);
        $key = (string)$state['state'];
        $summary['total']++;
        if (!array_key_exists($key, $summary)) $summary[$key] = 0;
        $summary[$key]++;
        if ($key !== 'reconciled') $summary['needs_attention']++;
        if ($key !== 'reconciled') {
            $risky[] = [
                'id'=>(string)$row['public_id'],
                'account_user_id'=>(int)$row['account_user_id'],
                'bundle_key'=>(string)$row['bundle_key'],
                'label'=>(string)$row['label_snapshot'],
                'stamps'=>(int)$row['stamps_snapshot'],
                'price_cents'=>(int)$row['price_cents_snapshot'],
                'currency'=>(string)$row['currency_snapshot'],
                'purchase_status'=>(string)$row['status'],
                'payment_status'=>(string)($row['payment_intent_status'] ?? ''),
                'provider_key'=>(string)($row['provider_key'] ?? ''),
                'provider_intent_reference'=>(string)($row['provider_intent_reference'] ?? ''),
                'credited_ledger_entry_id'=>(string)($row['credited_ledger_entry_public_id'] ?? ''),
                'created_at'=>$row['created_at'] ?? null,
                'updated_at'=>$row['updated_at'] ?? null,
                'age_minutes'=>mg_stamp_ops_age_minutes($row['created_at'] ?? null),
                'reconciliation_state'=>$state,
                'reconciliation_url'=>'/stamp-payment-reconciliation.php?filter=' . rawurlencode($key) . '&q=' . rawurlencode((string)$row['public_id']),
            ];
        }
    }

    usort($risky, static function(array $a, array $b): int {
        $pa = (int)($a['reconciliation_state']['priority'] ?? 99);
        $pb = (int)($b['reconciliation_state']['priority'] ?? 99);
        if ($pa === $pb) return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
        return $pa <=> $pb;
    });
    $risky = array_slice($risky, 0, 30);

    $recoveryActions = [];
    if (mg_stamp_ops_table_exists($pdo, 'audit_logs')) {
        $auditStmt = $pdo->query("SELECT user_id,action,entity_type,metadata_json,created_at FROM audit_logs WHERE action LIKE 'stamps.%' AND (action LIKE '%reconciliation%' OR action LIKE '%webhook%' OR action LIKE '%provider_status%' OR action LIKE '%paid_uncredited%' OR action LIKE '%recovery%' OR action LIKE '%completion_review%' OR action LIKE '%admin_verified%') ORDER BY created_at DESC,id DESC LIMIT 25");
        foreach ($auditStmt->fetchAll(PDO::FETCH_ASSOC) as $audit) {
            $meta = mg_stamp_ops_json((string)($audit['metadata_json'] ?? ''));
            $purchaseId = (string)($meta['purchase_id'] ?? ($meta['source_reference'] ?? ''));
            $recoveryActions[] = [
                'action'=>(string)$audit['action'],
                'entity_type'=>(string)$audit['entity_type'],
                'actor_user_id'=>isset($audit['user_id']) ? (int)$audit['user_id'] : null,
                'purchase_id'=>$purchaseId,
                'provider_key'=>(string)($meta['provider_key'] ?? ''),
                'provider_intent_reference'=>(string)($meta['provider_intent_reference'] ?? ''),
                'created_at'=>$audit['created_at'] ?? null,
                'metadata'=>$meta,
                'reconciliation_url'=>$purchaseId !== '' ? '/stamp-payment-reconciliation.php?q=' . rawurlencode($purchaseId) : '/stamp-payment-reconciliation.php?filter=review',
            ];
        }
    }

    $quickLinks = [
        ['key'=>'needs_attention','label'=>'Needs attention','count'=>(int)$summary['needs_attention'],'url'=>'/stamp-payment-reconciliation.php?filter=review'],
        ['key'=>'paid_uncredited','label'=>'Paid / uncredited','count'=>(int)$summary['paid_uncredited'],'url'=>'/stamp-payment-reconciliation.php?filter=paid_uncredited'],
        ['key'=>'awaiting_webhook','label'=>'Awaiting webhook','count'=>(int)$summary['awaiting_webhook'],'url'=>'/stamp-payment-reconciliation.php?filter=awaiting_webhook'],
        ['key'=>'failed_payment','label'=>'Failed / cancelled','count'=>(int)$summary['failed_payment'],'url'=>'/stamp-payment-reconciliation.php?filter=failed_payment'],
        ['key'=>'amount_review','label'=>'Amount review','count'=>(int)$summary['amount_review'],'url'=>'/stamp-payment-reconciliation.php?filter=amount_review'],
        ['key'=>'ledger_review','label'=>'Ledger review','count'=>(int)$summary['ledger_review'],'url'=>'/stamp-payment-reconciliation.php?filter=ledger_review'],
        ['key'=>'reconciled','label'=>'Reconciled','count'=>(int)$summary['reconciled'],'url'=>'/stamp-payment-reconciliation.php?filter=reconciled'],
    ];

    mg_ok([
        'schema_ready'=>true,
        'summary'=>$summary,
        'quick_links'=>$quickLinks,
        'risky_records'=>$risky,
        'recent_recovery_actions'=>$recoveryActions,
        'generated_at'=>date('Y-m-d H:i:s'),
        'read_only'=>true,
        'source_of_truth'=>'Stamp purchase ledger and payment reconciliation tables remain the source of truth. This dashboard is a read-only operations summary.',
    ], 'Stamp operations dashboard loaded.');
} catch (Throwable $error) {
    mg_security_log('warning','stamps.operations_dashboard_unavailable','Stamp operations dashboard unavailable.', ['exception_class'=>$error::class], (int)$user['id']);
    mg_ok(['schema_ready'=>false,'summary'=>[],'risky_records'=>[],'recent_recovery_actions'=>[],'quick_links'=>[]], 'Stamp operations dashboard unavailable until required Stamp tables are installed.');
}
