<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

function mg_admin_commerce_refund_detail(PDO $pdo,string $reference): array
{
    $e=mg_admin_commerce_one($pdo,<<<'SQL'
SELECT r.*,o.public_id order_public_id,t.public_id tip_public_id,
COALESCE(r.merchant_user_id,t.recipient_user_id) effective_merchant_user_id,COALESCE(o.buyer_user_id,t.sender_user_id) effective_customer_user_id,
COALESCE(mu.display_name,mu.full_name,mu.email) merchant_name,mu.email merchant_email,COALESCE(cu.display_name,cu.full_name,cu.email) customer_name,cu.email customer_email
FROM payment_refunds r LEFT JOIN commerce_orders o ON o.id=r.order_id LEFT JOIN tips t ON t.id=r.tip_id
LEFT JOIN users mu ON mu.id=COALESCE(r.merchant_user_id,t.recipient_user_id) LEFT JOIN users cu ON cu.id=COALESCE(o.buyer_user_id,t.sender_user_id)
WHERE r.public_id=? LIMIT 1
SQL,[$reference]);
    if(!$e)throw new MgAdminCommerceException('Refund not found.',404);
    $ledger=$e['ledger_group_id']!==null?mg_admin_commerce_all($pdo,'SELECT g.public_id,g.transaction_type,g.status,g.description,g.posted_at,e.entry_type,e.amount_cents,e.currency,e.description entry_description FROM ledger_transaction_groups g INNER JOIN ledger_entries e ON e.transaction_group_id=g.id WHERE g.id=? ORDER BY e.id LIMIT 100',[(int)$e['ledger_group_id']]):[];
    $timeline=[mg_admin_commerce_timeline_item((string)$e['created_at'],'refund.created','Refund requested',(string)$e['status'],(string)$e['reason'],'refund')];
    if($e['processed_at']!==null)$timeline[]=mg_admin_commerce_timeline_item((string)$e['processed_at'],'refund.processed','Refund processed',(string)$e['status'],$e['failure_message']!==null?(string)$e['failure_message']:null,'refund');
    foreach($ledger as $r)$timeline[]=mg_admin_commerce_timeline_item((string)($r['posted_at']??$e['updated_at']),'ledger.'.(string)$r['entry_type'],'Ledger '.(string)$r['entry_type'],(string)$r['status'],(string)$r['entry_description'],'ledger');
    mg_admin_commerce_timeline_sort($timeline);
    return ['entity'=>['type'=>'refund','public_id'=>(string)$e['public_id'],'status'=>(string)$e['status'],'secondary_status'=>(string)$e['reason'],'title'=>'Refund '.substr((string)$e['public_id'],0,8),'amount_cents'=>(int)$e['amount_cents'],'currency'=>(string)$e['currency'],'merchant'=>$e['effective_merchant_user_id']!==null?['id'=>(int)$e['effective_merchant_user_id'],'display_name'=>(string)$e['merchant_name'],'email'=>(string)$e['merchant_email']]:null,'customer'=>$e['effective_customer_user_id']!==null?['id'=>(int)$e['effective_customer_user_id'],'display_name'=>(string)$e['customer_name'],'email'=>(string)$e['customer_email']]:null,'created_at'=>(string)$e['created_at'],'updated_at'=>(string)$e['updated_at']],
    'facts'=>[mg_admin_commerce_fact('Order',$e['order_public_id']),mg_admin_commerce_fact('Tip',$e['tip_public_id']),mg_admin_commerce_fact('Source',(string)($e['source_type']??'order')),mg_admin_commerce_fact('Source reference',$e['source_reference']),mg_admin_commerce_fact('Provider refund',$e['provider_refund_reference']),mg_admin_commerce_fact('Failure',$e['failure_message']),mg_admin_commerce_fact('Processed at',$e['processed_at'],'date')],'related'=>['ledger'=>$ledger],'timeline'=>$timeline];
}

