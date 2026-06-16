<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/microgifts/_lifecycle.php';
require_once dirname(__DIR__) . '/microgifts/_idempotency.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';
mg_require_method('POST');
$user=mg_require_permission('microgift.lifecycle.manage');
$input=mg_input();
mg_require_csrf_for_write($input);
$instanceId=trim((string)($input['instance_id']??''));
$action=trim((string)($input['action']??''));
$sourceReference=trim((string)($input['source_reference']??''));
$key=trim((string)($input['idempotency_key']??''));
if($instanceId===''||$action===''||$sourceReference===''||$key==='')mg_fail('Instance, action, source reference, and idempotency key are required.',422);
$pdo=mg_db();
try {
    $pdo->beginTransaction();
    $instance=mg_microgift_load_instance($pdo,$instanceId);
    if($action==='rotate_claim'||$action==='rotate_redeem'){
        $result=mg_microgift_rotate_credential($pdo,$instance,$action==='rotate_claim'?'claim':'redeem',(int)$user['id']);
    } else {
        $existing=mg_microgift_assert_lifecycle_replay($pdo,$key,$instanceId,$action,'admin',$sourceReference);
        $result=$existing
            ? ['action_id'=>$existing['public_id'],'status'=>$existing['to_status'],'duplicate'=>true]
            : mg_microgift_apply_lifecycle($pdo,$instance,$action,'admin',$sourceReference,$key,(int)$user['id'],trim((string)($input['reason']??'')));
        $instance=mg_microgift_load_instance($pdo,$instanceId);
        $result['action_center']=mg_action_center_project_lifecycle($pdo,$instance);
    }
    $pdo->commit();
    mg_audit('microgift.lifecycle_applied','microgift_instance',['instance_id'=>$instanceId,'action'=>$action,'result'=>$result],(int)$user['id']);
    mg_ok($result,'Microgift lifecycle action applied.');
} catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to apply Microgift lifecycle action.',409);}
