<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

putenv('MG_PAYMENT_PROVIDER=stripe');
putenv('MG_PAYMENT_MODE=test');
putenv('MG_STRIPE_TEST_STUB=1');
putenv('MG_STRIPE_STUB_ACCOUNT_ACTIVE=1');
putenv('MG_APP_URL=https://stage-f.example.test');
putenv('MG_STRIPE_PUBLISHABLE_KEY_TEST=pk_test_stage_f');
putenv('MG_STRIPE_SECRET_KEY_TEST=sk_test_stage_f');
putenv('MG_STRIPE_WEBHOOK_SECRET_TEST=whsec_stage_f');
putenv('MG_PLATFORM_FEE_BPS=1500');
putenv('MG_PLATFORM_FIXED_FEE_CENTS=0');

require_once dirname(__DIR__).'/api/commerce/_checkout.php';
require_once dirname(__DIR__).'/api/payments/_checkout_session.php';
require_once dirname(__DIR__).'/api/payments/_connect.php';
require_once dirname(__DIR__).'/api/payments/_readiness.php';
require_once dirname(__DIR__).'/api/payments/_webhook.php';
require_once dirname(__DIR__).'/tests/integration/CheckoutBehaviorFixture.php';

function mg_stage_f_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_stage_f_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

$pdo=mg_db();
$runId='stage_f_'.bin2hex(random_bytes(6));
$summary=[
    'suite'=>'stage_f_stripe_connect_webhook_behavior',
    'run_id'=>$runId,
    'platform_readiness'=>false,
    'connect_onboarding_created'=>false,
    'connect_account_ready'=>false,
    'platform_fee_snapshotted'=>false,
    'stripe_checkout_created'=>false,
    'destination_and_fee_bound'=>false,
    'stripe_signature_verified'=>false,
    'webhook_capture_completed'=>false,
    'merchant_net_correct'=>false,
    'platform_revenue_correct'=>false,
    'webhook_replay_safe'=>false,
    'fulfillment_once'=>false,
    'invalid_signature_rejected'=>false,
    'fixtures_clean'=>false,
];

