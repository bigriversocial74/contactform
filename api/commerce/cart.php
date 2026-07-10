<?php
declare(strict_types=1);
require_once __DIR__ . '/_foundation.php';
$user=mg_require_api_user();
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$pdo=mg_db();
if($method==='GET'){
    $cart=mg_cart_active($pdo,(int)$user['id'],false,false);
    mg_ok(mg_cart_payload($pdo,$cart));
}
if($method==='DELETE'){
    $input=mg_input();mg_require_csrf_for_write($input);
    $pdo->beginTransaction();
    try{
        $cart=mg_cart_active($pdo,(int)$user['id'],true,false);
        if(!$cart){$pdo->commit();mg_ok(['cart_id'=>'','status'=>'empty','items'=>[],'totals'=>mg_cart_empty_totals()],'Cart is already empty.');}
        $pdo->prepare('DELETE FROM cart_items WHERE cart_id=?')->execute([(int)$cart['id']]);
        mg_cart_recalculate($pdo,(int)$cart['id']);
        $pdo->commit();
        mg_audit('commerce.cart_cleared','cart',['cart_id'=>$cart['public_id']],(int)$user['id']);
        mg_ok(['cart_id'=>$cart['public_id']],'Cart cleared.');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to clear cart.',500);}
}
mg_fail('Method not allowed.',405);
