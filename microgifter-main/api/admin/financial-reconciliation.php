<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/finance/_money.php';
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=mg_require_permission('financial.reconciliation.manage');
$pdo=mg_db();
if($method==='GET'){
    $stmt=$pdo->query('SELECT r.*,u.display_name created_by_name FROM financial_reconciliation_runs r LEFT JOIN users u ON u.id=r.created_by_user_id ORDER BY r.id DESC LIMIT 100');
    mg_ok(['runs'=>$stmt->fetchAll()]);
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);
$from=trim((string)($input['from']??''));$to=trim((string)($input['to']??''));$merchantUserId=(int)($input['merchant_user_id']??0);
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)||$from>$to)mg_fail('Invalid reconciliation period.',422);
$provider=trim((string)(getenv('MG_PAYMENT_PROVIDER')?:'sandbox'));
$pdo->beginTransaction();
try{
    $where='DATE(o.paid_at) BETWEEN ? AND ?';$params=[$from,$to];
    if($merchantUserId>0){$where.=' AND o.merchant_user_id=?';$params[]=$merchantUserId;}
    $orders=$pdo->prepare("SELECT COALESCE(SUM(o.total_cents),0) amount FROM commerce_orders o WHERE o.payment_status IN ('paid','partially_refunded','refunded','disputed') AND {$where}");$orders->execute($params);$orderCents=(int)$orders->fetchColumn();
    $ledgerSql="SELECT COALESCE(SUM(CASE WHEN e.entry_type='credit' THEN e.amount_cents ELSE -e.amount_cents END),0) amount FROM ledger_entries e INNER JOIN ledger_accounts a ON a.id=e.ledger_account_id INNER JOIN ledger_transaction_groups g ON g.id=e.transaction_group_id WHERE a.account_code='available' AND DATE(g.posted_at) BETWEEN ? AND ?";
    $ledgerParams=[$from,$to];if($merchantUserId>0){$ledgerSql.=' AND a.wallet_id IN (SELECT id FROM wallets WHERE owner_user_id=?)';$ledgerParams[]=$merchantUserId;}
    $ledger=$pdo->prepare($ledgerSql);$ledger->execute($ledgerParams);$ledgerCents=(int)$ledger->fetchColumn();
    $difference=$ledgerCents-$orderCents;$status=$difference===0?'completed':'completed_with_exceptions';$public=mg_public_uuid();
    $pdo->prepare("INSERT INTO financial_reconciliation_runs (public_id,merchant_user_id,provider_key,period_start,period_end,status,expected_cents,provider_cents,difference_cents,exception_count,created_by_user_id,started_at,completed_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())")
        ->execute([$public,$merchantUserId?:null,$provider,$from.' 00:00:00',$to.' 23:59:59',$status,$orderCents,$ledgerCents,$difference,$difference===0?0:1,(int)$user['id']]);
    $runId=(int)$pdo->lastInsertId();
    if($difference!==0){$pdo->prepare("INSERT INTO financial_reconciliation_items (reconciliation_run_id,reference_type,expected_cents,provider_cents,difference_cents,status,notes,created_at) VALUES (?,'orders_to_wallet_ledger',?,?,?,'amount_mismatch','Paid order total does not match wallet ledger availability postings.',NOW())")->execute([$runId,$orderCents,$ledgerCents,$difference]);mg_event('reconciliation.exception_found',['run_id'=>$public,'difference_cents'=>$difference],(int)$user['id']);}
    mg_event('reconciliation.run_started',['run_id'=>$public,'period_start'=>$from,'period_end'=>$to],(int)$user['id']);
    mg_audit('financial.reconciliation_completed','financial_reconciliation',['run_id'=>$public,'difference_cents'=>$difference],(int)$user['id']);
    $pdo->commit();mg_ok(['run_id'=>$public,'status'=>$status,'expected_cents'=>$orderCents,'ledger_cents'=>$ledgerCents,'difference_cents'=>$difference],'Reconciliation completed.',201);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to reconcile financial records.',500);}
