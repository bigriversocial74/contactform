<?php
declare(strict_types=1);

require_once __DIR__ . '/_actions.php';

mg_require_method('POST');
$actor=mg_require_api_user();
$actorId=(int)$actor['id'];
mg_rate_limit('admin.merchant_catalog.operate','user:'.$actorId,90,60);
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();

try{
    $pdo->beginTransaction();
    $result=mg_admin_mc_execute($pdo,$actor,$input);
    $pdo->commit();
    $metadata=['action'=>$result['action'],'subject_type'=>$result['subject_type'],'subject_reference'=>$result['subject_reference'],'from_status'=>$result['from_status'],'to_status'=>$result['to_status'],'cascades'=>$result['cascades'],'reason'=>$input['reason']??null];
    mg_audit('admin_merchant_catalog_'.$result['action'],$result['subject_type'],$metadata,$actorId);
    mg_event('admin.merchant_catalog.'.$result['action'],$metadata+['admin_user_id'=>$actorId],$actorId);
    mg_security_log('info','admin.merchant_catalog.operation_completed','Merchant catalog operation completed.',['action'=>$result['action'],'subject_type'=>$result['subject_type']],$actorId);
}catch(MgAdminMerchantCatalogException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('warning','admin.merchant_catalog.operation_rejected','Merchant catalog operation rejected.',['reason'=>$error->getMessage()],$actorId);
    mg_fail($error->getMessage(),$error->httpStatus());
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','admin.merchant_catalog.operation_failed','Merchant catalog operation failed.',['exception_class'=>$error::class],$actorId);
    mg_fail('Unable to complete the merchant catalog operation.',500);
}

header('Cache-Control: private, no-store, max-age=0');
mg_ok(['result'=>$result],'Merchant catalog operation completed.');
