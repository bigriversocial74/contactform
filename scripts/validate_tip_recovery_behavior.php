<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/tips/_payment_events.php';
require_once dirname(__DIR__).'/api/tips/_recovery.php';
require_once dirname(__DIR__).'/api/tips/_notifications.php';
require_once dirname(__DIR__).'/tests/integration/TipRecoveryBehaviorFixture.php';

$pdo=mg_db();$runId='tip_recovery_'.bin2hex(random_bytes(5));$prefix='tip-recovery:'.$runId;
$countState=static fn(PDO $pdo):array=>[
 'tips'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tips'),
 'recoveries'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tip_payment_recoveries'),
 'refunds'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM payment_refunds'),
 'disputes'=>(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM payment_disputes'),
 'holds'=>(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM payout_holds WHERE source_type='tip_dispute'"),
 'groups'=>(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM ledger_transaction_groups WHERE source_type IN ('tip_recovery','tip_dispute')"),
];
$baseline=$countState($pdo);
$summary=['suite'=>'stage12d_tip_recovery_behavior','refund_recovered_once'=>false,'refund_replay_safe'=>false,'dispute_hold_blocks_cashout'=>false,'dispute_won_releases_hold'=>false,'dispute_lost_recovers_funds'=>false,'chargeback_recovered_once'=>false,'provider_contract_rollback'=>false,'out_of_order_rejected'=>false,'notification_idempotency'=>false,'downstream_rollback'=>false,'fixtures_clean'=>false];
$pdo->beginTransaction();
try{
 $sender=mg_it_user($pdo,$runId.'-sender@example.test','Recovery Sender');$recipient=mg_it_user($pdo,$runId.'-recipient@example.test','Recovery Recipient');
 $wallet=mg_tip_it_wallet($pdo,'user',$recipient,'USD');$available=$wallet['available_account_id'];$held=mg_wallet_account_id($pdo,$wallet['id'],'held','USD');
 $feeAccount=mg_ledger_platform_account($pdo,'tip_fee_revenue','revenue','credit','USD');$processor=mg_ledger_platform_account($pdo,'processor_clearing','asset','debit','USD');
 $settle=static function(string $suffix,int $amount)use($pdo,$sender,$recipient,$prefix):array{
  $tip=mg_tip_create($pdo,$sender,['target_type'=>'profile','target_reference'=>(string)$recipient,'amount_cents'=>$amount,'currency'=>'USD','funding_type'=>'stripe','idempotency_key'=>$prefix.':'.$suffix]);
  return mg_tip_process_payment_event_result($pdo,(string)$tip['provider_key'],mg_tip_payment_it_event($prefix.':settle:'.$suffix,'payment_intent.succeeded',$tip));
 };

 $tip=$settle('refund',2000);$fee=mg_tip_fee_snapshot(2000);$a=mg_tip_it_account_balance($pdo,$available);$f=mg_tip_it_account_balance($pdo,$feeAccount);$p=mg_tip_it_account_balance($pdo,$processor);
 $event=mg_tip_recovery_it_event($prefix.':refund','refund.succeeded',$tip,'re_'.$runId,2000);$result=mg_tip_process_recovery_event($pdo,(string)$tip['provider_key'],$event);
 $alerts1=mg_tip_notify_recovery($pdo,$result,$result);$alerts2=mg_tip_notify_recovery($pdo,$result,$result);
 mg_it_assert((string)$result['status']==='refunded','Refund status failed.');
 mg_it_assert(mg_tip_it_account_balance($pdo,$available)===$a-$fee['net_cents']&&mg_tip_it_account_balance($pdo,$feeAccount)===$f-$fee['fee_cents']&&mg_tip_it_account_balance($pdo,$processor)===$p-2000,'Refund balances failed.');
 $counts=mg_tip_recovery_it_counts($pdo,(string)$tip['public_id']);mg_it_assert($counts['recoveries']===1&&$counts['refunds']===1&&$counts['transactions']===1&&$counts['recovery_groups']===1,'Refund records failed.');
 mg_it_assert($alerts1===$alerts2,'Recovery alerts are not idempotent.');$summary['refund_recovered_once']=true;$summary['notification_idempotency']=true;
 $replay=mg_tip_process_recovery_event($pdo,(string)$tip['provider_key'],$event);mg_it_assert(!empty($replay['duplicate'])&&$counts===mg_tip_recovery_it_counts($pdo,(string)$tip['public_id']),'Refund replay failed.');$summary['refund_replay_safe']=true;

 $wonTip=$settle('won',3000);$wonFee=mg_tip_fee_snapshot(3000);$ref='dp_won_'.$runId;$a=mg_tip_it_account_balance($pdo,$available);$h=mg_tip_it_account_balance($pdo,$held);
 $opened=mg_tip_process_recovery_event($pdo,(string)$wonTip['provider_key'],mg_tip_recovery_it_event($prefix.':open-won','charge.dispute.created',$wonTip,$ref,3000));
 mg_it_assert((string)$opened['status']==='disputed'&&mg_tip_it_account_balance($pdo,$available)===$a-$wonFee['net_cents']&&mg_tip_it_account_balance($pdo,$held)===$h+$wonFee['net_cents'],'Dispute hold failed.');
 mg_it_assert((string)mg_it_scalar($pdo,'SELECT status FROM payout_holds WHERE source_reference=?',[$ref])==='active','Active dispute hold missing.');$summary['dispute_hold_blocks_cashout']=true;
 $won=mg_tip_process_recovery_event($pdo,(string)$wonTip['provider_key'],mg_tip_recovery_it_event($prefix.':close-won','charge.dispute.closed',$wonTip,$ref,3000,['status'=>'won']));
 mg_it_assert((string)$won['status']==='posted'&&mg_tip_it_account_balance($pdo,$available)===$a&&mg_tip_it_account_balance($pdo,$held)===$h,'Won dispute release failed.');$summary['dispute_won_releases_hold']=true;

 $lostTip=$settle('lost',4000);$lostFee=mg_tip_fee_snapshot(4000);$ref='dp_lost_'.$runId;$a=mg_tip_it_account_balance($pdo,$available);$h=mg_tip_it_account_balance($pdo,$held);$f=mg_tip_it_account_balance($pdo,$feeAccount);$p=mg_tip_it_account_balance($pdo,$processor);
 mg_tip_process_recovery_event($pdo,(string)$lostTip['provider_key'],mg_tip_recovery_it_event($prefix.':open-lost','charge.dispute.created',$lostTip,$ref,4000));
 $lost=mg_tip_process_recovery_event($pdo,(string)$lostTip['provider_key'],mg_tip_recovery_it_event($prefix.':close-lost','charge.dispute.closed',$lostTip,$ref,4000,['status'=>'lost']));
 mg_it_assert((string)$lost['status']==='disputed'&&mg_tip_it_account_balance($pdo,$available)===$a-$lostFee['net_cents']&&mg_tip_it_account_balance($pdo,$held)===$h&&mg_tip_it_account_balance($pdo,$feeAccount)===$f-$lostFee['fee_cents']&&mg_tip_it_account_balance($pdo,$processor)===$p-4000,'Lost dispute recovery failed.');
 $lostCounts=mg_tip_recovery_it_counts($pdo,(string)$lostTip['public_id']);mg_it_assert($lostCounts['disputes']===1&&$lostCounts['holds']===1&&$lostCounts['transactions']===1&&$lostCounts['recovery_groups']===1,'Lost dispute records failed.');$summary['dispute_lost_recovers_funds']=true;

 $chargeTip=$settle('chargeback',1500);$charge=mg_tip_process_recovery_event($pdo,(string)$chargeTip['provider_key'],mg_tip_recovery_it_event($prefix.':chargeback','tip.chargeback',$chargeTip,'cb_'.$runId,1500));
 $chargeCounts=mg_tip_recovery_it_counts($pdo,(string)$chargeTip['public_id']);mg_it_assert($chargeCounts['recoveries']===1&&$chargeCounts['transactions']===1&&$chargeCounts['recovery_groups']===1,'Chargeback records failed.');
 mg_it_assert(!empty(mg_tip_process_recovery_event($pdo,(string)$chargeTip['provider_key'],mg_tip_recovery_it_event($prefix.':chargeback','tip.chargeback',$chargeTip,'cb_'.$runId,1500))['duplicate']),'Chargeback replay failed.');$summary['chargeback_recovered_once']=true;

 $invalid=$settle('invalid',1200);$before=$countState($pdo);$pdo->exec('SAVEPOINT invalid_provider');
 mg_tip_it_expect_throw(static fn()=>mg_tip_process_recovery_event($pdo,'other',mg_tip_recovery_it_event($prefix.':invalid','refund.succeeded',$invalid,'re_invalid_'.$runId,1200)),'Tip recovery provider does not match.');
 $pdo->exec('ROLLBACK TO SAVEPOINT invalid_provider');$pdo->exec('RELEASE SAVEPOINT invalid_provider');mg_it_assert($before===$countState($pdo),'Invalid provider changed state.');$summary['provider_contract_rollback']=true;

 $out=$settle('out',1300);$before=$countState($pdo);$pdo->exec('SAVEPOINT out_of_order');
 mg_tip_it_expect_throw(static fn()=>mg_tip_process_recovery_event($pdo,(string)$out['provider_key'],mg_tip_recovery_it_event($prefix.':out','tip.dispute_won',$out,'dp_missing_'.$runId,1300)),'Tip dispute record not found.');
 $pdo->exec('ROLLBACK TO SAVEPOINT out_of_order');$pdo->exec('RELEASE SAVEPOINT out_of_order');mg_it_assert($before===$countState($pdo),'Out-of-order event changed state.');$summary['out_of_order_rejected']=true;

 $rollback=$settle('rollback',1700);$before=$countState($pdo);$a=mg_tip_it_account_balance($pdo,$available);$f=mg_tip_it_account_balance($pdo,$feeAccount);$p=mg_tip_it_account_balance($pdo,$processor);$pdo->exec('SAVEPOINT downstream');
 try{$r=mg_tip_process_recovery_event($pdo,(string)$rollback['provider_key'],mg_tip_recovery_it_event($prefix.':rollback','refund.succeeded',$rollback,'re_rollback_'.$runId,1700));mg_tip_notify_recovery($pdo,$r,$r);throw new RuntimeException('simulated');}catch(RuntimeException $e){mg_it_assert($e->getMessage()==='simulated','Unexpected rollback error.');$pdo->exec('ROLLBACK TO SAVEPOINT downstream');}
 $pdo->exec('RELEASE SAVEPOINT downstream');mg_it_assert($before===$countState($pdo)&&mg_tip_it_account_balance($pdo,$available)===$a&&mg_tip_it_account_balance($pdo,$feeAccount)===$f&&mg_tip_it_account_balance($pdo,$processor)===$p,'Downstream rollback failed.');$summary['downstream_rollback']=true;

 $pdo->rollBack();mg_it_assert($baseline===$countState($pdo),'Stage 12D fixtures remain.');$summary['fixtures_clean']=true;
 fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$summary['error']=$e->getMessage();fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);throw $e;}
