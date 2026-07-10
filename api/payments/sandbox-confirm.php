<?php
declare(strict_types=1);

require_once __DIR__ . '/_local_confirmation.php';

mg_require_method('POST');
$user=mg_require_permission('commerce.checkout.create');
$input=mg_input();
mg_require_csrf_for_write($input);
if(mg_payment_is_live()||mg_payment_provider_key()!=='sandbox'){
    mg_fail('Sandbox confirmation is unavailable.',403);
}
$sessionId=trim((string)($input['session_id']??''));
if($sessionId==='')mg_fail('Checkout session is required.',422);

$pdo=mg_db();
try{
    $pdo->beginTransaction();
    $result=mg_payment_confirm_local_session($pdo,(int)$user['id'],$sessionId,'sandbox',(int)$user['id']);
    $pdo->commit();
    mg_audit('commerce.payment_succeeded','commerce_order',[
        'order_id'=>$result['order_id']??null,
        'payment_intent_id'=>$result['payment_intent_id']??null,
        'provider'=>'sandbox',
        'issued_count'=>$result['issued_count']??0,
        'microgift_issued_count'=>$result['microgift_issued_count']??0,
        'issuance_complete'=>$result['issuance_complete']??false,
        'reused'=>$result['reused']??false,
    ],(int)$user['id']);
    mg_ok($result,!empty($result['reused'])?'Sandbox payment and delivery reverified.':'Sandbox payment completed.');
}catch(MgCaptureWorkflowException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),$error->httpStatus);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','commerce.sandbox_confirmation_failed','Sandbox payment confirmation failed.',[
        'session_id'=>$sessionId,
        'exception_type'=>get_class($error),
    ],(int)$user['id']);
    mg_fail('Unable to confirm sandbox payment.',500);
}
