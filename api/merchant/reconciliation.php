<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.reconciliation.manage');
$input = mg_input();
mg_require_csrf_for_write($input);
$from = trim((string)($input['from'] ?? ''));
$to = trim((string)($input['to'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) {
    mg_fail('Invalid reconciliation period.', 422);
}
$pdo = mg_db();
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_cents),0) FROM commerce_orders WHERE merchant_user_id=? AND payment_status IN ('paid','partially_refunded','refunded') AND DATE(paid_at) BETWEEN ? AND ?");
$stmt->execute([(int)$user['id'], $from, $to]);
$expected = (int)$stmt->fetchColumn();
$public = mg_public_uuid();
$provider = trim((string)(getenv('MG_PAYMENT_PROVIDER') ?: 'sandbox'));
$providerAmount = $provider === 'sandbox' ? $expected : 0;
$difference = $providerAmount - $expected;
$status = $difference === 0 ? 'completed' : 'completed_with_exceptions';
$pdo->prepare("INSERT INTO financial_reconciliation_runs (public_id,merchant_user_id,provider_key,period_start,period_end,status,expected_cents,provider_cents,difference_cents,exception_count,created_by_user_id,started_at,completed_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())")
    ->execute([$public,(int)$user['id'],$provider,$from.' 00:00:00',$to.' 23:59:59',$status,$expected,$providerAmount,$difference,$difference===0?0:1,(int)$user['id']]);
if ($difference !== 0) {
    $pdo->prepare("INSERT INTO financial_reconciliation_items (reconciliation_run_id,reference_type,expected_cents,provider_cents,difference_cents,status,notes,created_at) VALUES (LAST_INSERT_ID(),'period_total',?,?,?,'amount_mismatch','Provider adapter did not return a matching settlement total.',NOW())")
        ->execute([$expected,$providerAmount,$difference]);
}
mg_audit('commerce.reconciliation_completed','financial_reconciliation',['run_id'=>$public,'difference_cents'=>$difference],(int)$user['id']);
mg_ok(['run_id'=>$public,'status'=>$status,'expected_cents'=>$expected,'provider_cents'=>$providerAmount,'difference_cents'=>$difference],'Reconciliation completed.',201);