function mg_admin_commerce_dispute_detail(PDO $pdo,string $reference): array
{
    $e=mg_admin_commerce_one($pdo,<<<'SQL'
SELECT d.*,o.public_id order_public_id,t.public_id tip_public_id,
COALESCE(d.merchant_user_id,t.recipient_user_id) effective_merchant_user_id,COALESCE(o.buyer_user_id,t.sender_user_id) effective_customer_user_id,
COALESCE(mu.display_name,mu.full_name,mu.email) merchant_name,mu.email merchant_email,COALESCE(cu.display_name,cu.full_name,cu.email) customer_name,cu.email customer_email
FROM payment_disputes d LEFT JOIN commerce_orders o ON o.id=d.order_id LEFT JOIN tips t ON t.id=d.tip_id
LEFT JOIN users mu ON mu.id=COALESCE(d.merchant_user_id,t.recipient_user_id) LEFT JOIN users cu ON cu.id=COALESCE(o.buyer_user_id,t.sender_user_id)
WHERE d.public_id=? LIMIT 1
SQL,[$reference]);
    if(!$e)throw new MgAdminCommerceException('Dispute not found.',404);
    $timeline=[mg_admin_commerce_timeline_item((string)$e['created_at'],'dispute.created','Dispute opened',(string)$e['status'],$e['reason']!==null?(string)$e['reason']:null,'dispute')];
    if($e['response_due_at']!==null)$timeline[]=mg_admin_commerce_timeline_item((string)$e['response_due_at'],'dispute.response_due','Dispute response due',(string)$e['status'],null,'provider');
    if($e['resolved_at']!==null)$timeline[]=mg_admin_commerce_timeline_item((string)$e['resolved_at'],'dispute.resolved','Dispute resolved',(string)$e['status'],null,'provider');
    mg_admin_commerce_timeline_sort($timeline);
    return ['entity'=>['type'=>'dispute','public_id'=>(string)$e['public_id'],'status'=>(string)$e['status'],'secondary_status'=>$e['reason'],'title'=>'Dispute '.substr((string)$e['public_id'],0,8),'amount_cents'=>(int)$e['amount_cents'],'currency'=>(string)$e['currency'],'merchant'=>$e['effective_merchant_user_id']!==null?['id'=>(int)$e['effective_merchant_user_id'],'display_name'=>(string)$e['merchant_name'],'email'=>(string)$e['merchant_email']]:null,'customer'=>$e['effective_customer_user_id']!==null?['id'=>(int)$e['effective_customer_user_id'],'display_name'=>(string)$e['customer_name'],'email'=>(string)$e['customer_email']]:null,'created_at'=>(string)$e['created_at'],'updated_at'=>(string)$e['updated_at']],
    'facts'=>[mg_admin_commerce_fact('Order',$e['order_public_id']),mg_admin_commerce_fact('Tip',$e['tip_public_id']),mg_admin_commerce_fact('Source',(string)($e['source_type']??'order')),mg_admin_commerce_fact('Source reference',$e['source_reference']),mg_admin_commerce_fact('Provider dispute',$e['provider_dispute_reference']),mg_admin_commerce_fact('Response due',$e['response_due_at'],'date'),mg_admin_commerce_fact('Resolved at',$e['resolved_at'],'date')],'related'=>[],'timeline'=>$timeline];
}

