<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/payments/_refund.php';
require_once dirname(__DIR__).'/api/payments/_disputes.php';
require_once dirname(__DIR__).'/tests/integration/DisputeBehaviorFixture.php';

function mg_reversal_assert(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
function mg_reversal_scalar(PDO $pdo,string $sql,array $params=[]): mixed {$stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();}
function mg_reversal_order_instance_ids(PDO $pdo,int $orderId): array {$stmt=$pdo->prepare("SELECT mi.id FROM microgift_instances mi INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND mi.source_type='commerce_order_item' ORDER BY mi.id");$stmt->execute([$orderId]);return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));}
function mg_reversal_order_status_count(PDO $pdo,int $orderId,string $status): int {return (int)mg_reversal_scalar($pdo,"SELECT COUNT(*) FROM microgift_instances mi INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND mi.status=?",[$orderId,$status]);}
function mg_reversal_order_pppm_status_count(PDO $pdo,int $orderId,string $status): int {return (int)mg_reversal_scalar($pdo,"SELECT COUNT(*) FROM pppm_items p INNER JOIN microgift_instances mi ON mi.pppm_item_id=p.id INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND p.status=?",[$orderId,$status]);}
function mg_reversal_action_state_count(PDO $pdo,int $orderId,string $state): int {return (int)mg_reversal_scalar($pdo,"SELECT COUNT(*) FROM microgift_inbox_items ac INNER JOIN microgift_instances mi ON mi.id=ac.instance_id INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND ac.state=?",[$orderId,$state]);}
function mg_reversal_review_count(PDO $pdo,int $orderId,string $reviewType,string $detailType): int {return (int)mg_reversal_scalar($pdo,"SELECT COUNT(*) FROM entitlement_review_items WHERE commerce_order_id=? AND review_type=? AND status='open' AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.microgift_review_type'))=?",[$orderId,$reviewType,$detailType]);}
function mg_reversal_dispute_order(PDO $pdo,array $order): array {return mg_dispute_load_order($pdo,(string)$order['order_public'],(string)$order['intent_public']);}
function mg_reversal_seed_claim_credentials(PDO $pdo,int $orderId,int $actorUserId): int
{
    $stmt=$pdo->prepare("SELECT mi.id,mi.expires_at FROM microgift_instances mi INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND mi.source_type='commerce_order_item' ORDER BY mi.id");
    $stmt->execute([$orderId]);$count=0;
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $instance){mg_microgift_create_credential($pdo,(int)$instance['id'],'claim',$actorUserId,$instance['expires_at']?:null);$count++;}
    return $count;
}