$pdo->beginTransaction();
try{
    $buyerEmail=$runId.'-buyer@example.test';
    $merchantEmail=$runId.'-merchant@example.test';
    $buyerId=mg_it_user($pdo,$buyerEmail,'Stage F Buyer');
    $merchantId=mg_it_user($pdo,$merchantEmail,'Stage F Merchant');
    $catalog=mg_checkout_fixture_catalog($pdo,$merchantId,$runId);
    $fixture=['run_id'=>$runId,'buyer_id'=>$buyerId,'merchant_id'=>$merchantId]+$catalog;

    $readiness=mg_payment_readiness($pdo,'stripe','test');
    mg_stage_f_assert($readiness['ready']===true,'Stripe test platform readiness did not pass.');
    mg_stage_f_assert((int)$readiness['provider']['platform_fee_bps']===1500,'Platform fee policy is not 15%.');
    $summary['platform_readiness']=true;

    $onboarding=mg_payment_connect_start($pdo,$merchantId);
    mg_stage_f_assert(str_starts_with((string)$onboarding['account_id'],'acct_test_stub_'),'Stripe Connect account was not created.');
    mg_stage_f_assert(str_starts_with((string)$onboarding['onboarding_url'],'https://connect.stripe.test/'),'Stripe onboarding link was not created.');
    $summary['connect_onboarding_created']=true;

    $account=mg_payment_connect_status($pdo,$merchantId,true);
    mg_stage_f_assert($account['ready']===true&&$account['charges_enabled']===true&&$account['payouts_enabled']===true,'Stripe Connect account did not become ready.');
    $summary['connect_account_ready']=true;

    $draft=mg_checkout_fixture_draft($pdo,$fixture,'stripe');
    $pdo->prepare('UPDATE checkout_drafts SET platform_fee_cents=375,total_cents=2500 WHERE id=?')
        ->execute([(int)$draft['draft_id']]);
    $orderResult=mg_checkout_create_order($pdo,$buyerId,$draft['draft_public'],'stage-f:order:'.$runId);
    $orderPublic=(string)$orderResult['order']['order_id'];
    $orderId=(int)mg_stage_f_scalar($pdo,'SELECT id FROM commerce_orders WHERE public_id=?',[$orderPublic]);
    mg_stage_f_assert((int)mg_stage_f_scalar($pdo,'SELECT platform_fee_cents FROM commerce_orders WHERE id=?',[$orderId])===375,'Order platform fee snapshot is wrong.');
    mg_stage_f_assert((int)mg_stage_f_scalar($pdo,'SELECT calculated_fee_cents FROM order_fee_snapshots WHERE order_id=?',[$orderId])===375,'Fee snapshot row is wrong.');
    $summary['platform_fee_snapshotted']=true;

    $session=mg_payment_create_checkout_session($pdo,$buyerId,$orderPublic,'stage-f:session:'.$runId,[
        'success_url'=>'/checkout-success.php',
        'cancel_url'=>'/account/orders.php',
    ]);
    mg_stage_f_assert($session['provider']==='stripe','Checkout did not use Stripe.');
    mg_stage_f_assert(str_starts_with((string)$session['provider_session_reference'],'cs_test_stub_'),'Stripe session reference is missing.');
    mg_stage_f_assert(str_starts_with((string)$session['checkout_url'],'https://checkout.stripe.test/'),'Hosted Stripe Checkout URL is missing.');
    $summary['stripe_checkout_created']=true;

    $intentStmt=$pdo->prepare('SELECT * FROM payment_intents WHERE public_id=?');
    $intentStmt->execute([$session['payment_intent_id']]);
    $intent=$intentStmt->fetch(PDO::FETCH_ASSOC);
    mg_stage_f_assert((int)$intent['application_fee_cents']===375,'Stripe application fee is not bound to the intent.');
    mg_stage_f_assert(hash_equals((string)$account['account_id'],(string)$intent['destination_account_reference']),'Stripe destination account is wrong.');
    $summary['destination_and_fee_bound']=true;

    $event=[
        'id'=>'evt_stage_f_'.$runId,
        'type'=>'checkout.session.completed',
        'data'=>['object'=>[
            'id'=>$session['provider_session_reference'],
            'object'=>'checkout.session',
            'payment_status'=>'paid',
            'payment_intent'=>(string)$intent['provider_intent_reference'],
            'amount_total'=>2500,
            'currency'=>'usd',
            'metadata'=>[
                'order_id'=>$orderPublic,
                'payment_intent_id'=>$session['payment_intent_id'],
                'checkout_session_id'=>$session['checkout_session_id'],
            ],
        ]],
    ];
    $payload=json_encode($event,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $timestamp=time();
    $signature='t='.$timestamp.',v1='.hash_hmac('sha256',$timestamp.'.'.$payload,'whsec_stage_f');
    mg_stage_f_assert(mg_stripe_verify_signature($payload,$signature,'whsec_stage_f')===true,'Valid Stripe signature was rejected.');
    $summary['stripe_signature_verified']=true;

    $processed=mg_payment_process_webhook_event($pdo,'stripe',$event,$payload);
    mg_stage_f_assert($processed['processed']===true&&$processed['duplicate']===false,'Stripe payment webhook did not capture the order.');
    mg_stage_f_assert((string)mg_stage_f_scalar($pdo,'SELECT payment_status FROM commerce_orders WHERE id=?',[$orderId])==='paid','Order was not marked paid.');
    mg_stage_f_assert((string)mg_stage_f_scalar($pdo,'SELECT status FROM checkout_sessions WHERE public_id=?',[$session['checkout_session_id']])==='completed','Checkout session was not completed.');
    $summary['webhook_capture_completed']=true;

    $wallet=mg_wallet_resolve($pdo,'merchant',$merchantId,'USD');
    $balances=mg_wallet_balances($pdo,(int)$wallet['id']);
    mg_stage_f_assert((int)$balances['available_cents']===2125,'Merchant net proceeds are not 85% of the order.');
    $summary['merchant_net_correct']=true;

    $platformFee=(int)mg_stage_f_scalar($pdo,
        "SELECT COALESCE(SUM(CASE WHEN e.entry_type='credit' THEN e.amount_cents ELSE -e.amount_cents END),0)
         FROM ledger_entries e
         INNER JOIN ledger_accounts a ON a.id=e.ledger_account_id
         INNER JOIN ledger_transaction_groups g ON g.id=e.transaction_group_id
         WHERE g.source_type='commerce_order' AND g.source_reference=? AND a.account_code='platform_fee_revenue'",
        [$orderPublic]
    );
    mg_stage_f_assert($platformFee===375,'Platform fee revenue is not 15% of the order.');
    $summary['platform_revenue_correct']=true;

    $replay=mg_payment_process_webhook_event($pdo,'stripe',$event,$payload);
    mg_stage_f_assert($replay['duplicate']===true,'Stripe webhook replay was not idempotent.');
    mg_stage_f_assert((int)mg_stage_f_scalar($pdo,'SELECT COUNT(*) FROM payment_webhook_events WHERE provider_key=? AND provider_event_id=?',['stripe',$event['id']])===1,'Webhook replay duplicated the event.');
    mg_stage_f_assert((int)mg_stage_f_scalar($pdo,'SELECT COUNT(*) FROM payment_transactions WHERE payment_intent_id=?',[(int)$intent['id']])===1,'Webhook replay duplicated the payment transaction.');
    $summary['webhook_replay_safe']=true;

    mg_stage_f_assert((int)mg_stage_f_scalar($pdo,'SELECT COUNT(*) FROM microgift_instances mi INNER JOIN commerce_order_items oi ON oi.id=mi.commerce_order_item_id WHERE oi.order_id=?',[$orderId])===2,'Paid Stripe order did not issue exactly two Microgifts.');
    mg_stage_f_assert((int)mg_stage_f_scalar($pdo,'SELECT COUNT(*) FROM notifications WHERE user_id IN (?,?) AND type IN (?,?)',[$buyerId,$merchantId,'payment_succeeded','merchant_payment_received'])===2,'Stripe capture confirmations were not created once.');
    $summary['fulfillment_once']=true;

    mg_stage_f_assert(mg_stripe_verify_signature($payload,'t='.$timestamp.',v1=bad','whsec_stage_f')===false,'Invalid Stripe signature was accepted.');
    $summary['invalid_signature_rejected']=true;

    $pdo->rollBack();
    mg_stage_f_assert((int)mg_stage_f_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$buyerEmail,$merchantEmail])===0,'Stage F user fixtures remain.');
    mg_stage_f_assert((int)mg_stage_f_scalar($pdo,'SELECT COUNT(*) FROM payment_webhook_events WHERE provider_event_id=?',[$event['id']])===0,'Stage F webhook fixture remains.');
    $summary['fixtures_clean']=true;

    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