function mg_admin_commerce_tip_detail(PDO $pdo,string $reference): array
{
    $e=mg_admin_commerce_one($pdo,<<<'SQL'
SELECT t.*,COALESCE(mu.display_name,mu.full_name,mu.email) merchant_name,mu.email merchant_email,COALESCE(cu.display_name,cu.full_name,cu.email) customer_name,cu.email customer_email
FROM tips t INNER JOIN users mu ON mu.id=t.recipient_user_id INNER JOIN users cu ON cu.id=t.sender_user_id WHERE t.public_id=? LIMIT 1
SQL,[$reference]);
    if(!$e)throw new MgAdminCommerceException('Tip not found.',404);
    $id=(int)$e['id'];
    $events=mg_admin_commerce_all($pdo,'SELECT event_type,actor_user_id,source_type,source_reference,created_at FROM tip_events WHERE tip_id=? ORDER BY created_at DESC,id DESC LIMIT 100',[$id]);
    $reversal=mg_admin_commerce_one($pdo,'SELECT public_id,amount_cents,currency,reason,reversed_by_user_id,created_at FROM tip_reversals WHERE tip_id=? LIMIT 1',[$id]);
    $recoveries=mg_admin_commerce_all($pdo,'SELECT public_id,recovery_type,provider_reference,amount_cents,net_cents,fee_cents,currency,status,processed_at,created_at,updated_at FROM tip_payment_recoveries WHERE tip_id=? ORDER BY created_at DESC,id DESC LIMIT 100',[$id]);
    $ledger=$e['ledger_group_id']!==null?mg_admin_commerce_all($pdo,'SELECT g.public_id,g.transaction_type,g.status,g.description,g.posted_at,e.entry_type,e.amount_cents,e.currency,e.description entry_description FROM ledger_transaction_groups g INNER JOIN ledger_entries e ON e.transaction_group_id=g.id WHERE g.id=? ORDER BY e.id LIMIT 100',[(int)$e['ledger_group_id']]):[];
    $timeline=[mg_admin_commerce_timeline_item((string)$e['created_at'],'tip.created','Tip created',(string)$e['status'],(string)$e['target_type'],'tip')];
    foreach($events as $r)$timeline[]=mg_admin_commerce_timeline_item((string)$r['created_at'],'tip.'.(string)$r['event_type'],str_replace(['.','_'],' ',(string)$r['event_type']),null,$r['source_reference']!==null?(string)$r['source_reference']:null,(string)$r['source_type']);
    foreach($recoveries as $r)$timeline[]=mg_admin_commerce_timeline_item((string)($r['processed_at']??$r['created_at']),'tip.recovery.'.(string)$r['recovery_type'],'Recovery '.str_replace('_',' ',(string)$r['recovery_type']),(string)$r['status'],null,'recovery');
    if($reversal)$timeline[]=mg_admin_commerce_timeline_item((string)$reversal['created_at'],'tip.reversed','Tip reversed','reversed',(string)$reversal['reason'],'admin');
    mg_admin_commerce_timeline_sort($timeline);
    return ['entity'=>['type'=>'tip','public_id'=>(string)$e['public_id'],'status'=>(string)$e['status'],'secondary_status'=>(string)$e['target_type'],'title'=>'Tip to '.(string)$e['merchant_name'],'amount_cents'=>(int)$e['amount_cents'],'currency'=>(string)$e['currency'],'merchant'=>['id'=>(int)$e['recipient_user_id'],'display_name'=>(string)$e['merchant_name'],'email'=>(string)$e['merchant_email']],'customer'=>['id'=>(int)$e['sender_user_id'],'display_name'=>(string)$e['customer_name'],'email'=>(string)$e['customer_email']],'created_at'=>(string)$e['created_at'],'updated_at'=>(string)$e['updated_at']],
    'facts'=>[mg_admin_commerce_fact('Target',(string)$e['target_type']),mg_admin_commerce_fact('Target reference',(string)$e['target_reference']),mg_admin_commerce_fact('Funding',(string)$e['funding_type']),mg_admin_commerce_fact('Fee',(int)$e['fee_cents'],'money'),mg_admin_commerce_fact('Net',(int)$e['net_cents'],'money'),mg_admin_commerce_fact('Provider payment',$e['provider_payment_id']),mg_admin_commerce_fact('Posted at',$e['posted_at'],'date'),mg_admin_commerce_fact('Reversed at',$e['reversed_at'],'date')],'related'=>compact('reversal','recoveries','ledger'),'timeline'=>$timeline];
}
