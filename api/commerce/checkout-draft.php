<?php
declare(strict_types=1);

require_once __DIR__ . '/_foundation.php';
require_once dirname(__DIR__) . '/payments/_payments.php';

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$idempotency=trim((string)($input['idempotency_key']??''));
if($idempotency===''||mb_strlen($idempotency)>190)mg_fail('A valid idempotency key is required.',422);
$pdo=mg_db();
$pdo->beginTransaction();
try{
    $cart=mg_cart_active($pdo,(int)$user['id'],true);
    $payload=mg_cart_payload($pdo,$cart);
    if(!$payload['items']){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Cart is empty.',409);}
    $merchantIds=array_values(array_unique(array_map(static fn(array $row)=>(int)$row['merchant_user_id'],$payload['items'])));
    if(count($merchantIds)!==1){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Checkout currently supports one merchant.',409);}
    $itemsJson=mg_commerce_json($payload['items']);
    $subtotal=(int)$payload['totals']['subtotal_cents'];
    $platformFee=mg_payment_platform_fee_cents($pdo,$subtotal);
    $total=$subtotal;

    $existing=$pdo->prepare('SELECT * FROM checkout_drafts WHERE buyer_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([(int)$user['id'],$idempotency]);
    if($draft=$existing->fetch()){
        $storedItems=json_decode((string)$draft['items_json'],true,512,JSON_THROW_ON_ERROR);
        $sameRequest=(int)$draft['cart_id']===(int)$cart['id']
            &&(int)$draft['merchant_user_id']===$merchantIds[0]
            &&hash_equals((string)$draft['currency'],(string)$payload['totals']['currency'])
            &&(int)$draft['subtotal_cents']===$subtotal
            &&(int)$draft['discount_cents']===0
            &&(int)$draft['tax_cents']===0
            &&(int)$draft['platform_fee_cents']===$platformFee
            &&(int)$draft['total_cents']===$total
            &&$storedItems==$payload['items'];
        if(!$sameRequest){
            $pdo->rollBack();
            mg_fail('Checkout draft idempotency key is already bound to a different cart snapshot.',409);
        }
        $pdo->commit();
        mg_ok([
            'checkout_draft_id'=>$draft['public_id'],
            'status'=>$draft['status'],
            'expires_at'=>$draft['expires_at'],
            'reused'=>true,
            'totals'=>[
                'currency'=>$draft['currency'],
                'subtotal_cents'=>(int)$draft['subtotal_cents'],
                'platform_fee_cents'=>(int)$draft['platform_fee_cents'],
                'total_cents'=>(int)$draft['total_cents'],
            ],
        ]);
    }

    $draftId=mg_public_uuid();
    $expires=date('Y-m-d H:i:s',time()+1800);
    $pdo->prepare("INSERT INTO checkout_drafts (public_id,cart_id,buyer_user_id,merchant_user_id,currency,subtotal_cents,discount_cents,tax_cents,platform_fee_cents,total_cents,items_json,status,idempotency_key,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,'open',?,?,NOW(),NOW())")
        ->execute([$draftId,(int)$cart['id'],(int)$user['id'],$merchantIds[0],$payload['totals']['currency'],$subtotal,0,0,$platformFee,$total,$itemsJson,$idempotency,$expires]);
    $pdo->commit();
    mg_audit('commerce.checkout_draft_created','checkout_draft',[
        'checkout_draft_id'=>$draftId,
        'cart_id'=>$cart['public_id'],
        'platform_fee_cents'=>$platformFee,
    ],(int)$user['id']);
    mg_ok([
        'checkout_draft_id'=>$draftId,
        'status'=>'open',
        'expires_at'=>$expires,
        'totals'=>[
            'currency'=>$payload['totals']['currency'],
            'subtotal_cents'=>$subtotal,
            'discount_cents'=>0,
            'tax_cents'=>0,
            'platform_fee_cents'=>$platformFee,
            'total_cents'=>$total,
        ],
        'items'=>$payload['items'],
    ],'Checkout draft created.',201);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail('Unable to create checkout draft.',500);
}
