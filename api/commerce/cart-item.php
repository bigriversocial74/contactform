<?php
declare(strict_types=1);
require_once __DIR__ . '/_foundation.php';
$user=mg_require_api_user();
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
if(!in_array($method,['PATCH','DELETE'],true))mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);
$itemId=trim((string)($input['item_id']??$_GET['item_id']??''));
if($itemId==='')mg_fail('Cart item is required.',422);
$pdo=mg_db();$pdo->beginTransaction();
try{
    $cart=mg_cart_active($pdo,(int)$user['id'],true);
    $stmt=$pdo->prepare('SELECT id FROM cart_items WHERE public_id=? AND cart_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$itemId,(int)$cart['id']]);$dbId=$stmt->fetchColumn();if(!$dbId)mg_fail('Cart item not found.',404);
    if($method==='DELETE'){$pdo->prepare('DELETE FROM cart_items WHERE id=?')->execute([(int)$dbId]);}
    else{$quantity=(int)($input['quantity']??0);if($quantity<1||$quantity>100)mg_fail('Quantity must be between 1 and 100.',422);$pdo->prepare('UPDATE cart_items SET quantity=?,line_total_cents=unit_amount_cents*?,updated_at=NOW() WHERE id=?')->execute([$quantity,$quantity,(int)$dbId]);}
    mg_cart_recalculate($pdo,(int)$cart['id']);$pdo->commit();
    mg_audit($method==='DELETE'?'commerce.cart_item_removed':'commerce.cart_item_updated','cart',['cart_id'=>$cart['public_id'],'item_id'=>$itemId],(int)$user['id']);
    $fresh=mg_cart_active($pdo,(int)$user['id']);mg_ok(mg_cart_payload($pdo,$fresh),$method==='DELETE'?'Cart item removed.':'Cart item updated.');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to update cart.',500);}
