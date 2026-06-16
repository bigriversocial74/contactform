<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/finance/_cashouts.php';
require_once dirname(__DIR__).'/tests/integration/MicrogiftBehaviorFixture.php';

function mg_cashout_behavior_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_cashout_behavior_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
}

function mg_cashout_behavior_fund(PDO $pdo,array $wallet,int $amountCents,int $actorUserId,string $runId): void
{
    $platform=mg_ledger_platform_account($pdo,'behavior_cashout_funding','asset','debit',(string)$wallet['currency']);
    $available=mg_wallet_account_id($pdo,(int)$wallet['id'],'available',(string)$wallet['currency']);
    mg_ledger_post($pdo,[
        'transaction_type'=>'behavior_funding','source_type'=>'behavior_fixture','source_reference'=>$runId,
        'idempotency_key'=>'behavior:cashout:fund:'.$runId,'currency'=>$wallet['currency'],'description'=>'Cashout behavior funding',
    ],[
        ['ledger_account_id'=>$platform,'entry_type'=>'debit','amount_cents'=>$amountCents],
        ['ledger_account_id'=>$available,'entry_type'=>'credit','amount_cents'=>$amountCents],
    ],$actorUserId);
}

function mg_cashout_behavior_group_balanced(PDO $pdo,int $groupId): bool
{
    $stmt=$pdo->prepare('SELECT entry_type,SUM(amount_cents) total FROM ledger_entries WHERE transaction_group_id=? GROUP BY entry_type');
    $stmt->execute([$groupId]);$sides=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$sides[(string)$row['entry_type']]=(int)$row['total'];
    return ($sides['debit']??0)>0&&($sides['debit']??0)===($sides['credit']??-1);
}

function mg_cashout_behavior_event(string $eventId,string $type,string $payoutId,array $extra=[]): array
{
    return ['id'=>$eventId,'type'=>$type,'data'=>['payout_id'=>$payoutId,'provider_reference'=>'provider-'.$eventId]+$extra];
}

