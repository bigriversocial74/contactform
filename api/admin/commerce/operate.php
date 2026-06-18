<?php
declare(strict_types=1);

require_once __DIR__ . '/_actions.php';

mg_require_method('POST');
$actor=mg_require_api_user();
$actorId=(int)$actor['id'];
mg_rate_limit('admin.commerce.operate','user:'.$actorId,90,60);
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();

try{
    $pdo->beginTransaction();
    $result=mg_admin_commerce_execute($pdo,$actor,$input);
    $pdo->commit();
    $action=mg_admin_commerce_action($input['action']??null);
    $metadata=['action'=>$action,'subject_type'=>$input['subject_type']??null,'subject_reference'=>$input['subject_reference']??null,'case_id'=>$input['case_id']??null,'reason'=>$input['reason']??null,'result'=>$result];
    mg_audit('admin_commerce_'.$action,'commerce_operation',$metadata,$actorId);
    mg_event('admin.commerce.'.$action,$metadata+['admin_user_id'=>$actorId],$actorId);
    mg_security_log('info','admin.commerce.operation_completed','Admin commerce operation completed.',['action'=>$action],$actorId);
}catch(MgAdminCommerceException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),$error->httpStatus());
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','admin.commerce.operation_failed','Admin commerce operation failed.',['exception_class'=>$error::class],$actorId);
    mg_fail('Unable to complete the commerce operation.',500);
}

header('Cache-Control: private, no-store, max-age=0');
mg_ok(['result'=>$result],'Commerce operation completed.');
