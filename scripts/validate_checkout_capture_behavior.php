<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/commerce/_checkout.php';
require_once dirname(__DIR__).'/api/commerce/_order_issuance_summary.php';
require_once dirname(__DIR__).'/api/payments/_checkout_session.php';
require_once dirname(__DIR__).'/tests/integration/CheckoutBehaviorFixture.php';

function mg_checkout_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}
function mg_checkout_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
}
function mg_checkout_balanced(PDO $pdo,int $groupId): bool
{
    $stmt=$pdo->prepare('SELECT entry_type,SUM(amount_cents) total FROM ledger_entries WHERE transaction_group_id=? GROUP BY entry_type');
    $stmt->execute([$groupId]);$s=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$s[(string)$row['entry_type']]=(int)$row['total'];
    return ($s['debit']??0)>0&&($s['debit']??0)===($s['credit']??-1);
}
function mg_checkout_order_microgift_count(PDO $pdo,int $orderId): int
{
    return (int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM microgift_instances mi INNER JOIN commerce_order_items oi ON oi.id=mi.commerce_order_item_id WHERE oi.order_id=? AND mi.source_type=?',[$orderId,'commerce_order_item']);
}
function mg_checkout_order_action_count(PDO $pdo,int $orderId,int $userId): int
{
    return (int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM microgift_inbox_items ac INNER JOIN microgift_instances mi ON mi.id=ac.instance_id INNER JOIN commerce_order_items oi ON oi.id=mi.commerce_order_item_id WHERE oi.order_id=? AND ac.user_id=?',[$orderId,$userId]);
}

$pdo=mg_db();
$runId='checkout_'.bin2hex(random_bytes(8));
$summary=[
    'suite'=>'checkout_capture_issuance_fulfillment_behavior','run_id'=>$runId,
    'checkout_created'=>false,'checkout_replay'=>false,'checkout_conflict_rejected'=>false,
    'session_created'=>false,'session_intent_linked'=>false,'session_replay'=>false,
    'session_conflict_rejected'=>false,'expired_session_recovery'=>false,
    'capture_completed'=>false,'capture_replay'=>false,'capture_conflict_rejected'=>false,
    'ledger_balanced'=>false,'merchant_balance_reconciled'=>false,'pppm_issued'=>false,
    'pppm_issuer_authoritative'=>false,'entitlements_granted'=>false,'asset_access_gated'=>false,
    'microgifts_issued'=>false,'microgifts_linked_to_pppm'=>false,'action_center_projected'=>false,
    'buyer_inbox_only'=>false,'issuance_summary_complete'=>false,'fulfillment_replay_safe'=>false,
    'lifecycle_ready'=>false,'receipt_consistent'=>false,'audit_consistent'=>false,'notifications_once'=>false,
    'failed_capture_no_fulfillment'=>false,'post_ledger_failure_rolled_back'=>false,
    'post_fulfillment_failure_rolled_back'=>false,'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $buyerEmail=$runId.'-buyer@example.test';
    $merchantEmail=$runId.'-merchant@example.test';
    $buyerId=mg_it_user($pdo,$buyerEmail,'Checkout Buyer');
    $merchantId=mg_it_user($pdo,$merchantEmail,'Checkout Merchant');
    $catalog=mg_checkout_fixture_catalog($pdo,$merchantId,$runId);
    $fixture=['run_id'=>$runId,'buyer_id'=>$buyerId,'merchant_id'=>$merchantId]+$catalog;

    $draft=mg_checkout_fixture_draft($pdo,$fixture,'success');
    mg_checkout_assert(mg_entitlement_resolve_active($pdo,$buyerId,$catalog['asset_public'])===null,'Asset was accessible before payment.');
    $orderResult=mg_checkout_create_order($pdo,$buyerId,$draft['draft_public'],'checkout-order-'.$runId);
    mg_checkout_assert($orderResult['duplicate']===false,'Checkout order was not created.');
    $orderPublic=(string)$orderResult['order']['order_id'];
    $orderId=(int)mg_checkout_scalar($pdo,'SELECT id FROM commerce_orders WHERE public_id=?',[$orderPublic]);
    $summary['checkout_created']=true;

    $orderReplay=mg_checkout_create_order($pdo,$buyerId,$draft['draft_public'],'checkout-order-'.$runId);
    mg_checkout_assert($orderReplay['duplicate']===true,'Exact checkout replay was not idempotent.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM commerce_orders WHERE buyer_user_id=? AND idempotency_key=?',[$buyerId,'checkout-order-'.$runId])===1,'Checkout replay duplicated order.');
    $summary['checkout_replay']=true;

    $conflictDraft=mg_checkout_fixture_draft($pdo,$fixture,'conflict');
    $checkoutConflict=false;
    try{mg_checkout_create_order($pdo,$buyerId,$conflictDraft['draft_public'],'checkout-order-'.$runId);}catch(MgCheckoutWorkflowException $error){$checkoutConflict=$error->httpStatus===409;}
    mg_checkout_assert($checkoutConflict,'Conflicting checkout replay was accepted.');
    $summary['checkout_conflict_rejected']=true;

    $session=mg_payment_create_checkout_session($pdo,$buyerId,$orderPublic,'checkout-session-'.$runId);
    mg_checkout_assert($session['duplicate']===false,'Payment session was not created.');
    $intentId=(int)mg_checkout_scalar($pdo,'SELECT id FROM payment_intents WHERE public_id=?',[$session['payment_intent_id']]);
    $linkedIntentId=(int)mg_checkout_scalar($pdo,'SELECT payment_intent_id FROM checkout_sessions WHERE public_id=?',[$session['checkout_session_id']]);
    mg_checkout_assert($intentId>0&&$linkedIntentId===$intentId,'Checkout session is not linked to its payment intent.');
    $summary['session_created']=true;
    $summary['session_intent_linked']=true;

    $sessionReplay=mg_payment_create_checkout_session($pdo,$buyerId,$orderPublic,'checkout-session-'.$runId);
    mg_checkout_assert($sessionReplay['duplicate']===true,'Payment session replay was not idempotent.');
    $summary['session_replay']=true;
    $sessionConflict=false;
    try{mg_payment_create_checkout_session($pdo,$buyerId,$orderPublic,'checkout-session-conflict-'.$runId);}catch(MgCheckoutSessionException $error){$sessionConflict=$error->httpStatus===409;}
    mg_checkout_assert($sessionConflict,'Conflicting payment session replay was accepted.');
    $summary['session_conflict_rejected']=true;

    $recoveryDraft=mg_checkout_fixture_draft($pdo,$fixture,'session-recovery');
    $recoveryOrder=mg_checkout_create_order($pdo,$buyerId,$recoveryDraft['draft_public'],'session-recovery-order-'.$runId);
    $recoveryFirst=mg_payment_create_checkout_session($pdo,$buyerId,$recoveryOrder['order']['order_id'],'session-recovery-first-'.$runId);
    $pdo->prepare("UPDATE checkout_sessions SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE public_id=?")->execute([$recoveryFirst['checkout_session_id']]);
    $recoverySecond=mg_payment_create_checkout_session($pdo,$buyerId,$recoveryOrder['order']['order_id'],'session-recovery-second-'.$runId);
    mg_checkout_assert($recoverySecond['checkout_session_id']!==$recoveryFirst['checkout_session_id'],'Expired checkout session was reused.');
    mg_checkout_assert((string)mg_checkout_scalar($pdo,'SELECT status FROM checkout_sessions WHERE public_id=?',[$recoveryFirst['checkout_session_id']])==='expired','Expired checkout session was not closed.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT payment_intent_id FROM checkout_sessions WHERE public_id=?',[$recoverySecond['checkout_session_id']])>0,'Replacement checkout session is not linked.');
    $summary['expired_session_recovery']=true;

    $providerReference='provider-capture-'.$runId;
    $captured=mg_finance_record_paid_order($pdo,$orderId,$intentId,$providerReference,$buyerId);
    mg_checkout_assert($captured['payment_transitioned']===true&&(int)$captured['issued_count']===2&&(int)$captured['microgift_issued_count']===2,'Capture did not complete paid-order fulfillment.');
    mg_checkout_assert((string)mg_checkout_scalar($pdo,'SELECT payment_status FROM commerce_orders WHERE id=?',[$orderId])==='paid','Order was not paid.');
    mg_checkout_assert((string)mg_checkout_scalar($pdo,'SELECT fulfillment_status FROM commerce_orders WHERE id=?',[$orderId])==='issued','Order was not fulfilled.');
    $summary['capture_completed']=true;

    $groupId=(int)mg_checkout_scalar($pdo,'SELECT id FROM ledger_transaction_groups WHERE idempotency_key=?',['order:paid:'.$orderPublic]);
    mg_checkout_assert($groupId>0&&mg_checkout_balanced($pdo,$groupId),'Paid-order ledger group is missing or unbalanced.');
    $summary['ledger_balanced']=true;
    $wallet=mg_wallet_resolve($pdo,'merchant',$merchantId,'USD');
    $balances=mg_wallet_balances($pdo,(int)$wallet['id']);
    mg_checkout_assert((int)$balances['available_cents']===2500,'Merchant available balance does not match paid order policy.');
    $summary['merchant_balance_reconciled']=true;

    $requestId=(int)mg_checkout_scalar($pdo,'SELECT pppm_issuance_request_id FROM commerce_order_items WHERE order_id=?',[$orderId]);
    mg_checkout_assert($requestId>0&&(int)mg_checkout_scalar($pdo,'SELECT issued_count FROM pppm_issuance_requests WHERE id=?',[$requestId])===2,'PPPM issuance request is wrong.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM pppm_items WHERE issuance_request_id=? AND owner_user_id=? AND status=?',[$requestId,$buyerId,'available'])===2,'PPPM ownership or quantity is wrong.');
    $summary['pppm_issued']=true;
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM pppm_items WHERE issuance_request_id=? AND issuer_user_id=? AND merchant_user_id=?',[$requestId,$merchantId,$merchantId])===2,'PPPM issuer authority does not match the selling merchant.');
    $summary['pppm_issuer_authoritative']=true;

    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=? AND e.entitled_user_id=? AND e.status=?',[$orderId,$buyerId,'active'])===2,'Entitlements were not granted exactly once.');
    $summary['entitlements_granted']=true;
    mg_checkout_assert(mg_entitlement_resolve_active($pdo,$buyerId,$catalog['asset_public'])!==null,'Asset was not accessible after payment.');
    $summary['asset_access_gated']=true;

    mg_checkout_assert(mg_checkout_order_microgift_count($pdo,$orderId)===2,'Paid order did not issue one Microgift per purchased unit.');
    $summary['microgifts_issued']=true;
    mg_checkout_assert((int)mg_checkout_scalar($pdo,"SELECT COUNT(DISTINCT mi.pppm_item_id) FROM microgift_instances mi INNER JOIN commerce_order_items oi ON oi.id=mi.commerce_order_item_id INNER JOIN pppm_items p ON p.id=mi.pppm_item_id WHERE oi.order_id=? AND mi.source_type='commerce_order_item' AND mi.issuer_user_id=? AND mi.owner_user_id=? AND mi.recipient_user_id=? AND p.owner_user_id=? AND p.status='available'",[$orderId,$merchantId,$buyerId,$buyerId,$buyerId])===2,'Purchased Microgifts are not linked one-to-one with buyer-owned PPPM items.');
    $summary['microgifts_linked_to_pppm']=true;

    mg_checkout_assert(mg_checkout_order_action_count($pdo,$orderId,$buyerId)===2,'Paid-order Microgifts were not projected into the buyer Action Center exactly once.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,"SELECT COUNT(*) FROM microgift_inbox_items ac INNER JOIN microgift_instances mi ON mi.id=ac.instance_id INNER JOIN commerce_order_items oi ON oi.id=mi.commerce_order_item_id WHERE oi.order_id=? AND ac.user_id=? AND ac.folder='inbox' AND ac.state='claimable'",[$orderId,$buyerId])===2,'Paid-order buyer Action Center state is inconsistent.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,"SELECT COUNT(*) FROM microgift_inbox_items ac INNER JOIN microgift_instances mi ON mi.id=ac.instance_id INNER JOIN commerce_order_items oi ON oi.id=mi.commerce_order_item_id WHERE oi.order_id=? AND ac.user_id=? AND ac.folder='sent'",[$orderId,$merchantId])===0,'A merchant Sent item was incorrectly created for a customer purchase.');
    $summary['action_center_projected']=true;
    $summary['buyer_inbox_only']=true;

    $orderRow=$pdo->prepare('SELECT * FROM commerce_orders WHERE id=?');$orderRow->execute([$orderId]);
    $issuanceSummary=mg_order_issuance_summary($pdo,$orderRow->fetch(PDO::FETCH_ASSOC),$buyerId);
    mg_checkout_assert($issuanceSummary['complete']===true&&$issuanceSummary['expected_units']===2,'Order issuance summary is incomplete.');
    $summary['issuance_summary_complete']=true;
    mg_checkout_assert((int)mg_checkout_scalar($pdo,"SELECT COUNT(*) FROM microgift_instances mi INNER JOIN commerce_order_items oi ON oi.id=mi.commerce_order_item_id INNER JOIN pppm_items p ON p.id=mi.pppm_item_id WHERE oi.order_id=? AND mi.status='issued' AND p.owner_user_id=mi.owner_user_id",[$orderId])===2,'Purchased Microgifts are not lifecycle-ready.');
    $summary['lifecycle_ready']=true;

    $receipt=$pdo->prepare('SELECT * FROM receipts WHERE order_id=? LIMIT 1');
    $receipt->execute([$orderId]);$receiptRow=$receipt->fetch(PDO::FETCH_ASSOC);
    mg_checkout_assert((string)$receiptRow['status']==='finalized'&&(int)$receiptRow['subtotal_cents']===2500&&(int)$receiptRow['total_cents']===2500&&(int)$receiptRow['tax_cents']===0&&(int)$receiptRow['platform_fee_cents']===0,'Receipt snapshot is inconsistent.');
    $summary['receipt_consistent']=true;
    mg_checkout_assert((int)mg_checkout_scalar($pdo,"SELECT COUNT(*) FROM order_status_history WHERE order_id=? AND status_domain='payment' AND to_status='paid'",[$orderId])===1,'Payment status history count is wrong.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,"SELECT COUNT(*) FROM order_audit_events WHERE order_id=? AND event_type='payment.captured'",[$orderId])===1,'Payment audit count is wrong.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,"SELECT COUNT(*) FROM order_audit_events WHERE order_id=? AND event_type='microgift.issued_from_paid_order'",[$orderId])===1,'Microgift fulfillment audit event is missing or duplicated.');
    $summary['audit_consistent']=true;
    mg_checkout_assert((int)mg_checkout_scalar($pdo,"SELECT COUNT(*) FROM notifications WHERE user_id IN (?,?) AND type IN ('payment_succeeded','merchant_payment_received')",[$buyerId,$merchantId])===2,'Capture notifications were not created once.');
    $summary['notifications_once']=true;

    $replay=mg_finance_record_paid_order($pdo,$orderId,$intentId,$providerReference,$buyerId);
    mg_checkout_assert($replay['payment_transitioned']===false,'Exact capture replay transitioned twice.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM payment_transactions WHERE payment_intent_id=?',[$intentId])===1,'Capture replay duplicated sale transaction.');
    mg_checkout_assert(mg_checkout_order_microgift_count($pdo,$orderId)===2,'Capture replay duplicated Microgifts.');
    mg_checkout_assert(mg_checkout_order_action_count($pdo,$orderId,$buyerId)===2,'Capture replay duplicated Action Center projections.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM pppm_items WHERE issuance_request_id=?',[$requestId])===2,'Capture replay duplicated PPPM items.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=?',[$orderId])===2,'Capture replay duplicated entitlements.');
    $summary['capture_replay']=true;
    $summary['fulfillment_replay_safe']=true;
    $captureConflict=false;
    try{mg_finance_record_paid_order($pdo,$orderId,$intentId,'provider-conflict-'.$runId,$buyerId);}catch(MgCaptureWorkflowException $error){$captureConflict=$error->httpStatus===409;}
    mg_checkout_assert($captureConflict,'Conflicting capture replay was accepted.');
    $summary['capture_conflict_rejected']=true;

    $failedDraft=mg_checkout_fixture_draft($pdo,$fixture,'failed');
    $failedOrder=mg_checkout_create_order($pdo,$buyerId,$failedDraft['draft_public'],'failed-order-'.$runId);
    $failedOrderId=(int)mg_checkout_scalar($pdo,'SELECT id FROM commerce_orders WHERE public_id=?',[$failedOrder['order']['order_id']]);
    $failedSession=mg_payment_create_checkout_session($pdo,$buyerId,$failedOrder['order']['order_id'],'failed-session-'.$runId);
    $failedIntentId=(int)mg_checkout_scalar($pdo,'SELECT id FROM payment_intents WHERE public_id=?',[$failedSession['payment_intent_id']]);
    $pdo->prepare("UPDATE payment_intents SET status='failed',failure_code='declined',failure_message='Behavior decline' WHERE id=?")->execute([$failedIntentId]);
    $failedCapture=false;
    try{mg_finance_record_paid_order($pdo,$failedOrderId,$failedIntentId,'failed-provider-'.$runId,$buyerId);}catch(MgCaptureWorkflowException){$failedCapture=true;}
    mg_checkout_assert($failedCapture,'Failed payment intent was captured.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM pppm_issuance_requests WHERE source_reference=?',[$failedOrder['order']['order_id']])===0,'Failed capture created PPPM issuance.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=?',[$failedOrderId])===0,'Failed capture created entitlements.');
    mg_checkout_assert(mg_checkout_order_microgift_count($pdo,$failedOrderId)===0,'Failed capture created Microgifts.');
    mg_checkout_assert(mg_checkout_order_action_count($pdo,$failedOrderId,$buyerId)===0,'Failed capture created Action Center projections.');
    $summary['failed_capture_no_fulfillment']=true;

    $rollbackDraft=mg_checkout_fixture_draft($pdo,$fixture,'rollback');
    $rollbackOrder=mg_checkout_create_order($pdo,$buyerId,$rollbackDraft['draft_public'],'rollback-order-'.$runId);
    $rollbackOrderId=(int)mg_checkout_scalar($pdo,'SELECT id FROM commerce_orders WHERE public_id=?',[$rollbackOrder['order']['order_id']]);
    $rollbackSession=mg_payment_create_checkout_session($pdo,$buyerId,$rollbackOrder['order']['order_id'],'rollback-session-'.$runId);
    $rollbackIntentId=(int)mg_checkout_scalar($pdo,'SELECT id FROM payment_intents WHERE public_id=?',[$rollbackSession['payment_intent_id']]);
    $beforeGroups=(int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups');
    $beforeNotifications=(int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM notifications');
    $pdo->exec('SAVEPOINT checkout_post_ledger_failure');$forced=false;
    try{mg_finance_record_paid_order($pdo,$rollbackOrderId,$rollbackIntentId,'rollback-provider-'.$runId,$buyerId,static function(string $stage): void {if($stage==='after_ledger')throw new RuntimeException('Forced post-ledger failure.');});}catch(Throwable){$forced=true;$pdo->exec('ROLLBACK TO SAVEPOINT checkout_post_ledger_failure');}
    mg_checkout_assert($forced,'Forced post-ledger failure did not throw.');
    mg_checkout_assert((string)mg_checkout_scalar($pdo,'SELECT payment_status FROM commerce_orders WHERE id=?',[$rollbackOrderId])==='unpaid','Post-ledger failure left order paid.');
    mg_checkout_assert((string)mg_checkout_scalar($pdo,'SELECT status FROM payment_intents WHERE id=?',[$rollbackIntentId])==='created','Post-ledger failure changed intent.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups')===$beforeGroups,'Post-ledger failure left ledger group.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM notifications')===$beforeNotifications,'Post-ledger failure left notifications.');
    mg_checkout_assert((string)mg_checkout_scalar($pdo,'SELECT status FROM receipts WHERE order_id=?',[$rollbackOrderId])==='pending','Post-ledger failure finalized receipt.');
    $summary['post_ledger_failure_rolled_back']=true;

    $fulfillmentDraft=mg_checkout_fixture_draft($pdo,$fixture,'fulfillment-rollback');
    $fulfillmentOrder=mg_checkout_create_order($pdo,$buyerId,$fulfillmentDraft['draft_public'],'fulfillment-rollback-order-'.$runId);
    $fulfillmentOrderId=(int)mg_checkout_scalar($pdo,'SELECT id FROM commerce_orders WHERE public_id=?',[$fulfillmentOrder['order']['order_id']]);
    $fulfillmentSession=mg_payment_create_checkout_session($pdo,$buyerId,$fulfillmentOrder['order']['order_id'],'fulfillment-rollback-session-'.$runId);
    $fulfillmentIntentId=(int)mg_checkout_scalar($pdo,'SELECT id FROM payment_intents WHERE public_id=?',[$fulfillmentSession['payment_intent_id']]);
    $beforeGroups=(int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups');
    $beforeNotifications=(int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM notifications');
    $beforePppm=(int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM pppm_items');
    $beforeEntitlements=(int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM entitlements');
    $beforeMicrogifts=(int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM microgift_instances');
    $beforeActions=(int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM microgift_inbox_items');
    $pdo->exec('SAVEPOINT checkout_post_fulfillment_failure');$forced=false;
    try{mg_finance_record_paid_order($pdo,$fulfillmentOrderId,$fulfillmentIntentId,'fulfillment-rollback-provider-'.$runId,$buyerId,static function(string $stage): void {if($stage==='after_fulfillment')throw new RuntimeException('Forced post-fulfillment failure.');});}catch(Throwable){$forced=true;$pdo->exec('ROLLBACK TO SAVEPOINT checkout_post_fulfillment_failure');}
    mg_checkout_assert($forced,'Forced post-fulfillment failure did not throw.');
    mg_checkout_assert((string)mg_checkout_scalar($pdo,'SELECT payment_status FROM commerce_orders WHERE id=?',[$fulfillmentOrderId])==='unpaid','Post-fulfillment failure left order paid.');
    mg_checkout_assert((string)mg_checkout_scalar($pdo,'SELECT fulfillment_status FROM commerce_orders WHERE id=?',[$fulfillmentOrderId])==='pending','Post-fulfillment failure left order fulfilled.');
    mg_checkout_assert((string)mg_checkout_scalar($pdo,'SELECT status FROM payment_intents WHERE id=?',[$fulfillmentIntentId])==='created','Post-fulfillment failure changed intent.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups')===$beforeGroups,'Post-fulfillment failure left ledger groups.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM notifications')===$beforeNotifications,'Post-fulfillment failure left notifications.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM pppm_items')===$beforePppm,'Post-fulfillment failure left PPPM items.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM entitlements')===$beforeEntitlements,'Post-fulfillment failure left entitlements.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM microgift_instances')===$beforeMicrogifts,'Post-fulfillment failure left Microgifts.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM microgift_inbox_items')===$beforeActions,'Post-fulfillment failure left Action Center rows.');
    mg_checkout_assert((string)mg_checkout_scalar($pdo,'SELECT status FROM receipts WHERE order_id=?',[$fulfillmentOrderId])==='pending','Post-fulfillment failure finalized receipt.');
    $summary['post_fulfillment_failure_rolled_back']=true;

    $pdo->rollBack();
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$buyerEmail,$merchantEmail])===0,'Checkout users remain.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM commerce_orders WHERE idempotency_key LIKE ?',['%'.$runId])===0,'Checkout orders remain.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,'SELECT COUNT(*) FROM catalog_products WHERE public_id=?',[$catalog['product_public']])===0,'Checkout product remains.');
    mg_checkout_assert((int)mg_checkout_scalar($pdo,"SELECT COUNT(*) FROM microgift_instances WHERE idempotency_key LIKE ?",['commerce-order-item:%'.$runId.'%'])===0,'Checkout Microgifts remain.');
    $summary['fixtures_clean']=true;
    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
