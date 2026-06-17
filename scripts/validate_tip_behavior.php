<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/tips/_tips.php';
require_once dirname(__DIR__).'/api/tips/_notifications.php';
require_once dirname(__DIR__).'/tests/integration/TipBehaviorFixture.php';

$pdo=mg_db();
$runId='tip_'.bin2hex(random_bytes(6));
$prefix='tip-behavior:'.$runId;
$baseline=mg_tip_it_state_counts($pdo);
$summary=[
    'suite'=>'stage12b_wallet_tip_behavior',
    'run_id'=>$runId,
    'balanced_posting'=>false,
    'wallet_routing'=>false,
    'exact_replay'=>false,
    'conflicting_replay'=>false,
    'self_tip_rejected'=>false,
    'insufficient_funds_rollback'=>false,
    'velocity_rollback'=>false,
    'notification_idempotency'=>false,
    'transaction_rollback'=>false,
    'reversal_integrity'=>false,
    'reversal_replay'=>false,
    'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $sender=mg_it_user($pdo,$runId.'-sender@example.test','Tip Behavior Sender');
    $userRecipient=mg_it_user($pdo,$runId.'-user@example.test','Tip Behavior User');
    $creatorRecipient=mg_it_user($pdo,$runId.'-creator@example.test','Tip Behavior Creator');
    $merchantRecipient=mg_it_user($pdo,$runId.'-merchant@example.test','Tip Behavior Merchant');
    $brokeSender=mg_it_user($pdo,$runId.'-broke@example.test','Tip Behavior Broke Sender');
    $velocitySender=mg_it_user($pdo,$runId.'-velocity@example.test','Tip Behavior Velocity Sender');
    $workspace=mg_tip_it_merchant_workspace($pdo,$merchantRecipient,$runId);

    $senderWallet=mg_tip_it_fund_sender($pdo,$sender,$runId,100000);
    $feeAccount=mg_ledger_platform_account($pdo,'tip_fee_revenue','revenue','credit','USD');

    $routes=[
        ['name'=>'user','target_type'=>'profile','target_reference'=>(string)$userRecipient,'recipient_user_id'=>$userRecipient,'wallet_type'=>'user','amount_cents'=>1000],
        ['name'=>'creator','target_type'=>'creator','target_reference'=>(string)$creatorRecipient,'recipient_user_id'=>$creatorRecipient,'wallet_type'=>'creator','amount_cents'=>2000],
        ['name'=>'merchant','target_type'=>'merchant','target_reference'=>$workspace['public_id'],'recipient_user_id'=>$merchantRecipient,'wallet_type'=>'merchant','amount_cents'=>3000],
    ];
    $posted=[];
    $allBalanced=true;
    $allRouted=true;
    $allReplay=true;
    $allConflicts=true;
    $allNotifications=true;

    foreach($routes as $route){
        $recipientWallet=mg_tip_it_wallet($pdo,$route['wallet_type'],$route['recipient_user_id'],'USD');
        $senderBefore=mg_tip_it_account_balance($pdo,$senderWallet['available_account_id']);
        $recipientBefore=mg_tip_it_account_balance($pdo,$recipientWallet['available_account_id']);
        $feeBefore=mg_tip_it_account_balance($pdo,$feeAccount);
        $fee=mg_tip_fee_snapshot($route['amount_cents']);
        $key=$prefix.':'.$route['name'];
        $input=[
            'target_type'=>$route['target_type'],
            'target_reference'=>$route['target_reference'],
            'amount_cents'=>$route['amount_cents'],
            'currency'=>'USD',
            'funding_type'=>'wallet',
            'idempotency_key'=>$key,
            'metadata'=>['run_id'=>$runId,'route'=>$route['name']],
        ];

        $tip=mg_tip_create($pdo,$sender,$input);
        mg_it_assert(empty($tip['duplicate'])&&(string)$tip['status']==='posted','Wallet tip did not post for '.$route['name'].'.');
        mg_it_assert((string)$tip['recipient_wallet_owner_type']===$route['wallet_type'],'Tip wallet owner type is incorrect for '.$route['name'].'.');
        mg_it_assert((int)$tip['recipient_wallet_owner_user_id']===$route['recipient_user_id'],'Tip wallet owner is incorrect for '.$route['name'].'.');
        mg_it_assert((int)$tip['fee_cents']===(int)$fee['fee_cents']&&(int)$tip['net_cents']===(int)$fee['net_cents'],'Tip fee snapshot is incorrect for '.$route['name'].'.');

        $alertA=mg_tip_notify_recipient($pdo,$tip);
        $alertB=mg_tip_notify_recipient($pdo,$tip);
        mg_it_assert($alertA===$alertB,'Recipient alert replay returned a different alert for '.$route['name'].'.');

        $groupId=(int)$tip['ledger_group_id'];
        $sides=mg_tip_it_group_sides($pdo,$groupId);
        $entryCount=(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM ledger_entries WHERE transaction_group_id=?',[$groupId]);
        $expectedEntryCount=(int)$fee['fee_cents']>0?3:2;
        $balanced=($sides['debit']??0)===$route['amount_cents']
            &&($sides['credit']??0)===$route['amount_cents']
            &&$entryCount===$expectedEntryCount
            &&mg_tip_it_ledger_entry_amount($pdo,$groupId,$senderWallet['available_account_id'],'debit')===$route['amount_cents']
            &&mg_tip_it_ledger_entry_amount($pdo,$groupId,$recipientWallet['available_account_id'],'credit')===(int)$fee['net_cents']
            &&mg_tip_it_ledger_entry_amount($pdo,$groupId,$feeAccount,'credit')===(int)$fee['fee_cents'];
        $allBalanced=$allBalanced&&$balanced;

        $routed=mg_tip_it_account_balance($pdo,$senderWallet['available_account_id'])===$senderBefore-$route['amount_cents']
            &&mg_tip_it_account_balance($pdo,$recipientWallet['available_account_id'])===$recipientBefore+(int)$fee['net_cents']
            &&mg_tip_it_account_balance($pdo,$feeAccount)===$feeBefore+(int)$fee['fee_cents'];
        $allRouted=$allRouted&&$routed;

        mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tip_events WHERE tip_id=?',[(int)$tip['id']])===2,'Tip lifecycle events are missing or duplicated for '.$route['name'].'.');
        mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM tip_events WHERE tip_id=? AND event_type='funded'",[(int)$tip['id']])===1,'Funded event is missing for '.$route['name'].'.');
        mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM tip_events WHERE tip_id=? AND event_type='posted'",[(int)$tip['id']])===1,'Posted event is missing for '.$route['name'].'.');
        mg_it_assert(mg_tip_it_alert_count($pdo,(string)$tip['public_id'],'tip_received')===1,'Recipient alert was duplicated for '.$route['name'].'.');
        mg_it_assert(mg_tip_it_event_count($pdo,(string)$tip['public_id'],'tip.received')===1,'Recipient event was duplicated for '.$route['name'].'.');
        mg_it_assert(mg_tip_it_event_count($pdo,(string)$tip['public_id'],'tip.posted')===1,'Posted event was duplicated for '.$route['name'].'.');

        $beforeReplay=mg_tip_it_state_counts($pdo);
        $replay=mg_tip_create($pdo,$sender,$input);
        $replayAlert=mg_tip_notify_recipient($pdo,$replay);
        $afterReplay=mg_tip_it_state_counts($pdo);
        $replayed=!empty($replay['duplicate'])
            &&(int)$replay['id']===(int)$tip['id']
            &&$replayAlert===$alertA
            &&$beforeReplay===$afterReplay;
        $allReplay=$allReplay&&$replayed;

        $beforeConflict=mg_tip_it_state_counts($pdo);
        $pdo->exec('SAVEPOINT tip_conflict');
        $conflict=$input;$conflict['amount_cents']=$route['amount_cents']+100;
        mg_tip_it_expect_throw(static fn()=>mg_tip_create($pdo,$sender,$conflict),'Idempotency key is already bound to a different tip request.');
        $pdo->exec('ROLLBACK TO SAVEPOINT tip_conflict');
        $pdo->exec('RELEASE SAVEPOINT tip_conflict');
        $allConflicts=$allConflicts&&$beforeConflict===mg_tip_it_state_counts($pdo);

        $allNotifications=$allNotifications
            &&mg_tip_it_alert_count($pdo,(string)$tip['public_id'],'tip_received')===1
            &&mg_tip_it_event_count($pdo,(string)$tip['public_id'],'tip.received')===1;
        $posted[$route['name']]=['tip'=>$tip,'route'=>$route,'recipient_wallet'=>$recipientWallet,'fee'=>$fee,'alert_id'=>$alertA];
    }

    mg_it_assert($allBalanced,'One or more wallet tips were not balanced.');
    mg_it_assert($allRouted,'One or more wallet tips were routed to the wrong wallet.');
    mg_it_assert($allReplay,'One or more exact wallet-tip replays changed persisted state.');
    mg_it_assert($allConflicts,'One or more conflicting wallet-tip replays changed persisted state.');
    mg_it_assert($allNotifications,'One or more wallet-tip notifications were duplicated.');
    $summary['balanced_posting']=true;
    $summary['wallet_routing']=true;
    $summary['exact_replay']=true;
    $summary['conflicting_replay']=true;
    $summary['notification_idempotency']=true;

    $window=gmdate('YmdH');
    $senderVelocityBefore=(int)mg_it_scalar($pdo,'SELECT COALESCE(tip_count,0) FROM tip_velocity_counters WHERE sender_user_id=? AND window_key=?',[$sender,$window]);
    $pdo->exec('SAVEPOINT self_tip');
    mg_tip_it_expect_throw(static fn()=>mg_tip_create($pdo,$sender,[
        'target_type'=>'profile','target_reference'=>(string)$sender,'amount_cents'=>1000,'currency'=>'USD','funding_type'=>'wallet','idempotency_key'=>$prefix.':self',
    ]),'You cannot tip yourself.');
    $pdo->exec('ROLLBACK TO SAVEPOINT self_tip');
    $pdo->exec('RELEASE SAVEPOINT self_tip');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tips WHERE sender_user_id=? AND idempotency_key=?',[$sender,$prefix.':self'])===0,'Self-tip persisted a tip row.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COALESCE(tip_count,0) FROM tip_velocity_counters WHERE sender_user_id=? AND window_key=?',[$sender,$window])===$senderVelocityBefore,'Self-tip changed velocity counters.');
    $summary['self_tip_rejected']=true;

    $beforeInsufficient=mg_tip_it_state_counts($pdo);
    $pdo->exec('SAVEPOINT insufficient_funds');
    mg_tip_it_expect_throw(static fn()=>mg_tip_create($pdo,$brokeSender,[
        'target_type'=>'profile','target_reference'=>(string)$userRecipient,'amount_cents'=>1000,'currency'=>'USD','funding_type'=>'wallet','idempotency_key'=>$prefix.':insufficient',
    ]),'Insufficient wallet balance.');
    $pdo->exec('ROLLBACK TO SAVEPOINT insufficient_funds');
    $pdo->exec('RELEASE SAVEPOINT insufficient_funds');
    mg_it_assert($beforeInsufficient===mg_tip_it_state_counts($pdo),'Insufficient-funds failure left partial tip state.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tips WHERE sender_user_id=? AND idempotency_key=?',[$brokeSender,$prefix.':insufficient'])===0,'Insufficient-funds tip row remains.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM wallets WHERE owner_type=\'user\' AND owner_user_id=?',[$brokeSender])===0,'Insufficient-funds failure left a sender wallet fixture.');
    $summary['insufficient_funds_rollback']=true;

    mg_tip_it_fund_sender($pdo,$velocitySender,$runId.'_velocity',5000);
    $pdo->prepare('INSERT INTO tip_velocity_counters (sender_user_id,window_key,tip_count,amount_cents,updated_at) VALUES (?,?,20,0,NOW()) ON DUPLICATE KEY UPDATE tip_count=20,amount_cents=0,updated_at=NOW()')->execute([$velocitySender,$window]);
    $beforeVelocity=mg_tip_it_state_counts($pdo);
    $pdo->exec('SAVEPOINT velocity_limit');
    mg_tip_it_expect_throw(static fn()=>mg_tip_create($pdo,$velocitySender,[
        'target_type'=>'profile','target_reference'=>(string)$userRecipient,'amount_cents'=>1000,'currency'=>'USD','funding_type'=>'wallet','idempotency_key'=>$prefix.':velocity',
    ]),'Tip velocity limit exceeded.');
    $pdo->exec('ROLLBACK TO SAVEPOINT velocity_limit');
    $pdo->exec('RELEASE SAVEPOINT velocity_limit');
    mg_it_assert($beforeVelocity===mg_tip_it_state_counts($pdo),'Velocity-limit failure left partial tip state.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tips WHERE sender_user_id=? AND idempotency_key=?',[$velocitySender,$prefix.':velocity'])===0,'Velocity-limit tip row remains.');
    $summary['velocity_rollback']=true;

    $beforeAtomic=mg_tip_it_state_counts($pdo);
    $senderBalanceBeforeAtomic=mg_tip_it_account_balance($pdo,$senderWallet['available_account_id']);
    $recipientBalanceBeforeAtomic=mg_tip_it_account_balance($pdo,$posted['user']['recipient_wallet']['available_account_id']);
    $feeBalanceBeforeAtomic=mg_tip_it_account_balance($pdo,$feeAccount);
    $pdo->exec('SAVEPOINT downstream_failure');
    try{
        $atomicTip=mg_tip_create($pdo,$sender,[
            'target_type'=>'profile','target_reference'=>(string)$userRecipient,'amount_cents'=>1500,'currency'=>'USD','funding_type'=>'wallet','idempotency_key'=>$prefix.':downstream',
        ]);
        mg_tip_notify_recipient($pdo,$atomicTip);
        throw new RuntimeException('Simulated downstream failure.');
    }catch(RuntimeException $error){
        mg_it_assert($error->getMessage()==='Simulated downstream failure.','Unexpected downstream failure: '.$error->getMessage());
        $pdo->exec('ROLLBACK TO SAVEPOINT downstream_failure');
    }
    $pdo->exec('RELEASE SAVEPOINT downstream_failure');
    mg_it_assert($beforeAtomic===mg_tip_it_state_counts($pdo),'Downstream failure left partial tip, ledger, event, or alert state.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$senderWallet['available_account_id'])===$senderBalanceBeforeAtomic,'Downstream failure changed sender balance.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$posted['user']['recipient_wallet']['available_account_id'])===$recipientBalanceBeforeAtomic,'Downstream failure changed recipient balance.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$feeAccount)===$feeBalanceBeforeAtomic,'Downstream failure changed fee balance.');
    $summary['transaction_rollback']=true;

    $merchantTip=$posted['merchant']['tip'];
    $merchantRoute=$posted['merchant']['route'];
    $merchantWallet=$posted['merchant']['recipient_wallet'];
    $merchantFee=$posted['merchant']['fee'];
    $senderBeforeReverse=mg_tip_it_account_balance($pdo,$senderWallet['available_account_id']);
    $merchantBeforeReverse=mg_tip_it_account_balance($pdo,$merchantWallet['available_account_id']);
    $feeBeforeReverse=mg_tip_it_account_balance($pdo,$feeAccount);
    $reverseKey=$prefix.':reverse';
    $reason='Stage 12B behavior reversal';
    $reverse=mg_tip_reverse($pdo,$sender,(string)$merchantTip['public_id'],$reverseKey,$reason);
    mg_it_assert(empty($reverse['duplicate']),'Initial tip reversal was treated as a replay.');
    $reverseAlertA=mg_tip_notify_reversal($pdo,$reverse['tip'],$reason);
    $reverseAlertB=mg_tip_notify_reversal($pdo,$reverse['tip'],$reason);
    mg_it_assert($reverseAlertA===$reverseAlertB,'Reversal alert replay returned a different alert.');
    mg_it_assert((string)mg_it_scalar($pdo,'SELECT status FROM tips WHERE id=?',[(int)$merchantTip['id']])==='reversed','Tip was not marked reversed.');
    mg_it_assert((string)mg_it_scalar($pdo,'SELECT status FROM ledger_transaction_groups WHERE id=?',[(int)$merchantTip['ledger_group_id']])==='reversed','Original tip ledger group was not marked reversed.');
    mg_it_assert((int)$reverse['reversal']['amount_cents']===$merchantRoute['amount_cents']&&(string)$reverse['reversal']['currency']==='USD','Reversal money snapshot is incorrect.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM ledger_reversal_links WHERE original_group_id=? AND reversal_group_id=?',[(int)$merchantTip['ledger_group_id'],(int)$reverse['reversal']['ledger_group_id']])===1,'Tip reversal link is missing.');
    $reversalSides=mg_tip_it_group_sides($pdo,(int)$reverse['reversal']['ledger_group_id']);
    mg_it_assert(($reversalSides['debit']??0)===$merchantRoute['amount_cents']&&($reversalSides['credit']??0)===$merchantRoute['amount_cents'],'Tip reversal ledger group is not balanced.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$senderWallet['available_account_id'])===$senderBeforeReverse+$merchantRoute['amount_cents'],'Tip reversal did not restore sender funds.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$merchantWallet['available_account_id'])===$merchantBeforeReverse-(int)$merchantFee['net_cents'],'Tip reversal did not remove merchant proceeds.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$feeAccount)===$feeBeforeReverse-(int)$merchantFee['fee_cents'],'Tip reversal did not remove fee revenue.');
    mg_it_assert(mg_tip_it_alert_count($pdo,(string)$merchantTip['public_id'],'tip_reversed')===1,'Tip reversal alert was duplicated.');
    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM tip_events WHERE tip_id=? AND event_type='reversed'",[(int)$merchantTip['id']])===1,'Tip reversal lifecycle event is missing or duplicated.');
    $summary['reversal_integrity']=true;

    $reversalCountBefore=(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tip_reversals WHERE tip_id=?',[(int)$merchantTip['id']]);
    $reversalEventCountBefore=(int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM tip_events WHERE tip_id=? AND event_type='reversed'",[(int)$merchantTip['id']]);
    $senderBeforeReplay=mg_tip_it_account_balance($pdo,$senderWallet['available_account_id']);
    $merchantBeforeReplay=mg_tip_it_account_balance($pdo,$merchantWallet['available_account_id']);
    $feeBeforeReplay=mg_tip_it_account_balance($pdo,$feeAccount);
    $reverseReplay=mg_tip_reverse($pdo,$sender,(string)$merchantTip['public_id'],$reverseKey,$reason);
    $reverseReplayAlert=mg_tip_notify_reversal($pdo,$reverseReplay['tip'],$reason);
    mg_it_assert(!empty($reverseReplay['duplicate']),'Exact reversal replay was not recognized.');
    mg_it_assert((string)$reverseReplay['reversal']['public_id']===(string)$reverse['reversal']['public_id'],'Exact reversal replay returned a different reversal.');
    mg_it_assert($reverseReplayAlert===$reverseAlertA,'Exact reversal replay returned a different alert.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tip_reversals WHERE tip_id=?',[(int)$merchantTip['id']])===$reversalCountBefore,'Exact reversal replay duplicated the reversal row.');
    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM tip_events WHERE tip_id=? AND event_type='reversed'",[(int)$merchantTip['id']])===$reversalEventCountBefore,'Exact reversal replay duplicated the lifecycle event.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$senderWallet['available_account_id'])===$senderBeforeReplay&&mg_tip_it_account_balance($pdo,$merchantWallet['available_account_id'])===$merchantBeforeReplay&&mg_tip_it_account_balance($pdo,$feeAccount)===$feeBeforeReplay,'Exact reversal replay changed financial positions.');

    $pdo->exec('SAVEPOINT reversal_conflict');
    mg_tip_it_expect_throw(static fn()=>mg_tip_reverse($pdo,$sender,(string)$merchantTip['public_id'],$reverseKey,'Conflicting reversal reason'),'Tip is already reversed by a different request.');
    $pdo->exec('ROLLBACK TO SAVEPOINT reversal_conflict');
    $pdo->exec('RELEASE SAVEPOINT reversal_conflict');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tip_reversals WHERE tip_id=?',[(int)$merchantTip['id']])===$reversalCountBefore,'Conflicting reversal replay changed persisted state.');
    $summary['reversal_replay']=true;

    $pdo->rollBack();
    mg_it_assert($baseline===mg_tip_it_state_counts($pdo),'Stage 12B behavior fixtures changed persistent tip state.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email LIKE ?',[$runId.'-%@example.test'])===0,'Stage 12B user fixtures remain.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM ledger_transaction_groups WHERE idempotency_key LIKE ?',[$prefix.'%'])===0,'Stage 12B ledger fixtures remain.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM tips WHERE idempotency_key LIKE ?',[$prefix.'%'])===0,'Stage 12B tip fixtures remain.');
    $summary['fixtures_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