$pdo=mg_db();$runId='reversal_'.bin2hex(random_bytes(8));
$summary=[
    'suite'=>'payment_reversal_microgift_behavior','run_id'=>$runId,
    'full_refund_revoked'=>false,'refund_credentials_revoked'=>false,'refund_action_center_reconciled'=>false,
    'partial_refund_reviewed'=>false,'dispute_open_suspended'=>false,'dispute_won_restored'=>false,
    'dispute_lost_revoked'=>false,'redeemed_recovery_reviewed'=>false,'replay_safe'=>false,
    'rollback_safe'=>false,'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $buyerEmail=$runId.'-buyer@example.test';$merchantEmail=$runId.'-merchant@example.test';
    $buyerId=mg_it_user($pdo,$buyerEmail,'Reversal Buyer');$merchantId=mg_it_user($pdo,$merchantEmail,'Reversal Merchant');
    $catalog=mg_dispute_fixture_catalog($pdo,$merchantId,$runId);
    $fixture=['run_id'=>$runId,'buyer_id'=>$buyerId,'merchant_id'=>$merchantId,'buyer_email'=>$buyerEmail]+$catalog;

    $refundOrder=mg_dispute_fixture_order($pdo,$fixture,'full-refund');
    mg_reversal_assert(count(mg_reversal_order_instance_ids($pdo,$refundOrder['order_id']))===2,'Full-refund fixture did not issue two Microgifts.');
    mg_reversal_assert(mg_reversal_seed_claim_credentials($pdo,$refundOrder['order_id'],$merchantId)===2,'Full-refund fixture did not create claim credentials.');
    $refund=mg_finance_refund_order($pdo,$merchantId,$merchantId,['order_id'=>$refundOrder['order_public'],'amount_cents'=>2500,'reason'=>'requested_by_customer','idempotency_key'=>'reversal:refund:'.$runId]);
    mg_reversal_assert(($refund['microgift_reconciliation']['revoked']??0)===2,'Full refund did not revoke both Microgifts.');
    mg_reversal_assert(mg_reversal_order_status_count($pdo,$refundOrder['order_id'],'revoked')===2,'Full refund left usable Microgifts.');
    mg_reversal_assert(mg_reversal_order_pppm_status_count($pdo,$refundOrder['order_id'],'refunded')===2,'Full refund left PPPM items available.');
    $summary['full_refund_revoked']=true;
    mg_reversal_assert((int)mg_reversal_scalar($pdo,"SELECT COUNT(*) FROM microgift_credentials c INNER JOIN microgift_instances mi ON mi.id=c.instance_id INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND c.status='revoked'",[$refundOrder['order_id']])===2,'Full refund did not revoke active claim credentials.');
    $summary['refund_credentials_revoked']=true;
    mg_reversal_assert(mg_reversal_action_state_count($pdo,$refundOrder['order_id'],'revoked')===4,'Full refund did not reconcile buyer and merchant Action Center rows.');
    $summary['refund_action_center_reconciled']=true;
    $refundReplay=mg_finance_refund_order($pdo,$merchantId,$merchantId,['order_id'=>$refundOrder['order_public'],'amount_cents'=>2500,'reason'=>'requested_by_customer','idempotency_key'=>'reversal:refund:'.$runId]);
    mg_reversal_assert($refundReplay['duplicate']===true,'Full refund replay was not idempotent.');
    mg_reversal_assert((int)mg_reversal_scalar($pdo,"SELECT COUNT(*) FROM microgift_lifecycle_actions a INNER JOIN microgift_instances mi ON mi.id=a.instance_id INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND a.action_type='refund'",[$refundOrder['order_id']])===2,'Refund replay duplicated lifecycle actions.');
    $summary['replay_safe']=true;

    $partialOrder=mg_dispute_fixture_order($pdo,$fixture,'partial-refund');
    mg_finance_refund_order($pdo,$merchantId,$merchantId,['order_id'=>$partialOrder['order_public'],'amount_cents'=>500,'reason'=>'requested_by_customer','idempotency_key'=>'reversal:partial:'.$runId]);
    mg_reversal_assert(mg_reversal_order_status_count($pdo,$partialOrder['order_id'],'issued')===2,'Partial refund changed Microgift units automatically.');
    mg_reversal_assert(mg_reversal_order_pppm_status_count($pdo,$partialOrder['order_id'],'available')===2,'Partial refund changed PPPM units automatically.');
    mg_reversal_assert(mg_reversal_review_count($pdo,$partialOrder['order_id'],'partial_refund','microgift_partial_refund')===1,'Partial refund Microgift review was not created.');
    $summary['partial_refund_reviewed']=true;

    $wonOrder=mg_dispute_fixture_order($pdo,$fixture,'dispute-won');mg_reversal_seed_claim_credentials($pdo,$wonOrder['order_id'],$merchantId);
    $wonLoaded=mg_reversal_dispute_order($pdo,$wonOrder);$wonReference='reversal-dispute-won-'.$runId;
    $opened=mg_dispute_apply_event($pdo,$wonLoaded,['dispute_id'=>$wonReference,'amount_cents'=>2500,'reason'=>'fraudulent'],'dispute.opened');
    mg_reversal_assert(($opened['microgift_reconciliation']['suspended']??0)===2,'Full dispute did not suspend both Microgifts.');
    mg_reversal_assert(mg_reversal_order_status_count($pdo,$wonOrder['order_id'],'revoked')===2,'Dispute-open Microgifts were not suspended.');
    mg_reversal_assert(mg_reversal_order_pppm_status_count($pdo,$wonOrder['order_id'],'voided')===2,'Dispute-open PPPM items were not suspended.');
    mg_reversal_assert((int)mg_reversal_scalar($pdo,"SELECT COUNT(*) FROM microgift_credentials c INNER JOIN microgift_instances mi ON mi.id=c.instance_id INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND c.status='active'",[$wonOrder['order_id']])===2,'Temporary dispute suspension revoked reusable credentials.');
    $summary['dispute_open_suspended']=true;
    $won=mg_dispute_apply_event($pdo,$wonLoaded,['dispute_id'=>$wonReference,'amount_cents'=>2500,'reason'=>'fraudulent'],'dispute.won');
    mg_reversal_assert(($won['microgift_reconciliation']['restored']??0)===2,'Won dispute did not restore both Microgifts.');
    mg_reversal_assert(mg_reversal_order_status_count($pdo,$wonOrder['order_id'],'issued')===2,'Won dispute did not restore Microgift states.');
    mg_reversal_assert(mg_reversal_order_pppm_status_count($pdo,$wonOrder['order_id'],'available')===2,'Won dispute did not restore PPPM states.');
    mg_reversal_assert(mg_reversal_action_state_count($pdo,$wonOrder['order_id'],'claimable')===4,'Won dispute did not restore Action Center states.');
    mg_reversal_assert((int)mg_reversal_scalar($pdo,"SELECT COUNT(*) FROM microgift_credentials c INNER JOIN microgift_instances mi ON mi.id=c.instance_id INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND c.status='active'",[$wonOrder['order_id']])===2,'Won dispute did not preserve claim credentials.');
    $summary['dispute_won_restored']=true;

    $lostOrder=mg_dispute_fixture_order($pdo,$fixture,'dispute-lost');mg_reversal_seed_claim_credentials($pdo,$lostOrder['order_id'],$merchantId);
    $lostLoaded=mg_reversal_dispute_order($pdo,$lostOrder);$lostReference='reversal-dispute-lost-'.$runId;
    mg_dispute_apply_event($pdo,$lostLoaded,['dispute_id'=>$lostReference,'amount_cents'=>2500,'reason'=>'fraudulent'],'dispute.opened');
    $lost=mg_dispute_apply_event($pdo,$lostLoaded,['dispute_id'=>$lostReference,'amount_cents'=>2500,'fee_cents'=>150,'reason'=>'fraudulent'],'dispute.lost');
    mg_reversal_assert(($lost['microgift_reconciliation']['revoked']??0)===2,'Lost dispute did not permanently revoke both Microgifts.');
    mg_reversal_assert(mg_reversal_order_status_count($pdo,$lostOrder['order_id'],'revoked')===2,'Lost dispute left Microgifts usable.');
    mg_reversal_assert(mg_reversal_order_pppm_status_count($pdo,$lostOrder['order_id'],'refunded')===2,'Lost dispute left PPPM items usable.');
    mg_reversal_assert((int)mg_reversal_scalar($pdo,"SELECT COUNT(*) FROM microgift_credentials c INNER JOIN microgift_instances mi ON mi.id=c.instance_id INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND c.status='revoked'",[$lostOrder['order_id']])===2,'Lost dispute did not revoke credentials.');
    $summary['dispute_lost_revoked']=true;

    $redeemedOrder=mg_dispute_fixture_order($pdo,$fixture,'redeemed-refund');$redeemedIds=mg_reversal_order_instance_ids($pdo,$redeemedOrder['order_id']);
    $pdo->prepare("UPDATE microgift_instances SET status='redeemed',redeemed_at=NOW() WHERE id=?")->execute([$redeemedIds[0]]);
    $pdo->prepare("UPDATE pppm_items SET status='redeemed',redeemed_at=NOW() WHERE id=(SELECT pppm_item_id FROM microgift_instances WHERE id=?)")->execute([$redeemedIds[0]]);
    mg_finance_refund_order($pdo,$merchantId,$merchantId,['order_id'=>$redeemedOrder['order_public'],'amount_cents'=>2500,'reason'=>'merchant_error','idempotency_key'=>'reversal:redeemed:'.$runId]);
    mg_reversal_assert((string)mg_reversal_scalar($pdo,'SELECT status FROM microgift_instances WHERE id=?',[$redeemedIds[0]])==='redeemed','Refund rewrote redeemed Microgift history.');
    mg_reversal_assert(mg_reversal_review_count($pdo,$redeemedOrder['order_id'],'policy_exception','microgift_recovery')===1,'Redeemed Microgift recovery review was not created.');
    mg_reversal_assert(mg_reversal_order_status_count($pdo,$redeemedOrder['order_id'],'revoked')===1,'Unredeemed unit was not revoked beside redeemed recovery exception.');
    $summary['redeemed_recovery_reviewed']=true;

    $rollbackOrder=mg_dispute_fixture_order($pdo,$fixture,'rollback');mg_reversal_seed_claim_credentials($pdo,$rollbackOrder['order_id'],$merchantId);
    $beforeRefunds=(int)mg_reversal_scalar($pdo,'SELECT COUNT(*) FROM payment_refunds');
    $pdo->exec('SAVEPOINT reversal_failure');$failed=false;
    try{mg_finance_refund_order($pdo,$merchantId,$merchantId,['order_id'=>$rollbackOrder['order_public'],'amount_cents'=>2500,'reason'=>'merchant_error','idempotency_key'=>'reversal:rollback:'.$runId],static function(string $stage): void {if($stage==='after_microgift_reconciliation')throw new RuntimeException('Forced reversal failure.');});}
    catch(Throwable){$failed=true;$pdo->exec('ROLLBACK TO SAVEPOINT reversal_failure');}
    mg_reversal_assert($failed,'Forced post-reconciliation failure did not throw.');
    mg_reversal_assert((int)mg_reversal_scalar($pdo,'SELECT COUNT(*) FROM payment_refunds')===$beforeRefunds,'Forced failure left refund state.');
    mg_reversal_assert((string)mg_reversal_scalar($pdo,'SELECT payment_status FROM commerce_orders WHERE id=?',[$rollbackOrder['order_id']])==='paid','Forced failure changed order state.');
    mg_reversal_assert(mg_reversal_order_status_count($pdo,$rollbackOrder['order_id'],'issued')===2,'Forced failure left Microgift state.');
    mg_reversal_assert(mg_reversal_order_pppm_status_count($pdo,$rollbackOrder['order_id'],'available')===2,'Forced failure left PPPM state.');
    mg_reversal_assert(mg_reversal_action_state_count($pdo,$rollbackOrder['order_id'],'claimable')===4,'Forced failure left Action Center state.');
    mg_reversal_assert((int)mg_reversal_scalar($pdo,"SELECT COUNT(*) FROM microgift_credentials c INNER JOIN microgift_instances mi ON mi.id=c.instance_id INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND c.status='active'",[$rollbackOrder['order_id']])===2,'Forced failure left credential state.');
    $summary['rollback_safe']=true;

    $pdo->rollBack();
    mg_reversal_assert((int)mg_reversal_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$buyerEmail,$merchantEmail])===0,'Reversal users remain.');
    mg_reversal_assert((int)mg_reversal_scalar($pdo,"SELECT COUNT(*) FROM commerce_orders WHERE idempotency_key LIKE ?",['%'.$runId.'%'])===0,'Reversal orders remain.');
    $summary['fixtures_clean']=true;
    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();$summary['error']=$error->getMessage();fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);throw $error;}