$pdo=mg_db();$runId='cashout_'.bin2hex(random_bytes(8));
$summary=[
    'suite'=>'cashout_payout_reconciliation_behavior','run_id'=>$runId,
    'wallet_funded'=>false,'hold_blocks_cashout'=>false,'hold_release_restores_eligibility'=>false,
    'cashout_reserved'=>false,'cashout_replay'=>false,'cashout_conflict_rejected'=>false,'reservation_balanced'=>false,
    'payout_created_once'=>false,'paid_webhook_settled'=>false,'webhook_replay'=>false,'webhook_conflict_rejected'=>false,
    'terminal_conflict_rejected'=>false,'failed_payout_restored_balance'=>false,'notifications_once'=>false,
    'forced_failure_rolled_back'=>false,'ledger_consistent'=>false,'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $merchantEmail=$runId.'-merchant@example.test';$adminEmail=$runId.'-admin@example.test';
    $merchantId=mg_it_user($pdo,$merchantEmail,'Cashout Merchant');$adminId=mg_it_user($pdo,$adminEmail,'Cashout Admin');
    $wallet=mg_wallet_resolve($pdo,'merchant',$merchantId,'USD');
    mg_cashout_behavior_fund($pdo,$wallet,10000,$adminId,$runId);
    $balances=mg_wallet_balances($pdo,(int)$wallet['id']);
    mg_cashout_behavior_assert((int)$balances['available_cents']===10000,'Wallet funding failed.');
    $summary['wallet_funded']=true;

    $hold=mg_payout_hold_create($pdo,$wallet,1000,'Behavior hold',$adminId);
    $blocked=false;
    try{mg_cashout_request($pdo,$wallet,$merchantId,500,'hold-block-'.$runId);}catch(MgCashoutWorkflowException $e){$blocked=$e->getMessage()==='Wallet has an active payout hold.';}
    mg_cashout_behavior_assert($blocked,'Active hold did not block cashout.');
    $summary['hold_blocks_cashout']=true;
    $released=mg_payout_hold_release($pdo,$hold['hold_id'],$adminId);
    $releaseReplay=mg_payout_hold_release($pdo,$hold['hold_id'],$adminId);
    mg_cashout_behavior_assert($released['duplicate']===false&&$releaseReplay['duplicate']===true,'Hold release was not idempotent.');
    $balances=mg_wallet_balances($pdo,(int)$wallet['id']);
    mg_cashout_behavior_assert((int)$balances['available_cents']===10000&&(int)$balances['held_cents']===0,'Hold release did not restore balance.');
    $summary['hold_release_restores_eligibility']=true;

    $cashout1=mg_cashout_request($pdo,$wallet,$merchantId,3000,'paid-'.$runId);
    mg_cashout_behavior_assert((string)$cashout1['status']==='requested'&&!$cashout1['duplicate'],'Cashout request failed.');
    $balances=mg_wallet_balances($pdo,(int)$wallet['id']);
    mg_cashout_behavior_assert((int)$balances['available_cents']===7000&&(int)$balances['cashout_pending_cents']===3000,'Cashout reservation balances are wrong.');
    $summary['cashout_reserved']=true;
    $replay=mg_cashout_request($pdo,$wallet,$merchantId,3000,'paid-'.$runId);
    mg_cashout_behavior_assert($replay['duplicate']===true,'Exact cashout replay was not idempotent.');
    mg_cashout_behavior_assert((int)mg_cashout_behavior_scalar($pdo,'SELECT COUNT(*) FROM cashout_requests WHERE wallet_id=? AND idempotency_key=?',[(int)$wallet['id'],'paid-'.$runId])===1,'Cashout replay duplicated request.');
    $summary['cashout_replay']=true;
    $conflict=false;
    try{mg_cashout_request($pdo,$wallet,$merchantId,3100,'paid-'.$runId);}catch(MgCashoutWorkflowException $e){$conflict=$e->httpStatus===409;}
    mg_cashout_behavior_assert($conflict,'Conflicting cashout replay was accepted.');
    $summary['cashout_conflict_rejected']=true;
    mg_cashout_behavior_assert(mg_cashout_behavior_group_balanced($pdo,(int)$cashout1['reservation_group_id']),'Cashout reservation group is not balanced.');
    $summary['reservation_balanced']=true;

    $approved1=mg_cashout_approve($pdo,$cashout1,$adminId);
    $approvedReplay=mg_cashout_approve($pdo,$cashout1,$adminId);
    mg_cashout_behavior_assert(!$approved1['duplicate']&&$approvedReplay['duplicate'],'Payout approval replay is not idempotent.');
    mg_cashout_behavior_assert((int)mg_cashout_behavior_scalar($pdo,'SELECT COUNT(*) FROM cashout_payout_links WHERE cashout_request_id=?',[(int)$cashout1['id']])===1,'Cashout linked to multiple payouts.');
    $summary['payout_created_once']=true;

    $paidEvent=mg_cashout_behavior_event('evt-paid-'.$runId,'payout.paid',$approved1['payout_id']);
    $paidPayload=json_encode($paidEvent,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $paidResult=mg_payout_process_event($pdo,'sandbox',$paidEvent,$paidPayload);
    mg_cashout_behavior_assert(!$paidResult['duplicate']&&($paidResult['result']['status']??null)==='paid','Paid webhook did not settle payout.');
    $balances=mg_wallet_balances($pdo,(int)$wallet['id']);
    mg_cashout_behavior_assert((int)$balances['available_cents']===7000&&(int)$balances['cashout_pending_cents']===0&&(int)$balances['paid_cents']===3000,'Paid payout balances are wrong.');
    $summary['paid_webhook_settled']=true;
    $paidReplay=mg_payout_process_event($pdo,'sandbox',$paidEvent,$paidPayload);
    mg_cashout_behavior_assert($paidReplay['duplicate']===true,'Exact webhook replay was not idempotent.');
    $summary['webhook_replay']=true;

    $payloadConflict=false;$changed=$paidEvent;$changed['data']['provider_reference']='changed';
    try{mg_payout_process_event($pdo,'sandbox',$changed,json_encode($changed,JSON_THROW_ON_ERROR));}catch(MgCashoutWorkflowException $e){$payloadConflict=$e->httpStatus===409;}
    mg_cashout_behavior_assert($payloadConflict,'Conflicting webhook payload was accepted.');
    $summary['webhook_conflict_rejected']=true;
    $terminalConflict=false;$pdo->exec('SAVEPOINT terminal_conflict');
    $lateFailure=mg_cashout_behavior_event('evt-late-fail-'.$runId,'payout.failed',$approved1['payout_id'],['failure_message'=>'Late failure']);
    try{mg_payout_process_event($pdo,'sandbox',$lateFailure,json_encode($lateFailure,JSON_THROW_ON_ERROR));}catch(MgCashoutWorkflowException $e){$terminalConflict=$e->httpStatus===409;$pdo->exec('ROLLBACK TO SAVEPOINT terminal_conflict');}
    mg_cashout_behavior_assert($terminalConflict,'Conflicting terminal payout event was accepted.');
    $summary['terminal_conflict_rejected']=true;

    $cashout2=mg_cashout_request($pdo,$wallet,$merchantId,2000,'failed-'.$runId);
    $approved2=mg_cashout_approve($pdo,$cashout2,$adminId);
    $failedEvent=mg_cashout_behavior_event('evt-failed-'.$runId,'payout.failed',$approved2['payout_id'],['failure_message'=>'Behavior decline']);
    $failedPayload=json_encode($failedEvent,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $failedResult=mg_payout_process_event($pdo,'sandbox',$failedEvent,$failedPayload);
    mg_cashout_behavior_assert(($failedResult['result']['status']??null)==='failed','Failed payout did not settle.');
    $balances=mg_wallet_balances($pdo,(int)$wallet['id']);
    mg_cashout_behavior_assert((int)$balances['available_cents']===7000&&(int)$balances['cashout_pending_cents']===0&&(int)$balances['paid_cents']===3000,'Failed payout did not restore available balance.');
    $summary['failed_payout_restored_balance']=true;

    $cashout3=mg_cashout_request($pdo,$wallet,$merchantId,1000,'rollback-'.$runId);
    $approved3=mg_cashout_approve($pdo,$cashout3,$adminId);
    $groupsBefore=(int)mg_cashout_behavior_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups');
    $eventsBefore=(int)mg_cashout_behavior_scalar($pdo,'SELECT COUNT(*) FROM payment_webhook_events');
    $alertsBefore=(int)mg_cashout_behavior_scalar($pdo,'SELECT COUNT(*) FROM operational_alerts WHERE user_id=?',[$merchantId]);
    $pdo->exec('SAVEPOINT forced_payout_failure');$forced=false;
    $forcedEvent=mg_cashout_behavior_event('evt-forced-'.$runId,'payout.paid',$approved3['payout_id']);
    try{
        mg_payout_process_event($pdo,'sandbox',$forcedEvent,json_encode($forcedEvent,JSON_THROW_ON_ERROR),static function(string $stage): void {
            if($stage==='after_ledger')throw new RuntimeException('Forced payout failure.');
        });
    }catch(Throwable){$forced=true;$pdo->exec('ROLLBACK TO SAVEPOINT forced_payout_failure');}
    mg_cashout_behavior_assert($forced,'Forced payout failure did not throw.');
    mg_cashout_behavior_assert((string)mg_cashout_behavior_scalar($pdo,'SELECT status FROM merchant_payouts WHERE public_id=?',[$approved3['payout_id']])==='pending','Forced failure changed payout state.');
    mg_cashout_behavior_assert((string)mg_cashout_behavior_scalar($pdo,'SELECT status FROM cashout_requests WHERE public_id=?',[$cashout3['public_id']])==='queued','Forced failure changed cashout state.');
    mg_cashout_behavior_assert((int)mg_cashout_behavior_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups')===$groupsBefore,'Forced failure left ledger state.');
    mg_cashout_behavior_assert((int)mg_cashout_behavior_scalar($pdo,'SELECT COUNT(*) FROM payment_webhook_events')===$eventsBefore,'Forced failure left webhook state.');
    mg_cashout_behavior_assert((int)mg_cashout_behavior_scalar($pdo,'SELECT COUNT(*) FROM operational_alerts WHERE user_id=?',[$merchantId])===$alertsBefore,'Forced failure left notifications.');
    $summary['forced_failure_rolled_back']=true;

    mg_cashout_behavior_assert((int)mg_cashout_behavior_scalar($pdo,"SELECT COUNT(*) FROM operational_alerts WHERE user_id=? AND alert_type IN ('cashout_requested','cashout_approved','payout_paid','payout_failed','payout_hold_created','payout_hold_released')",[$merchantId])===10,'Cashout notifications were not created exactly once.');
    $summary['notifications_once']=true;
    $summary['ledger_consistent']=mg_cashout_behavior_group_balanced($pdo,(int)mg_cashout_behavior_scalar($pdo,'SELECT id FROM ledger_transaction_groups WHERE idempotency_key=?',['payout:paid:'.$approved1['payout_id']]))
        &&mg_cashout_behavior_group_balanced($pdo,(int)mg_cashout_behavior_scalar($pdo,'SELECT id FROM ledger_transaction_groups WHERE idempotency_key=?',['payout:failed:'.$approved2['payout_id']]));
    mg_cashout_behavior_assert($summary['ledger_consistent'],'Payout settlement groups are not balanced.');

    $pdo->rollBack();
    mg_cashout_behavior_assert((int)mg_cashout_behavior_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$merchantEmail,$adminEmail])===0,'Cashout users remain.');
    mg_cashout_behavior_assert((int)mg_cashout_behavior_scalar($pdo,'SELECT COUNT(*) FROM cashout_requests WHERE idempotency_key LIKE ?',['%'.$runId])===0,'Cashout fixtures remain.');
    mg_cashout_behavior_assert((int)mg_cashout_behavior_scalar($pdo,'SELECT COUNT(*) FROM payment_webhook_events WHERE provider_event_id LIKE ?',['%'.$runId])===0,'Webhook fixtures remain.');
    $summary['fixtures_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
