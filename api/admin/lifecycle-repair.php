<?php
declare(strict_types=1);

require_once __DIR__ . '/_system_health.php';
require_once __DIR__ . '/_system_health_actions.php';
require_once __DIR__ . '/_golden_path_health.php';

mg_require_method('POST');
$user=mg_admin_system_health_require_user();
mg_admin_system_health_require_manager($user);
$input=mg_input();
mg_require_csrf_for_write($input);
mg_rate_limit('admin.lifecycle_health.repair','user:'.(int)$user['id'],12,300);

$action=strtolower(trim((string)($input['action']??'')));
$reference=trim((string)($input['subject_reference']??''));
$reason=preg_replace('/\s+/u',' ',trim((string)($input['reason']??'')))??'';
if(!in_array($action,['retry_order_fulfillment','reproject_microgift'],true))mg_fail('Invalid lifecycle repair action.',422);
if($reference===''||strlen($reference)>190||preg_match('/^[A-Za-z0-9._:-]+$/',$reference)!==1)mg_fail('A valid subject reference is required.',422);
if(mb_strlen($reason)<8||mb_strlen($reason)>1000)mg_fail('Provide a repair reason between 8 and 1000 characters.',422);

$pdo=mg_db();
try{
    $pdo->beginTransaction();
    $result=match($action){
        'retry_order_fulfillment'=>mg_admin_golden_path_retry_order($pdo,$reference,(int)$user['id']),
        'reproject_microgift'=>mg_admin_golden_path_reproject($pdo,$reference),
    };
    $pdo->commit();
    $metadata=['action'=>$action,'subject_reference'=>$reference,'reason'=>$reason,'result'=>$result];
    mg_audit('admin.lifecycle_health.'.$action,'lifecycle_health',$metadata,(int)$user['id']);
    mg_event('admin.lifecycle_health.'.$action,$metadata,(int)$user['id']);
}catch(RuntimeException|InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),409);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','admin.lifecycle_health.repair_failed','Lifecycle repair failed.',['action'=>$action,'exception_class'=>$error::class],(int)$user['id']);
    mg_fail('Unable to complete the lifecycle repair.',500);
}

header('Cache-Control: private, no-store, max-age=0');
mg_ok(['action'=>$action,'subject_reference'=>$reference,'result'=>$result],'Lifecycle repair completed.');
