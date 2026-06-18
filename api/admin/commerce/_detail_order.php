<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

function mg_admin_commerce_order_detail(PDO $pdo,string $reference): array
{
    $entity=mg_admin_commerce_one($pdo,<<<'SQL'
SELECT o.*,COALESCE(mu.display_name,mu.full_name,mu.email) merchant_name,mu.email merchant_email,COALESCE(cu.display_name,cu.full_name,cu.email) customer_name,cu.email customer_email
FROM commerce_orders o INNER JOIN users mu ON mu.id=o.merchant_user_id INNER JOIN users cu ON cu.id=o.buyer_user_id WHERE o.public_id=? LIMIT 1
SQL,[$reference]);
    if(!$entity)throw new MgAdminCommerceException('Order not found.',404);
    $id=(int)$entity['id'];
    $items=mg_admin_commerce_all($pdo,'SELECT public_id,title_snapshot,quantity,unit_amount_cents,discount_cents,tax_cents,line_total_cents,currency,product_id,product_version_id,pppm_issuance_request_id,created_at FROM commerce_order_items WHERE order_id=? ORDER BY id LIMIT 100',[$id]);
    $intents=mg_admin_commerce_all($pdo,'SELECT public_id,provider_key,provider_intent_reference,amount_cents,currency,status,capture_method,failure_code,failure_message,authorized_at,captured_at,created_at,updated_at FROM payment_intents WHERE order_id=? ORDER BY created_at DESC,id DESC LIMIT 25',[$id]);
    $transactions=mg_admin_commerce_all($pdo,'SELECT t.public_id,t.transaction_type,t.provider_reference,t.amount_cents,t.currency,t.status,t.occurred_at,t.created_at FROM payment_transactions t INNER JOIN payment_intents i ON i.id=t.payment_intent_id WHERE i.order_id=? ORDER BY t.occurred_at DESC,t.id DESC LIMIT 100',[$id]);
    $refunds=mg_admin_commerce_all($pdo,'SELECT public_id,amount_cents,currency,reason,status,provider_refund_reference,failure_message,processed_at,created_at,updated_at FROM payment_refunds WHERE order_id=? ORDER BY created_at DESC,id DESC LIMIT 50',[$id]);
    $disputes=mg_admin_commerce_all($pdo,'SELECT public_id,amount_cents,currency,reason,status,provider_dispute_reference,response_due_at,resolved_at,created_at,updated_at FROM payment_disputes WHERE order_id=? ORDER BY created_at DESC,id DESC LIMIT 50',[$id]);
    $statusHistory=mg_admin_commerce_all($pdo,'SELECT status_domain,from_status,to_status,actor_type,actor_user_id,reason_code,created_at FROM order_status_history WHERE order_id=? ORDER BY created_at DESC,id DESC LIMIT 100',[$id]);
    $audits=mg_admin_commerce_all($pdo,'SELECT event_type,actor_user_id,created_at FROM order_audit_events WHERE order_id=? ORDER BY created_at DESC,id DESC LIMIT 100',[$id]);
    $receipt=mg_admin_commerce_one($pdo,'SELECT public_id,receipt_number,status,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,finalized_at,created_at,updated_at FROM receipts WHERE order_id=? LIMIT 1',[$id]);
    $ledger=mg_admin_commerce_all($pdo,<<<'SQL'
SELECT g.public_id,g.transaction_type,g.source_type,g.source_reference,g.currency,g.status,g.description,g.posted_at,g.created_at,
SUM(CASE WHEN e.entry_type='debit' THEN e.amount_cents ELSE 0 END) debit_cents,SUM(CASE WHEN e.entry_type='credit' THEN e.amount_cents ELSE 0 END) credit_cents
FROM ledger_transaction_groups g INNER JOIN ledger_entries e ON e.transaction_group_id=g.id
WHERE (g.source_type='commerce_order' AND g.source_reference=?) OR (g.source_type='payment_refund' AND g.source_reference IN (SELECT public_id FROM payment_refunds WHERE order_id=?))
GROUP BY g.id ORDER BY g.created_at DESC,g.id DESC LIMIT 50
SQL,[$reference,$id]);
    $microgifts=mg_admin_commerce_all($pdo,'SELECT m.public_id,m.status,m.title_snapshot,m.face_value_cents,m.currency,m.issued_at,m.delivered_at,m.claimed_at,m.redeemed_at,m.expires_at FROM microgift_instances m INNER JOIN commerce_order_items oi ON oi.id=m.commerce_order_item_id WHERE oi.order_id=? ORDER BY m.created_at DESC,m.id DESC LIMIT 100',[$id]);
    $timeline=[mg_admin_commerce_timeline_item((string)$entity['created_at'],'order.created','Order created',(string)$entity['payment_status'],(string)$entity['source_type'],'order')];
    if($entity['paid_at']!==null)$timeline[]=mg_admin_commerce_timeline_item((string)$entity['paid_at'],'order.paid','Payment completed','paid',null,'order');
    if($entity['cancelled_at']!==null)$timeline[]=mg_admin_commerce_timeline_item((string)$entity['cancelled_at'],'order.cancelled','Order cancelled','cancelled',null,'order');
    foreach($statusHistory as $r)$timeline[]=mg_admin_commerce_timeline_item((string)$r['created_at'],'order.status',ucfirst((string)$r['status_domain']).' status changed',(string)$r['to_status'],$r['reason_code']!==null?(string)$r['reason_code']:null,(string)$r['actor_type']);
    foreach($transactions as $r)$timeline[]=mg_admin_commerce_timeline_item((string)$r['occurred_at'],'payment.'.(string)$r['transaction_type'],ucfirst((string)$r['transaction_type']).' transaction',(string)$r['status'],null,'payment');
    foreach($refunds as $r)$timeline[]=mg_admin_commerce_timeline_item((string)($r['processed_at']??$r['created_at']),'refund.'.(string)$r['status'],'Refund '.(string)$r['status'],(string)$r['status'],(string)$r['reason'],'refund');
    foreach($disputes as $r)$timeline[]=mg_admin_commerce_timeline_item((string)($r['resolved_at']??$r['created_at']),'dispute.'.(string)$r['status'],'Dispute '.str_replace('_',' ',(string)$r['status']),(string)$r['status'],$r['reason']!==null?(string)$r['reason']:null,'dispute');
    foreach($audits as $r)$timeline[]=mg_admin_commerce_timeline_item((string)$r['created_at'],(string)$r['event_type'],str_replace(['.','_'],' ',(string)$r['event_type']),null,null,'audit');
    foreach($microgifts as $r){$timeline[]=mg_admin_commerce_timeline_item((string)$r['issued_at'],'microgift.issued','Microgift issued: '.(string)$r['title_snapshot'],(string)$r['status'],null,'microgift');if($r['redeemed_at']!==null)$timeline[]=mg_admin_commerce_timeline_item((string)$r['redeemed_at'],'microgift.redeemed','Microgift redeemed: '.(string)$r['title_snapshot'],'redeemed',null,'microgift');}
    mg_admin_commerce_timeline_sort($timeline);
    return [
        'entity'=>['type'=>'order','public_id'=>(string)$entity['public_id'],'status'=>(string)$entity['payment_status'],'secondary_status'=>(string)$entity['fulfillment_status'],'title'=>'Order '.substr((string)$entity['public_id'],0,8),'amount_cents'=>(int)$entity['total_cents'],'currency'=>(string)$entity['currency'],'merchant'=>['id'=>(int)$entity['merchant_user_id'],'display_name'=>(string)$entity['merchant_name'],'email'=>(string)$entity['merchant_email']],'customer'=>['id'=>(int)$entity['buyer_user_id'],'display_name'=>(string)$entity['customer_name'],'email'=>(string)$entity['customer_email']],'created_at'=>(string)$entity['created_at'],'updated_at'=>(string)$entity['updated_at']],
        'facts'=>[mg_admin_commerce_fact('Payment status',(string)$entity['payment_status'],'status'),mg_admin_commerce_fact('Fulfillment',(string)$entity['fulfillment_status'],'status'),mg_admin_commerce_fact('Subtotal',(int)$entity['subtotal_cents'],'money'),mg_admin_commerce_fact('Discount',(int)$entity['discount_cents'],'money'),mg_admin_commerce_fact('Tax',(int)$entity['tax_cents'],'money'),mg_admin_commerce_fact('Platform fee',(int)$entity['platform_fee_cents'],'money'),mg_admin_commerce_fact('Source',(string)$entity['source_type']),mg_admin_commerce_fact('Paid at',$entity['paid_at'],'date')],
        'related'=>compact('items','intents','transactions','refunds','disputes','receipt','ledger','microgifts'),'timeline'=>$timeline,
    ];
}
