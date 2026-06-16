<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/payments/_refund.php';
require_once dirname(__DIR__).'/tests/integration/DisputeBehaviorFixture.php';

function mg_rr_assert(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
function mg_rr_scalar(PDO $pdo,string $sql,array $params=[]): mixed {$stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();}
function mg_rr_status(PDO $pdo,int $orderId,string $table,string $status): int
{
    $sql=$table==='microgift_instances'
        ? "SELECT COUNT(*) FROM microgift_instances mi INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND mi.status=?"
        : "SELECT COUNT(*) FROM pppm_items p INNER JOIN microgift_instances mi ON mi.pppm_item_id=p.id INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND p.status=?";
    return (int)mg_rr_scalar($pdo,$sql,[$orderId,$status]);
}
function mg_rr_balanced(PDO $pdo,int $groupId): bool
{
    $stmt=$pdo->prepare('SELECT entry_type,SUM(amount_cents) total FROM ledger_entries WHERE transaction_group_id=? GROUP BY entry_type');$stmt->execute([$groupId]);$s=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$s[(string)$row['entry_type']]=(int)$row['total'];
    return ($s['debit']??0)>0&&($s['debit']??0)===($s['credit']??-1);
}

$pdo=mg_db();$runId='refund_reconcile_'.bin2hex(random_bytes(8));
$summary=['suite'=>'refund_behavior','run_id'=>$runId,'partial_refund'=>false,'full_refund'=>false,'ledger_balanced'=>false,'exact_replay'=>false,'conflicting_replay_rejected'=>false,'entitlement_policy'=>false,'pppm_preserved'=>false,'receipt_and_audit_consistent'=>false,'notifications_created_once'=>false,'forced_failure_rolled_back'=>false,'fixtures_clean'=>false];
$pdo->beginTransaction();
try{
    $buyerEmail=$runId.'-buyer@example.test';$merchantEmail=$runId.'-merchant@example.test';
    $buyerId=mg_it_user($pdo,$buyerEmail,'Refund Buyer');$merchantId=mg_it_user($pdo,$merchantEmail,'Refund Merchant');
    $catalog=mg_dispute_fixture_catalog($pdo,$merchantId,$runId);$fixture=['run_id'=>$runId,'buyer_id'=>$buyerId,'merchant_id'=>$merchantId,'buyer_email'=>$buyerEmail]+$catalog;
    $order=mg_dispute_fixture_order($pdo,$fixture,'success');$notificationsBefore=(int)mg_rr_scalar($pdo,'SELECT COUNT(*) FROM notifications');

    $partialInput=['order_id'=>$order['order_public'],'amount_cents'=>500,'reason'=>'requested_by_customer','idempotency_key'=>'refund:partial:'.$runId];
    $partial=mg_finance_refund_order($pdo,$merchantId,$merchantId,$partialInput);
    mg_rr_assert($partial['order_status']==='partially_refunded','Partial refund failed.');
    mg_rr_assert(mg_rr_status($pdo,$order['order_id'],'microgift_instances','issued')===2&&mg_rr_status($pdo,$order['order_id'],'pppm_items','available')===2,'Partial refund changed unit state.');
    mg_rr_assert((int)mg_rr_scalar($pdo,"SELECT COUNT(*) FROM entitlement_review_items WHERE commerce_order_id=? AND review_type='partial_refund' AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.microgift_review_type'))='microgift_partial_refund'",[$order['order_id']])===1,'Microgift partial review missing.');
    $summary['partial_refund']=true;

    mg_rr_assert(mg_finance_refund_order($pdo,$merchantId,$merchantId,$partialInput)['duplicate']===true,'Refund replay failed.');
    mg_rr_assert((int)mg_rr_scalar($pdo,'SELECT COUNT(*) FROM payment_refunds WHERE merchant_user_id=? AND idempotency_key=?',[$merchantId,$partialInput['idempotency_key']])===1,'Refund replay duplicated rows.');
    $summary['exact_replay']=true;
    $conflict=$partialInput;$conflict['amount_cents']=600;$rejected=false;
    try{mg_finance_refund_order($pdo,$merchantId,$merchantId,$conflict);}catch(MgRefundException $e){$rejected=$e->httpStatus===409;}
    mg_rr_assert($rejected,'Conflicting refund replay was accepted.');$summary['conflicting_replay_rejected']=true;

    $full=mg_finance_refund_order($pdo,$merchantId,$merchantId,['order_id'=>$order['order_public'],'amount_cents'=>2000,'reason'=>'requested_by_customer','idempotency_key'=>'refund:full:'.$runId]);
    mg_rr_assert($full['order_status']==='refunded','Full refund failed.');
    mg_rr_assert(mg_rr_status($pdo,$order['order_id'],'microgift_instances','revoked')===2&&mg_rr_status($pdo,$order['order_id'],'pppm_items','refunded')===2,'Full refund did not reconcile units.');
    mg_rr_assert((int)mg_rr_scalar($pdo,"SELECT COUNT(*) FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=? AND e.status='revoked'",[$order['order_id']])===2,'Full refund did not revoke entitlements.');
    $summary['full_refund']=$summary['entitlement_policy']=$summary['pppm_preserved']=true;

    $groups=$pdo->prepare("SELECT id FROM ledger_transaction_groups WHERE source_type='payment_refund' AND source_reference IN (?,?) ORDER BY id");$groups->execute([$partial['refund_id'],$full['refund_id']]);$rows=$groups->fetchAll(PDO::FETCH_COLUMN);
    mg_rr_assert(count($rows)===2,'Refund ledger groups missing.');foreach($rows as $id)mg_rr_assert(mg_rr_balanced($pdo,(int)$id),'Refund ledger is unbalanced.');$summary['ledger_balanced']=true;
    mg_rr_assert((string)mg_rr_scalar($pdo,'SELECT status FROM receipts WHERE id=?',[$order['receipt_id']])==='finalized','Refund changed receipt history.');
    mg_rr_assert((int)mg_rr_scalar($pdo,"SELECT COUNT(*) FROM order_audit_events WHERE order_id=? AND event_type='payment.refunded'",[$order['order_id']])===2,'Refund audit history is inconsistent.');$summary['receipt_and_audit_consistent']=true;
    mg_rr_assert((int)mg_rr_scalar($pdo,'SELECT COUNT(*) FROM notifications')===$notificationsBefore+4,'Refund notifications are inconsistent.');$summary['notifications_created_once']=true;

    $failure=mg_dispute_fixture_order($pdo,$fixture,'failure');$before=(int)mg_rr_scalar($pdo,'SELECT COUNT(*) FROM payment_refunds');$pdo->exec('SAVEPOINT refund_failure');$failed=false;
    try{mg_finance_refund_order($pdo,$merchantId,$merchantId,['order_id'=>$failure['order_public'],'amount_cents'=>2500,'reason'=>'merchant_error','idempotency_key'=>'refund:failure:'.$runId],static function(string $stage): void {if($stage==='after_microgift_reconciliation')throw new RuntimeException('Forced refund failure.');});}
    catch(Throwable){$failed=true;$pdo->exec('ROLLBACK TO SAVEPOINT refund_failure');}
    mg_rr_assert($failed&&(int)mg_rr_scalar($pdo,'SELECT COUNT(*) FROM payment_refunds')===$before,'Forced refund failure left state.');
    mg_rr_assert(mg_rr_status($pdo,$failure['order_id'],'microgift_instances','issued')===2&&mg_rr_status($pdo,$failure['order_id'],'pppm_items','available')===2,'Forced failure left unit state.');$summary['forced_failure_rolled_back']=true;

    $pdo->rollBack();
    mg_rr_assert((int)mg_rr_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$buyerEmail,$merchantEmail])===0,'Refund fixtures remain.');$summary['fixtures_clean']=true;
    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$summary['error']=$e->getMessage();fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);throw $e;}
