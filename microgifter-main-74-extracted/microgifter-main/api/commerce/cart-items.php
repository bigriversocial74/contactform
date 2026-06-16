<?php
declare(strict_types=1);
require_once __DIR__ . '/_foundation.php';
mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();mg_require_csrf_for_write($input);
$versionId=trim((string)($input['product_version_id']??''));
$quantity=max(1,min(100,(int)($input['quantity']??1)));
if($versionId==='')mg_fail('Product version is required.',422);
$pdo=mg_db();$pdo->beginTransaction();
try{
    $cart=mg_cart_active($pdo,(int)$user['id'],true);
    $item=mg_resolve_published_product_version($pdo,$versionId);
    $existing=$pdo->prepare('SELECT id,quantity FROM cart_items WHERE cart_id=? AND product_version_id=? LIMIT 1 FOR UPDATE');
    $existing->execute([(int)$cart['id'],(int)$item['version_db_id']]);
    $row=$existing->fetch();
    $currencyCheck=$pdo->prepare('SELECT COUNT(*) FROM cart_items WHERE cart_id=? AND currency<>?');$currencyCheck->execute([(int)$cart['id'],$item['currency']]);if((int)$currencyCheck->fetchColumn()>0)mg_fail('Cart items must use one currency.',409);
    $merchantCheck=$pdo->prepare('SELECT COUNT(*) FROM cart_items WHERE cart_id=? AND merchant_user_id<>?');$merchantCheck->execute([(int)$cart['id'],(int)$item['merchant_user_id']]);if((int)$merchantCheck->fetchColumn()>0)mg_fail('Cart currently supports one merchant.',409);
    if($row){$newQuantity=min(100,(int)$row['quantity']+$quantity);$pdo->prepare('UPDATE cart_items SET quantity=?,line_total_cents=unit_amount_cents*?,updated_at=NOW() WHERE id=?')->execute([$newQuantity,$newQuantity,(int)$row['id']]);}
    else{$pdo->prepare('INSERT INTO cart_items (public_id,cart_id,product_id,product_version_id,merchant_user_id,title_snapshot,unit_amount_cents,currency,quantity,line_total_cents,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([mg_public_uuid(),(int)$cart['id'],(int)$item['product_db_id'],(int)$item['version_db_id'],(int)$item['merchant_user_id'],$item['title'],(int)$item['unit_value_cents'],$item['currency'],$quantity,(int)$item['unit_value_cents']*$quantity]);}
    mg_cart_recalculate($pdo,(int)$cart['id']);$pdo->commit();
    mg_audit('commerce.cart_item_added','cart',['cart_id'=>$cart['public_id'],'product_version_id'=>$versionId,'quantity'=>$quantity],(int)$user['id']);
    $fresh=mg_cart_active($pdo,(int)$user['id']);mg_ok(mg_cart_payload($pdo,$fresh),'Cart updated.',201);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','commerce.cart_item_failed','Cart item update failed.',['exception_type'=>get_class($e)],(int)$user['id']);mg_fail('Unable to update cart.',500);}
