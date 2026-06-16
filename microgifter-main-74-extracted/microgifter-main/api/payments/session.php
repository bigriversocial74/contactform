<?php
declare(strict_types=1);
require_once __DIR__ . '/_payments.php';
mg_require_method('GET');
$user=mg_require_permission('commerce.checkout.create');$id=trim((string)($_GET['id']??''));
$stmt=mg_db()->prepare('SELECT cs.public_id session_id,cs.status session_status,cs.provider_key,cs.expires_at,o.public_id order_id,o.currency,o.subtotal_cents,o.tax_cents,o.discount_cents,o.total_cents,o.payment_status,o.merchant_user_id,mw.display_name merchant_name FROM checkout_sessions cs INNER JOIN commerce_orders o ON o.id=cs.order_id LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=o.merchant_user_id WHERE cs.public_id=? AND o.buyer_user_id=? LIMIT 1');$stmt->execute([$id,(int)$user['id']]);$session=$stmt->fetch();if(!$session)mg_fail('Checkout session not found.',404);$items=mg_db()->prepare('SELECT title_snapshot,quantity,unit_amount_cents,line_total_cents,currency FROM commerce_order_items coi INNER JOIN commerce_orders o ON o.id=coi.order_id WHERE o.public_id=? ORDER BY coi.id');$items->execute([$session['order_id']]);mg_ok(['session'=>$session,'items'=>$items->fetchAll()]);
