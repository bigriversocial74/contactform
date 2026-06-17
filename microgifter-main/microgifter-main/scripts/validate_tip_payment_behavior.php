<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/tips/_payment_events.php';
require_once dirname(__DIR__).'/api/tips/_notifications.php';
require_once dirname(__DIR__).'/tests/integration/TipPaymentBehaviorFixture.php';

$pdo=mg_db();
$runId='tip_payment_'.bin2hex(random_bytes(6));
$prefix='tip-payment:'.$runId;
$baseline=mg_tip_it_state_counts($pdo);
$summary=[
    'suite'=>'stage12c_stripe_tip_payment_behavior',
    'run_id'=>$runId,
    'intent_created'=>false,
    'intent_replay_safe'=>false,
    'intent_conflict_rejected'=>false,
    'provider_contract_verified'=>false,
    'processing_transition'=>false,
    'success_settled_once'=>false,
    'failure_no_credit'=>false,
    'failure_then_success_recovered'=>false,
    'stale_failure_ignored'=>false,
    'downstream_rollback'=>false,
    'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $sender=mg_it_user($pdo,$runId.'-sender@example.test','Tip Payment Sender');
    $recipient=mg_it_user($pdo,$runId.'-recipient@example.test','Tip Payment Recipient');
    $recipientWallet=mg_tip_it_wallet($pdo,'user',$recipient,'USD');
    $feeAccount=mg_ledger_platform_account($pdo,'tip_fee_revenue','revenue','credit','USD');
    $processorAccount=mg_ledger_platform_account($pdo,'processor_clearing','asset','debit','USD');

    $createTip=static function(string $suffix,int $amount) use($pdo,$sender,$recipient,$prefix): array {
        return mg_tip_create($pdo,$sender,[
            'target_type'=>'profile',
            'target_reference'=>(string)$recipient,
            'amount_cents'=>$amount,
            'currency'=>'USD',
            'funding_type'=>'stripe',
            'idempotency_key'=>$prefix.':'.$suffix,
            'metadata'=>['suite'=>'stage12c','suffix'=>$suffix],
        ]);
    };

    $tip=$createTip('success',2500);
    mg_it_assert(empty($tip['duplicate']),'Initial card-funded tip was treated as a replay.');
    mg_it_assert((string)$tip['status']==='requires_action','Initial card-funded tip did not require action.');
    mg_it_assert((int)$tip['payment_intent_id']>0,'Tip was not linked to a canonical payment intent.');
    mg_it_assert((string)($tip['payment_intent_public_id']??'')!=='','Payment intent public ID was not returned.');
    mg_it_assert((string)($tip['provider_payment_id']??'')!=='','Provider payment ID was not returned.');
    mg_it_assert((string)($tip['client_secret']??'')!=='','Client secret was not returned.');
    $intent=mg_tip_payment_it_intent($pdo,(int)$tip['payment_intent_id']);
    mg_it_assert((string)$intent['source_type']==='tip'&&(string)$intent['source_reference']===(string)$tip['public_id'],'Payment intent source linkage is incorrect.');
    mg_it_assert((int)$intent['amount_cents']===2500&&(string)$intent['currency']==='USD','Payment intent amount or currency is incorrect.');
    mg_it_assert((string)$intent['provider_key']===(string)$tip['provider_key'],'Payment intent provider does not match the tip.');
    mg_it_assert((string)$intent['provider_intent_reference']===(string)$tip['provider_payment_id'],'Provider intent reference does not match the tip.');
    mg_it_assert(mg_tip_payment_it_counts($pdo,(string)$tip['public_id'])['payment_intents']===1,'Card-funded tip created duplicate payment intents.');
    $summary['intent_created']=true;

    $beforeReplay=mg_tip_payment_it_counts($pdo,(string)$tip['public_id']);
    $replay=$createTip('success',2500);
    $afterReplay=mg_tip_payment_it_counts($pdo,(string)$tip['public_id']);
    mg_it_assert(!empty($replay['duplicate']),'Exact card-funded tip replay was not recognized.');
    mg_it_assert((int)$replay['id']===(int)$tip['id'],'Exact replay returned a different tip.');
    mg_it_assert((string)$replay['provider_payment_id']===(string)$tip['provider_payment_id'],'Exact replay returned a different provider intent.');
    mg_it_assert((string)$replay['client_secret']===(string)$tip['client_secret'],'Exact replay returned a different client secret.');
    mg_it_assert($beforeReplay===$afterReplay,'Exact card-funded tip replay changed persisted state.');
    $summary['intent_replay_safe']=true;

    $pdo->exec('SAVEPOINT intent_conflict');
    mg_tip_it_expect_throw(static fn()=>mg_tip_create($pdo,$sender,[
        'target_type'=>'profile','target_reference'=>(string)$recipient,'amount_cents'=>2600,'currency'=>'USD','funding_type'=>'stripe','idempotency_key'=>$prefix.':success',
    ]),'Idempotency key is already bound to a different tip request.');
    $pdo->exec('ROLLBACK TO SAVEPOINT intent_conflict');
    $pdo->exec('RELEASE SAVEPOINT intent_conflict');
    mg_it_assert(mg_tip_payment_it_counts($pdo,(string)$tip['public_id'])===$afterReplay,'Conflicting intent replay changed state.');
    $summary['intent_conflict_rejected']=true;

    foreach([
        ['provider','other-provider',mg_tip_payment_it_event($prefix.':bad-provider','payment_intent.succeeded',$tip)],
        ['amount',(string)$tip['provider_key'],mg_tip_payment_it_event($prefix.':bad-amount','payment_intent.succeeded',$tip,['amount'=>2600,'amount_received'=>2600])],
        ['currency',(string)$tip['provider_key'],mg_tip_payment_it_event($prefix.':bad-currency','payment_intent.succeeded',$tip,['currency'=>'eur'])],
        ['metadata',(string)$tip['provider_key'],mg_tip_payment_it_event($prefix.':bad-metadata','payment_intent.succeeded',$tip,['metadata'=>['tip_id'=>mg_public_uuid()]])],
    ] as [$label,$provider,$event]){
        $before=mg_tip_payment_it_counts($pdo,(string)$tip['public_id']);
        $pdo->exec('SAVEPOINT provider_contract');
        mg_tip_it_expect_throw(static fn()=>mg_tip_process_payment_event_result($pdo,$provider,$event),'Tip payment');
        $pdo->exec('ROLLBACK TO SAVEPOINT provider_contract');
        $pdo->exec('RELEASE SAVEPOINT provider_contract');
        mg_it_assert($before===mg_tip_payment_it_counts($pdo,(string)$tip['public_id']),'Invalid '.$label.' event changed state.');
    }
    $summary['provider_contract_verified']=true;

    $processing=mg_tip_process_payment_event_result($pdo,(string)$tip['provider_key'],mg_tip_payment_it_event($prefix.':processing','payment_intent.processing',$tip));
    mg_it_assert((string)$processing['status']==='processing','Processing webhook did not transition the tip.');
    mg_it_assert((string)mg_it_scalar($pdo,'SELECT status FROM payment_intents WHERE id=?',[(int)$tip['payment_intent_id']])==='processing','Processing webhook did not transition the payment intent.');
    $summary['processing_transition']=true;

    $recipientBefore=mg_tip_it_account_balance($pdo,$recipientWallet['available_account_id']);
    $feeBefore=mg_tip_it_account_balance($pdo,$feeAccount);
    $processorBefore=mg_tip_it_account_balance($pdo,$processorAccount);
    $successEvent=mg_tip_payment_it_event($prefix.':success-event','payment_intent.succeeded',$tip);
    $settled=mg_tip_process_payment_event_result($pdo,(string)$tip['provider_key'],$successEvent);
    $alertA=mg_tip_notify_recipient($pdo,$settled);
    $alertB=mg_tip_notify_recipient($pdo,$settled);
    $fee=mg_tip_fee_snapshot(2500);
    mg_it_assert((string)$settled['status']==='posted','Successful webhook did not post the tip.');
    mg_it_assert($alertA===$alertB,'Successful webhook notification replay created a different alert.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM payment_transactions WHERE payment_intent_id=?',[(int)$tip['payment_intent_id']])===1,'Successful webhook did not create exactly one payment transaction.');
    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM ledger_transaction_groups WHERE transaction_type='tip' AND source_reference=?",[(string)$tip['public_id']])===1,'Successful webhook did not create exactly one tip ledger group.');
    $groupId=(int)mg_it_scalar($pdo,"SELECT id FROM ledger_transaction_groups WHERE transaction_type='tip' AND source_reference=?",[(string)$tip['public_id']]);
    $sides=mg_tip_it_group_sides($pdo,$groupId);
    mg_it_assert(($sides['debit']??0)===2500&&($sides['credit']??0)===2500,'Successful tip ledger group is not balanced.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$recipientWallet['available_account_id'])===$recipientBefore+(int)$fee['net_cents'],'Successful webhook did not credit recipient proceeds.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$feeAccount)===$feeBefore+(int)$fee['fee_cents'],'Successful webhook did not credit fee revenue.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$processorAccount)===$processorBefore+2500,'Successful webhook did not debit processor clearing.');
    mg_it_assert(mg_tip_it_alert_count($pdo,(string)$tip['public_id'],'tip_received')===1,'Successful webhook duplicated the recipient alert.');
    $countsAfterSuccess=mg_tip_payment_it_counts($pdo,(string)$tip['public_id']);
    $successReplay=mg_tip_process_payment_event_result($pdo,(string)$tip['provider_key'],$successEvent);
    mg_it_assert(!empty($successReplay['duplicate']),'Successful webhook replay was not recognized.');
    mg_it_assert($countsAfterSuccess===mg_tip_payment_it_counts($pdo,(string)$tip['public_id']),'Successful webhook replay changed persisted state.');
    $summary['success_settled_once']=true;

    $staleFailure=mg_tip_process_payment_event_result($pdo,(string)$tip['provider_key'],mg_tip_payment_it_event($prefix.':stale-failure','payment_intent.payment_failed',$tip,['failure_message'=>'Late decline']));
    mg_it_assert(!empty($staleFailure['ignored'])&&!empty($staleFailure['duplicate']),'Stale failure after success was not ignored.');
    mg_it_assert($countsAfterSuccess===mg_tip_payment_it_counts($pdo,(string)$tip['public_id']),'Stale failure changed posted tip state.');
    $summary['stale_failure_ignored']=true;

    $failedTip=$createTip('failed',1800);
    $failedRecipientBefore=mg_tip_it_account_balance($pdo,$recipientWallet['available_account_id']);
    $failedFeeBefore=mg_tip_it_account_balance($pdo,$feeAccount);
    $failedProcessorBefore=mg_tip_it_account_balance($pdo,$processorAccount);
    $failed=mg_tip_process_payment_event_result($pdo,(string)$failedTip['provider_key'],mg_tip_payment_it_event($prefix.':failed-event','payment_intent.payment_failed',$failedTip,['failure_message'=>'Behavior decline','failure_code'=>'card_declined']));
    mg_it_assert((string)$failed['status']==='failed','Failed webhook did not fail the tip.');
    mg_it_assert((string)mg_it_scalar($pdo,'SELECT status FROM payment_intents WHERE id=?',[(int)$failedTip['payment_intent_id']])==='failed','Failed webhook did not fail the payment intent.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM payment_transactions WHERE payment_intent_id=?',[(int)$failedTip['payment_intent_id']])===0,'Failed webhook created a payment transaction.');
    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM ledger_transaction_groups WHERE transaction_type='tip' AND source_reference=?",[(string)$failedTip['public_id']])===0,'Failed webhook created a tip ledger group.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$recipientWallet['available_account_id'])===$failedRecipientBefore,'Failed webhook credited the recipient.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$feeAccount)===$failedFeeBefore,'Failed webhook credited fee revenue.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$processorAccount)===$failedProcessorBefore,'Failed webhook changed processor clearing.');
    $summary['failure_no_credit']=true;

    $recovered=mg_tip_process_payment_event_result($pdo,(string)$failedTip['provider_key'],mg_tip_payment_it_event($prefix.':recovery-event','payment_intent.succeeded',$failedTip));
    mg_it_assert((string)$recovered['status']==='posted','Success after failure did not recover the tip.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM payment_transactions WHERE payment_intent_id=?',[(int)$failedTip['payment_intent_id']])===1,'Recovered tip did not create exactly one payment transaction.');
    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM ledger_transaction_groups WHERE transaction_type='tip' AND source_reference=?",[(string)$failedTip['public_id']])===1,'Recovered tip did not post exactly one ledger group.');
    $summary['failure_then_success_recovered']=true;

    $rollbackTip=$createTip('rollback',1400);
    $rollbackCounts=mg_tip_payment_it_counts($pdo,(string)$rollbackTip['public_id']);
    $rollbackRecipient=mg_tip_it_account_balance($pdo,$recipientWallet['available_account_id']);
    $rollbackFee=mg_tip_it_account_balance($pdo,$feeAccount);
    $rollbackProcessor=mg_tip_it_account_balance($pdo,$processorAccount);
    $pdo->exec('SAVEPOINT tip_payment_rollback');
    try{
        $rollbackResult=mg_tip_process_payment_event_result($pdo,(string)$rollbackTip['provider_key'],mg_tip_payment_it_event($prefix.':rollback-event','payment_intent.succeeded',$rollbackTip));
        mg_tip_notify_recipient($pdo,$rollbackResult);
        throw new RuntimeException('Simulated post-settlement failure.');
    }catch(RuntimeException $error){
        mg_it_assert($error->getMessage()==='Simulated post-settlement failure.','Unexpected rollback failure: '.$error->getMessage());
        $pdo->exec('ROLLBACK TO SAVEPOINT tip_payment_rollback');
    }
    $pdo->exec('RELEASE SAVEPOINT tip_payment_rollback');
    mg_it_assert($rollbackCounts===mg_tip_payment_it_counts($pdo,(string)$rollbackTip['public_id']),'Post-settlement failure left partial records.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$recipientWallet['available_account_id'])===$rollbackRecipient,'Post-settlement failure changed recipient balance.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$feeAccount)===$rollbackFee,'Post-settlement failure changed fee balance.');
    mg_it_assert(mg_tip_it_account_balance($pdo,$processorAccount)===$rollbackProcessor,'Post-settlement failure changed processor clearing.');
    $summary['downstream_rollback']=true;

    $pdo->rollBack();
    mg_it_assert($baseline===mg_tip_it_state_counts($pdo),'Stage 12C fixtures changed persistent tip state.');
    mg_it_assert((int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email LIKE ?',[$runId.'-%@example.test'])===0,'Stage 12C user fixtures remain.');
    mg_it_assert((int)mg_it_scalar($pdo,"SELECT COUNT(*) FROM payment_intents WHERE source_type='tip' AND idempotency_key LIKE ?",['tip:'.$prefix.'%'])===0,'Stage 12C payment intent fixtures remain.');
    $summary['fixtures_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
