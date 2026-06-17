<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__).'/payments/_refund.php';

mg_require_method('POST');
$user=mg_require_permission('merchant.refunds.manage');
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();
$pdo->beginTransaction();
try{
    $result=mg_finance_refund_order($pdo,(int)$user['id'],(int)$user['id'],$input);
    $pdo->commit();
    mg_ok([
        'refund_id'=>$result['refund_id'],
        'status'=>$result['status'],
        'duplicate'=>$result['duplicate'],
        'order_status'=>$result['order_status']??null,
        'entitlement_policy'=>$result['entitlement_policy']??null,
    ],$result['duplicate']?'Refund already exists.':'Refund created.',$result['duplicate']?200:201);
}catch(MgRefundException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),$e->httpStatus);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail('Unable to create refund.',500);
}
