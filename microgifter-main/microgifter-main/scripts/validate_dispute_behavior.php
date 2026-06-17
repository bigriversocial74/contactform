<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/payments/_disputes.php';
require_once dirname(__DIR__).'/tests/integration/DisputeBehaviorFixture.php';
require_once dirname(__DIR__).'/tests/integration/DisputeBehaviorAssertions.php';
require_once dirname(__DIR__).'/tests/integration/DisputeWonScenario.php';
require_once dirname(__DIR__).'/tests/integration/DisputeLostPartialScenario.php';

$pdo=mg_db();$runId='dispute_'.bin2hex(random_bytes(8));
$summary=['suite'=>'payment_dispute_chargeback_behavior','run_id'=>$runId,'open_reserved'=>false,'open_replay'=>false,'conflicting_replay_rejected'=>false,'full_entitlements_suspended'=>false,'won_restored'=>false,'lost_finalized'=>false,'fee_balanced'=>false,'partial_review'=>false,'pppm_preserved'=>false,'receipts_consistent'=>false,'notifications_once'=>false,'forced_failure_rolled_back'=>false,'fixtures_clean'=>false];

$pdo->beginTransaction();
try{
    $buyerEmail=$runId.'-buyer@example.test';$merchantEmail=$runId.'-merchant@example.test';
    $buyerId=mg_it_user($pdo,$buyerEmail,'Dispute Buyer');$merchantId=mg_it_user($pdo,$merchantEmail,'Dispute Merchant');
    $catalog=mg_dispute_fixture_catalog($pdo,$merchantId,$runId);
    $fixture=['run_id'=>$runId,'buyer_id'=>$buyerId,'merchant_id'=>$merchantId,'buyer_email'=>$buyerEmail]+$catalog;
    $wallet=mg_wallet_resolve($pdo,'merchant',$merchantId,'USD');
    $alertsBefore=(int)mg_dispute_behavior_scalar($pdo,'SELECT COUNT(*) FROM operational_alerts WHERE user_id=?',[$merchantId]);

    $won=mg_dispute_behavior_run_won($pdo,$fixture,$wallet,$runId);
    $summary['open_reserved']=$summary['open_replay']=$summary['conflicting_replay_rejected']=true;
    $summary['full_entitlements_suspended']=$summary['won_restored']=$summary['pppm_preserved']=true;

    $lost=mg_dispute_behavior_run_lost($pdo,$fixture,$runId);
    $summary['lost_finalized']=$summary['fee_balanced']=true;
    $partial=mg_dispute_behavior_run_partial($pdo,$fixture,$runId);
    $summary['partial_review']=true;

    mg_dispute_behavior_assert((string)mg_dispute_behavior_scalar($pdo,'SELECT status FROM receipts WHERE id=?',[$won['order']['receipt_id']])==='finalized','Won dispute corrupted receipt.');
    mg_dispute_behavior_assert((string)mg_dispute_behavior_scalar($pdo,'SELECT status FROM receipts WHERE id=?',[$lost['order']['receipt_id']])==='finalized','Lost dispute corrupted receipt.');
    $summary['receipts_consistent']=true;
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,'SELECT COUNT(*) FROM operational_alerts WHERE user_id=?',[$merchantId])===$alertsBefore+5,'Dispute alerts were not created exactly once.');
    $summary['notifications_once']=true;

    $failureOrder=mg_dispute_fixture_order($pdo,$fixture,'failure');
    $beforeDisputes=(int)mg_dispute_behavior_scalar($pdo,'SELECT COUNT(*) FROM payment_disputes');
    $beforeGroups=(int)mg_dispute_behavior_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups');
    $pdo->exec('SAVEPOINT dispute_failure');$failed=false;
    $event=mg_dispute_fixture_event('evt-failure-'.$runId,'dispute.opened',$failureOrder,'dp-failure-'.$runId,2500);
    try{
        mg_dispute_behavior_process($pdo,$event,static function(string $stage): void {if($stage==='after_ledger')throw new RuntimeException('Forced dispute failure.');});
    }catch(Throwable){$failed=true;$pdo->exec('ROLLBACK TO SAVEPOINT dispute_failure');}
    mg_dispute_behavior_assert($failed,'Forced dispute failure did not throw.');
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,'SELECT COUNT(*) FROM payment_disputes')===$beforeDisputes,'Forced failure left dispute state.');
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups')===$beforeGroups,'Forced failure left ledger state.');
    mg_dispute_behavior_assert((string)mg_dispute_behavior_scalar($pdo,'SELECT payment_status FROM commerce_orders WHERE id=?',[$failureOrder['order_id']])==='paid','Forced failure changed order state.');
    $summary['forced_failure_rolled_back']=true;

    $pdo->rollBack();
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$buyerEmail,$merchantEmail])===0,'Dispute users remain.');
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,'SELECT COUNT(*) FROM payment_disputes WHERE provider_dispute_reference LIKE ?',['%'.$runId])===0,'Dispute fixtures remain.');
    $summary['fixtures_clean']=true;
    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
