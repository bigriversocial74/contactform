<?php
declare(strict_types=1);
require_once __DIR__ . '/_checkout_session.php';

function mg_checkout_local_redirect(mixed $value,string $fallback): string
{
    $path=trim((string)$value);
    if($path===''||!str_starts_with($path,'/')||str_starts_with($path,'//')||str_contains($path,"\r")||str_contains($path,"\n"))return $fallback;
    return mb_substr($path,0,500);
}

mg_require_method('POST');
$user=mg_require_permission('commerce.checkout.create');
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();
$pdo->beginTransaction();
try{
    $result=mg_payment_create_checkout_session(
        $pdo,
        (int)$user['id'],
        trim((string)($input['order_id']??'')),
        trim((string)($input['idempotency_key']??'')),
        [
            'success_url'=>mg_checkout_local_redirect($input['success_url']??null,'/checkout-success.php'),
            'cancel_url'=>mg_checkout_local_redirect($input['cancel_url']??null,'/cart.php'),
        ]
    );
    $pdo->commit();
    if(!$result['duplicate'])mg_audit('commerce.payment_session_created','commerce_order',[
        'order_id'=>$result['order_id'],
        'checkout_session_id'=>$result['checkout_session_id'],
        'provider'=>$result['provider'],
    ],(int)$user['id']);
    mg_ok($result,$result['duplicate']?'Checkout session already exists.':'Checkout session created.',$result['duplicate']?200:201);
}catch(MgCheckoutSessionException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),$e->httpStatus);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail('Unable to create checkout session.',500);
}
