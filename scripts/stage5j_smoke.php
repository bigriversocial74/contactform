<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}
require_once dirname(__DIR__).'/api/db.php';
$pdo=mg_db();
$required=['carts','cart_items','checkout_drafts','order_fee_snapshots','order_status_history','receipts','order_audit_events'];
try{
    foreach($required as $table){$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$stmt->execute([$table]);if((int)$stmt->fetchColumn()!==1)throw new RuntimeException("Missing table: {$table}");}
    foreach(['api/commerce/cart.php','api/commerce/cart-items.php','api/commerce/cart-item.php','api/commerce/checkout-draft.php','api/commerce/orders.php','api/commerce/order.php','api/commerce/receipt.php','api/payments/order-checkout-session.php'] as $file){if(!is_file(dirname(__DIR__).'/'.$file))throw new RuntimeException("Missing endpoint: {$file}");}
    echo "Stage 5J smoke validation passed.\n";
}catch(Throwable $e){fwrite(STDERR,'FAILED: '.$e->getMessage()."\n");exit(1);}
