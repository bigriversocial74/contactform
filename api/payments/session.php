<?php
declare(strict_types=1);

require_once __DIR__ . '/_payments.php';

mg_require_method('GET');
$user=mg_require_permission('commerce.checkout.create');
$id=trim((string)($_GET['id']??''));
if($id==='')mg_fail('Checkout session is required.',422);

$pdo=mg_db();
$stmt=$pdo->prepare(
    "SELECT cs.public_id session_id,cs.status session_status,cs.provider_key,cs.expires_at,
            pi.public_id payment_intent_id,pi.status payment_intent_status,
            o.public_id order_id,o.currency,o.subtotal_cents,o.tax_cents,o.discount_cents,
            o.platform_fee_cents,o.total_cents,o.payment_status,o.fulfillment_status,
            o.merchant_user_id,mw.display_name merchant_name
     FROM checkout_sessions cs
     INNER JOIN payment_intents pi ON pi.id=cs.payment_intent_id AND pi.order_id=cs.order_id
     INNER JOIN commerce_orders o ON o.id=cs.order_id
     LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=o.merchant_user_id
     WHERE cs.public_id=? AND o.buyer_user_id=?
     LIMIT 1"
);
$stmt->execute([$id,(int)$user['id']]);
$session=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$session)mg_fail('Checkout session not found.',404);

$expired=!empty($session['expires_at'])&&strtotime((string)$session['expires_at'])<=time();
if($expired&&in_array((string)$session['session_status'],['created','open'],true)){
    $session['session_status']='expired';
}
$session['can_confirm']=(
    $session['provider_key']==='sandbox'
    && in_array((string)$session['session_status'],['created','open'],true)
    && (string)$session['payment_status']==='unpaid'
    && !in_array((string)$session['payment_intent_status'],['failed','cancelled','succeeded'],true)
);

$items=$pdo->prepare(
    'SELECT title_snapshot,quantity,unit_amount_cents,line_total_cents,currency
     FROM commerce_order_items coi
     INNER JOIN commerce_orders o ON o.id=coi.order_id
     WHERE o.public_id=? AND o.buyer_user_id=?
     ORDER BY coi.id'
);
$items->execute([$session['order_id'],(int)$user['id']]);
mg_ok(['session'=>$session,'items'=>$items->fetchAll(PDO::FETCH_ASSOC)]);
