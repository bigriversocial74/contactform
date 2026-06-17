<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/commerce/_checkout.php';
require_once dirname(__DIR__).'/api/payments/_checkout_session.php';
require_once dirname(__DIR__).'/api/account/_action_center.php';
require_once dirname(__DIR__).'/tests/integration/CheckoutBehaviorFixture.php';

function mg_upgraded_checkout_assert(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
function mg_upgraded_checkout_scalar(PDO $pdo,string $sql,array $params=[]): mixed {$stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();}

function mg_upgraded_checkout_apply_microgift_migration(PDO $pdo): void
{
    $migration=dirname(__DIR__).'/database/stage_3_commerce_microgift_fulfillment.sql';
    $sql=file_get_contents($migration);
    if(!is_string($sql)||trim($sql)==='')throw new RuntimeException('Commerce Microgift fulfillment migration is missing.');
    $pdo->exec($sql);
}

$pdo=mg_db();$runId='upgraded_checkout_'.bin2hex(random_bytes(8));
$summary=[
    'suite'=>'upgraded_checkout_microgift_action_center_behavior',
    'run_id'=>$runId,
    'migration_applied'=>false,
    'checkout_created'=>false,
    'capture_completed'=>false,
    'microgifts_issued'=>false,
    'recipient_action_center_visible'=>false,
    'merchant_action_center_sent_visible'=>false,
    'replay_idempotent'=>false,
    'fixtures_clean'=>false,
];

try{
    mg_upgraded_checkout_apply_microgift_migration($pdo);
    mg_upgraded_checkout_assert((int)mg_upgraded_checkout_scalar($pdo,"SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='commerce_order_items' AND COLUMN_NAME='merchant_user_id'")===1,'merchant_user_id column was not applied.');
    $summary['migration_applied']=true;

    $pdo->beginTransaction();
    $buyerEmail=$runId.'-buyer@example.test';$merchantEmail=$runId.'-merchant@example.test';
    $buyerId=mg_it_user($pdo,$buyerEmail,'Upgraded Checkout Buyer');$merchantId=mg_it_user($pdo,$merchantEmail,'Upgraded Checkout Merchant');
    $catalog=mg_checkout_fixture_catalog($pdo,$merchantId,$runId);
    $fixture=['run_id'=>$runId,'buyer_id'=>$buyerId,'merchant_id'=>$merchantId]+$catalog;

    $draft=mg_checkout_fixture_draft($pdo,$fixture,'success');
    $orderResult=mg_checkout_create_order($pdo,$buyerId,$draft['draft_public'],'upgraded-checkout-order-'.$runId);
    mg_upgraded_checkout_assert($orderResult['duplicate']===false,'Checkout order was not created.');
    $orderPublic=(string)$orderResult['order']['order_id'];
    $orderId=(int)mg_upgraded_checkout_scalar($pdo,'SELECT id FROM commerce_orders WHERE public_id=?',[$orderPublic]);
    mg_upgraded_checkout_assert($orderId>0,'Order was not persisted.');
    mg_upgraded_checkout_assert((int)mg_upgraded_checkout_scalar($pdo,'SELECT COUNT(*) FROM commerce_order_items WHERE order_id=? AND merchant_user_id=?',[$orderId,$merchantId])===1,'Order item merchant ownership was not persisted.');
    $summary['checkout_created']=true;

    $session=mg_payment_create_checkout_session($pdo,$buyerId,$orderPublic,'upgraded-checkout-session-'.$runId);
    $intentId=(int)mg_upgraded_checkout_scalar($pdo,'SELECT id FROM payment_intents WHERE public_id=?',[$session['payment_intent_id']]);
    $providerReference='upgraded-provider-'.$runId;
    $capture=mg_finance_record_paid_order($pdo,$orderId,$intentId,$providerReference,$buyerId);
    mg_upgraded_checkout_assert($capture['payment_transitioned']===true,'Capture did not transition payment.');
    mg_upgraded_checkout_assert((int)($capture['microgift_issued_count']??0)===2,'Capture did not issue two Microgifts.');
    $summary['capture_completed']=true;

    $linePublic=(string)mg_upgraded_checkout_scalar($pdo,'SELECT public_id FROM commerce_order_items WHERE order_id=? LIMIT 1',[$orderId]);
    mg_upgraded_checkout_assert($linePublic!=='','Order item public ID missing.');
    $instanceCount=(int)mg_upgraded_checkout_scalar($pdo,"SELECT COUNT(*) FROM microgift_instances WHERE source_type='commerce_order_item' AND source_reference=? AND issuer_user_id=? AND recipient_user_id=?",[$linePublic,$merchantId,$buyerId]);
    mg_upgraded_checkout_assert($instanceCount===2,'Expected two Microgift instances from the paid order item.');
    $summary['microgifts_issued']=true;

    $recipientItems=mg_action_center_items($pdo,$buyerId,'inbox',10);
    $recipientCheckoutItems=array_values(array_filter($recipientItems,static fn(array $item): bool => (string)($item['state']??'')==='claimable' && (string)($item['sender_id']??'')!==''));
    mg_upgraded_checkout_assert(count($recipientCheckoutItems)>=2,'Recipient Action Center inbox does not show issued checkout Microgifts.');
    $summary['recipient_action_center_visible']=true;

    $sentItems=mg_action_center_items($pdo,$merchantId,'sent',10);
    $sentCheckoutItems=array_values(array_filter($sentItems,static fn(array $item): bool => (string)($item['state']??'')==='claimable' && (string)($item['recipient_id']??'')!==''));
    mg_upgraded_checkout_assert(count($sentCheckoutItems)>=2,'Merchant Action Center sent folder does not show issued checkout Microgifts.');
    $summary['merchant_action_center_sent_visible']=true;

    $replay=mg_finance_record_paid_order($pdo,$orderId,$intentId,$providerReference,$buyerId);
    mg_upgraded_checkout_assert($replay['payment_transitioned']===false,'Capture replay transitioned twice.');
    mg_upgraded_checkout_assert((int)($replay['microgift_issued_count']??0)===0,'Capture replay issued duplicate Microgifts.');
    mg_upgraded_checkout_assert((int)mg_upgraded_checkout_scalar($pdo,"SELECT COUNT(*) FROM microgift_instances WHERE source_type='commerce_order_item' AND source_reference=?",[$linePublic])===2,'Replay changed Microgift instance count.');
    $summary['replay_idempotent']=true;

    $pdo->rollBack();
    mg_upgraded_checkout_assert((int)mg_upgraded_checkout_scalar($pdo,'SELECT COUNT(*) FROM users WHERE email IN (?,?)',[$buyerEmail,$merchantEmail])===0,'Upgraded checkout users remain.');
    mg_upgraded_checkout_assert((int)mg_upgraded_checkout_scalar($pdo,'SELECT COUNT(*) FROM commerce_orders WHERE idempotency_key LIKE ?',['%'.$runId])===0,'Upgraded checkout orders remain.');
    $summary['fixtures_clean']=true;
    fwrite(STDOUT,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    $summary['error']=$error->getMessage();
    fwrite(STDERR,json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
    throw $error;
}
