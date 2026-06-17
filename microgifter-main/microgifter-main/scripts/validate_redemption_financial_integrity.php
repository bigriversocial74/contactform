<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/microgifts/_lifecycle.php';
require_once dirname(__DIR__).'/api/payments/_refund.php';
require_once dirname(__DIR__).'/api/finance/_cashouts.php';
require_once dirname(__DIR__).'/tests/integration/DisputeBehaviorFixture.php';

function mg_rfi_assert(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
function mg_rfi_scalar(PDO $pdo,string $sql,array $params=[]): mixed {$stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();}
function mg_rfi_balances(PDO $pdo,int $merchantId): array {$wallet=mg_wallet_resolve($pdo,'merchant',$merchantId,'USD');return ['wallet'=>$wallet,'balances'=>mg_wallet_balances($pdo,(int)$wallet['id'])];}
function mg_rfi_instances(PDO $pdo,int $orderId): array
{
    $stmt=$pdo->prepare("SELECT mi.* FROM microgift_instances mi INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND mi.source_type='commerce_order_item' ORDER BY mi.id FOR UPDATE");
    $stmt->execute([$orderId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function mg_rfi_make_redeemable(PDO $pdo,array $instance,int $buyerId,int $merchantId,string $key): void
{
    $credential=mg_microgift_create_credential($pdo,(int)$instance['id'],'claim',$merchantId,$instance['expires_at']?:null);
    $claim=mg_microgift_claim($pdo,$buyerId,['instance_id'=>(string)$instance['public_id'],'code'=>$credential['code'],'idempotency_key'=>'rfi:claim:'.$key]);
    mg_rfi_assert(($claim['status']??'')==='completed','Fixture claim did not complete.');
}

$pdo=mg_db();$runId='redemption_financial_'.bin2hex(random_bytes(8));
$summary=[
    'suite'=>'redemption_financial_integrity_behavior','run_id'=>$runId,
    'canonical_merchant_enforced'=>false,'immutable_snapshot_used'=>false,'wallet_not_double_credited'=>false,
    'exact_replay_safe'=>false,'conflicting_replay_rejected'=>false,'recovery_review_created'=>false,
    'refund_blocks_cashout'=>false,'rollback_safe'=>false,'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $buyerEmail=$runId.'-buyer@example.test';$merchantEmail=$runId.'-merchant@example.test';$otherEmail=$runId.'-other@example.test';
    $buyerId=mg_it_user($pdo,$buyerEmail,'Redemption Buyer');$merchantId=mg_it_user($pdo,$merchantEmail,'Redemption Merchant');$otherMerchantId=mg_it_user($pdo,$otherEmail,'Other Merchant');
    $catalog=mg_dispute_fixture_catalog($pdo,$merchantId,$runId);
    $fixture=['run_id'=>$runId,'buyer_id'=>$buyerId,'merchant_id'=>$merchantId,'buyer_email'=>$buyerEmail]+$catalog;

    $order=mg_dispute_fixture_order($pdo,$fixture,'primary');
    $instances=mg_rfi_instances($pdo,$order['order_id']);
    mg_rfi_assert(count($instances)===2,'Paid-order fixture did not issue two Microgifts.');
    mg_rfi_make_redeemable($pdo,$instances[0],$buyerId,$merchantId,$runId.':primary');
    $walletContext=mg_rfi_balances($pdo,$merchantId);$before=(int)$walletContext['balances']['available_cents'];
    mg_rfi_assert($before===2500,'Paid-order merchant wallet was not funded exactly once.');

    $wrongMerchantRejected=false;
    try{
        mg_microgift_redeem($pdo,$buyerId,[
            'instance_id'=>(string)$instances[0]['public_id'],'merchant_user_id'=>$otherMerchantId,
            'location_reference'=>'store-1','source_reference'=>'pos-wrong-'.$runId,'idempotency_key'=>'rfi:wrong:'.$runId,
        ]);
    }catch(RuntimeException $error){$wrongMerchantRejected=$error->getMessage()==='Microgift is not redeemable by this merchant.';}
    mg_rfi_assert($wrongMerchantRejected,'A non-canonical merchant was allowed to redeem the Microgift.');
    mg_rfi_assert((int)mg_rfi_scalar($pdo,'SELECT COUNT(*) FROM microgift_redemptions WHERE instance_id=?',[(int)$instances[0]['id']])===0,'Rejected merchant attempt created a redemption.');
    $summary['canonical_merchant_enforced']=true;

    $input=[
        'instance_id'=>(string)$instances[0]['public_id'],'merchant_user_id'=>$merchantId,
        'location_reference'=>'store-1','source_reference'=>'pos-'.$runId,'idempotency_key'=>'rfi:redeem:'.$runId,
        'metadata'=>['terminal'=>'behavior'],
    ];
    $redeemed=mg_microgift_redeem($pdo,$buyerId,$input);
    mg_rfi_assert($redeemed['duplicate']===false&&$redeemed['status']==='completed','Canonical redemption did not complete.');
    mg_rfi_assert((int)$redeemed['amount_cents']===(int)$instances[0]['face_value_cents']&&(string)$redeemed['currency']===(string)$instances[0]['currency'],'Redemption did not use immutable Microgift value snapshots.');
    mg_rfi_assert((string)$redeemed['financial_policy']==='merchant_wallet_precredited_at_payment','Redemption financial policy was not explicit.');
    mg_rfi_assert((int)mg_rfi_scalar($pdo,'SELECT amount_cents FROM microgift_redemptions WHERE public_id=?',[$redeemed['redemption_id']])===(int)$instances[0]['face_value_cents'],'Stored redemption amount differs from the Microgift snapshot.');
    mg_rfi_assert((string)mg_rfi_scalar($pdo,'SELECT status FROM pppm_items WHERE id=?',[(int)$instances[0]['pppm_item_id']])==='redeemed','PPPM unit did not redeem with the Microgift.');
    $summary['immutable_snapshot_used']=true;

    $after=(int)(mg_rfi_balances($pdo,$merchantId)['balances']['available_cents']);
    mg_rfi_assert($after===$before,'Redemption credited the merchant wallet a second time.');
    mg_rfi_assert((int)mg_rfi_scalar($pdo,"SELECT COUNT(*) FROM ledger_transaction_groups WHERE source_type='microgift_redemption' AND source_reference=?",[$redeemed['redemption_id']])===0,'Redemption created a duplicate merchant-credit ledger group.');
    $summary['wallet_not_double_credited']=true;

    $replay=mg_microgift_redeem($pdo,$buyerId,$input);
    mg_rfi_assert($replay['duplicate']===true&&$replay['redemption_id']===$redeemed['redemption_id'],'Exact redemption replay was not idempotent.');
    mg_rfi_assert((int)mg_rfi_scalar($pdo,'SELECT COUNT(*) FROM microgift_redemptions WHERE idempotency_key=?',[$input['idempotency_key']])===1,'Exact replay duplicated redemption rows.');
    mg_rfi_assert((int)(mg_rfi_balances($pdo,$merchantId)['balances']['available_cents'])===$before,'Exact replay changed the merchant balance.');
    $summary['exact_replay_safe']=true;

    $conflict=$input;$conflict['source_reference']='changed-pos-'.$runId;$conflictRejected=false;
    try{mg_microgift_redeem($pdo,$buyerId,$conflict);}catch(RuntimeException $error){$conflictRejected=$error->getMessage()==='Redemption idempotency key is already bound to a different request.';}
    mg_rfi_assert($conflictRejected,'Conflicting redemption replay was accepted.');
    $summary['conflicting_replay_rejected']=true;

    $refund=mg_finance_refund_order($pdo,$merchantId,$merchantId,[
        'order_id'=>$order['order_public'],'amount_cents'=>2500,'reason'=>'merchant_error','idempotency_key'=>'rfi:refund:'.$runId,
    ]);
    mg_rfi_assert(($refund['order_status']??'')==='refunded','Post-redemption refund did not complete.');
    mg_rfi_assert((string)mg_rfi_scalar($pdo,'SELECT status FROM microgift_instances WHERE id=?',[(int)$instances[0]['id']])==='redeemed','Refund rewrote completed redemption history.');
    mg_rfi_assert((int)mg_rfi_scalar($pdo,"SELECT COUNT(*) FROM entitlement_review_items WHERE commerce_order_id=? AND review_type='policy_exception' AND status='open' AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.microgift_review_type'))='microgift_recovery'",[$order['order_id']])===1,'Redeemed refund did not create a recovery review.');
    $summary['recovery_review_created']=true;

    $walletContext=mg_rfi_balances($pdo,$merchantId);
    mg_rfi_assert((int)$walletContext['balances']['available_cents']===0,'Full refund did not remove payout-eligible balance.');
    $cashoutBlocked=false;
    try{mg_cashout_request($pdo,$walletContext['wallet'],$merchantId,1,'rfi:cashout:'.$runId);}catch(MgCashoutWorkflowException $error){$cashoutBlocked=$error->getMessage()==='Insufficient available balance.';}
    mg_rfi_assert($cashoutBlocked,'Cashout ignored ledger-derived post-refund availability.');
    mg_rfi_assert((int)mg_rfi_scalar($pdo,'SELECT COUNT(*) FROM cashout_requests WHERE idempotency_key=?',['rfi:cashout:'.$runId])===0,'Rejected cashout created a request.');
    $summary['refund_blocks_cashout']=true;

    $rollbackOrder=mg_dispute_fixture_order($pdo,$fixture,'rollback');
    $rollbackInstances=mg_rfi_instances($pdo,$rollbackOrder['order_id']);
    mg_rfi_make_redeemable($pdo,$rollbackInstances[0],$buyerId,$merchantId,$runId.':rollback');
    $walletBeforeRollback=(int)(mg_rfi_balances($pdo,$merchantId)['balances']['available_cents']);
    $redemptionRowsBefore=(int)mg_rfi_scalar($pdo,'SELECT COUNT(*) FROM microgift_redemptions');
    $eventsBefore=(int)mg_rfi_scalar($pdo,"SELECT COUNT(*) FROM microgift_events WHERE event_type='microgift.redemption_completed'");
    $pdo->exec('SAVEPOINT rfi_redemption_failure');$forced=false;
    try{
        mg_microgift_redeem($pdo,$buyerId,[
            'instance_id'=>(string)$rollbackInstances[0]['public_id'],'merchant_user_id'=>$merchantId,
            'location_reference'=>'store-1','source_reference'=>'rollback-pos-'.$runId,'idempotency_key'=>'rfi:rollback:'.$runId,
        ],static function(string $stage): void {if($stage==='after_redemption')throw new RuntimeException('Forced redemption failure.');});
    }catch(Throwable){$forced=true;$pdo->exec('ROLLBACK TO SAVEPOINT rfi_redemption_failure');}
    mg_rfi_assert($forced,'Forced redemption failure did not throw.');
    mg_rfi_assert((int)mg_rfi_scalar($pdo,'SELECT COUNT(*) FROM microgift_redemptions')===$redemptionRowsBefore,'Forced failure left a redemption row.');
    mg_rfi_assert((string)mg_rfi_scalar($pdo,'SELECT status FROM microgift_instances WHERE id=?',[(int)$rollbackInstances[0]['id']])==='redeemable','Forced failure left Microgift state.');
    mg_rfi_assert((string)mg_rfi_scalar($pdo,'SELECT status FROM pppm_items WHERE id=?',[(int)$rollbackInstances[0]['pppm_item_id']])==='available','Forced failure left PPPM state.');
    mg_rfi_assert((int)mg_rfi_scalar($pdo,"SELECT COUNT(*) FROM microgift_events WHERE event_type='microgift.redemption_completed'")===$eventsBefore,'Forced failure left redemption events.');
    mg_rfi_assert((int)(mg_rfi_balances($pdo,$merchantId)['balances']['available_cents'])===$walletBeforeRollback,'Forced failure changed merchant wallet balance.');
    $summary['rollback_safe']=true;

    $pdo->rollBack();
    mg_rfi_assert((int)mg_rfi_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?,?)',[$buyerEmail,$merchantEmail,$otherEmail])===0,'Redemption financial fixtures remain.');
    mg_rfi_assert((int)mg_rfi_scalar($pdo,"SELECT COUNT(*) FROM commerce_orders WHERE idempotency_key LIKE ?",['%'.$runId.'%'])===0,'Redemption financial orders remain.');
    $summary['fixtures_clean']=true;
    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();$summary['error']=$error->getMessage();fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);throw $error;}
