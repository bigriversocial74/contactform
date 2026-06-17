<?php
declare(strict_types=1);

function mg_dispute_behavior_run_won(PDO $pdo,array $fixture,array $wallet,string $runId): array
{
    $order=mg_dispute_fixture_order($pdo,$fixture,'won');
    $requestId=(int)mg_dispute_behavior_scalar($pdo,'SELECT pppm_issuance_request_id FROM commerce_order_items WHERE id=?',[$order['line_id']]);
    $pppmBefore=(int)mg_dispute_behavior_scalar($pdo,'SELECT COUNT(*) FROM pppm_items WHERE issuance_request_id=?',[$requestId]);

    $open=mg_dispute_fixture_event('evt-open-won-'.$runId,'dispute.opened',$order,'dp-won-'.$runId,2500);
    $opened=mg_dispute_behavior_process($pdo,$open);
    mg_dispute_behavior_assert(($opened['result']['status']??null)==='needs_response','Dispute did not open.');
    $balances=mg_wallet_balances($pdo,(int)$wallet['id']);
    mg_dispute_behavior_assert((int)$balances['available_cents']===0&&(int)$balances['held_cents']===2500,'Dispute reserve balances are wrong.');
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,"SELECT COUNT(*) FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=? AND e.status='suspended'",[$order['order_id']])===2,'Full dispute did not suspend entitlements.');

    $replay=mg_dispute_behavior_process($pdo,$open);
    mg_dispute_behavior_assert($replay['duplicate']===true,'Open replay was not idempotent.');
    $changed=$open;$changed['data']['amount_cents']=2400;$conflict=false;
    try{mg_dispute_behavior_process($pdo,$changed);}catch(MgDisputeWorkflowException $error){$conflict=$error->httpStatus===409;}
    mg_dispute_behavior_assert($conflict,'Conflicting event replay was accepted.');

    $won=mg_dispute_fixture_event('evt-won-'.$runId,'dispute.won',$order,'dp-won-'.$runId,2500);
    mg_dispute_behavior_process($pdo,$won);
    $balances=mg_wallet_balances($pdo,(int)$wallet['id']);
    mg_dispute_behavior_assert((int)$balances['available_cents']===2500&&(int)$balances['held_cents']===0,'Won dispute did not restore balance.');
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,"SELECT COUNT(*) FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=? AND e.status='active'",[$order['order_id']])===2,'Won dispute did not restore entitlements.');
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,'SELECT COUNT(*) FROM pppm_items WHERE issuance_request_id=?',[$requestId])===$pppmBefore,'Dispute corrupted PPPM records.');

    return ['order'=>$order,'opened'=>true,'replay'=>true,'conflict'=>true,'won'=>true,'pppm'=>true];
}
