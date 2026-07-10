<?php
declare(strict_types=1);

require_once __DIR__ . '/_local_confirmation.php';

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$sessionId=trim((string)($input['session_id']??''));
if($sessionId==='')mg_fail('Checkout session is required.',422);

$pdo=mg_db();
try{
    $pdo->beginTransaction();
    $result=mg_payment_confirm_local_session($pdo,(int)$user['id'],$sessionId,'cash',(int)$user['id']);
    $pdo->commit();
    mg_audit('commerce.payment_succeeded','commerce_order',[
        'order_id'=>$result['order_id']??null,
        'payment_intent_id'=>$result['payment_intent_id']??null,
        'provider'=>'cash',
        'issued_count'=>$result['issued_count']??0,
        'microgift_issued_count'=>$result['microgift_issued_count']??0,
        'issuance_complete'=>$result['issuance_complete']??false,
        'reused'=>$result['reused']??false,
    ],(int)$user['id']);
    mg_ok($result,!empty($result['reused'])?'Cash payment and delivery reverified.':'Cash payment recorded.');
}catch(MgCaptureWorkflowException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),$error->httpStatus);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','commerce.cash_confirmation_failed','Cash payment confirmation failed.',[
        'session_id'=>$sessionId,
        'exception_type'=>get_class($error),
    ],(int)$user['id']);
    mg_fail('Unable to confirm cash payment.',500);
}
