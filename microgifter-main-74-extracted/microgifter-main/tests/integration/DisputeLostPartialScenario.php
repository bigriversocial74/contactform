<?php
declare(strict_types=1);

function mg_dispute_behavior_run_lost(PDO $pdo,array $fixture,string $runId): array
{
    $order=mg_dispute_fixture_order($pdo,$fixture,'lost');
    $open=mg_dispute_fixture_event('evt-open-lost-'.$runId,'dispute.opened',$order,'dp-lost-'.$runId,2500);
    mg_dispute_behavior_process($pdo,$open);
    $lost=mg_dispute_fixture_event('evt-lost-'.$runId,'dispute.lost',$order,'dp-lost-'.$runId,2500,150);
    $result=mg_dispute_behavior_process($pdo,$lost);
    mg_dispute_behavior_assert(($result['result']['status']??null)==='lost','Lost dispute did not finalize.');
    mg_dispute_behavior_assert((string)mg_dispute_behavior_scalar($pdo,'SELECT payment_status FROM commerce_orders WHERE id=?',[$order['order_id']])==='refunded','Lost full dispute order state is wrong.');
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,"SELECT COUNT(*) FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=? AND e.status='revoked'",[$order['order_id']])===2,'Lost dispute did not revoke entitlements.');
    $groupId=(int)mg_dispute_behavior_scalar($pdo,'SELECT id FROM ledger_transaction_groups WHERE idempotency_key=?',['dispute:lost:'.$result['result']['dispute_id']]);
    mg_dispute_behavior_assert(mg_dispute_behavior_balanced($pdo,$groupId),'Lost dispute and fee group is not balanced.');
    return ['order'=>$order,'lost'=>true,'fee_balanced'=>true];
}

function mg_dispute_behavior_run_partial(PDO $pdo,array $fixture,string $runId): array
{
    $order=mg_dispute_fixture_order($pdo,$fixture,'partial');
    $open=mg_dispute_fixture_event('evt-partial-'.$runId,'dispute.opened',$order,'dp-partial-'.$runId,500);
    mg_dispute_behavior_process($pdo,$open);
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,"SELECT COUNT(*) FROM entitlement_review_items WHERE commerce_order_id=? AND review_type='dispute' AND status='open'",[$order['order_id']])===2,'Partial dispute review scopes are incomplete.');
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,"SELECT COUNT(*) FROM entitlement_review_items WHERE commerce_order_id=? AND review_type='dispute' AND status='open' AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.microgift_review_type'))='microgift_partial_dispute'",[$order['order_id']])===1,'Partial dispute Microgift review missing.');
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,"SELECT COUNT(*) FROM entitlement_review_items WHERE commerce_order_id=? AND review_type='dispute' AND status='open' AND JSON_EXTRACT(payload_json,'$.microgift_review_type') IS NULL",[$order['order_id']])===1,'Partial dispute entitlement review missing.');
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,"SELECT COUNT(*) FROM entitlements e INNER JOIN commerce_order_items oi ON oi.id=e.commerce_order_item_id WHERE oi.order_id=? AND e.status='active'",[$order['order_id']])===2,'Partial dispute changed entitlements automatically.');
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,"SELECT COUNT(*) FROM microgift_instances mi INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND mi.status='issued'",[$order['order_id']])===2,'Partial dispute changed Microgifts automatically.');
    mg_dispute_behavior_assert((int)mg_dispute_behavior_scalar($pdo,"SELECT COUNT(*) FROM pppm_items p INNER JOIN microgift_instances mi ON mi.pppm_item_id=p.id INNER JOIN commerce_order_items oi ON oi.public_id=mi.source_reference WHERE oi.order_id=? AND p.status='available'",[$order['order_id']])===2,'Partial dispute changed PPPM units automatically.');
    return ['order'=>$order,'partial_review'=>true];
}
