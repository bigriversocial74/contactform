<?php
declare(strict_types=1);
require_once __DIR__ . '/_engine.php';
require_once __DIR__ . '/_action_center_projection.php';
mg_require_method('POST');
$user=mg_require_permission('microgift.instances.issue');
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();
try{
    $pdo->beginTransaction();
    $result=mg_microgift_issue($pdo,(int)$user['id'],$input);
    $stmt=$pdo->prepare('SELECT * FROM microgift_instances WHERE public_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([(string)$result['instance_id']]);
    $instance=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$instance)throw new RuntimeException('Issued Microgift instance could not be projected.');
    $result['action_center']=mg_action_center_project_lifecycle($pdo,$instance);
    $pdo->commit();
    mg_audit('microgift.instance_issued','microgift_instance',['instance_id'=>$result['instance_id'],'status'=>$result['status'],'duplicate'=>$result['duplicate']],(int)$user['id']);
    mg_event('microgift.instance_issued',['instance_id'=>$result['instance_id'],'source_type'=>(string)($input['source_type']??''),'source_reference'=>(string)($input['source_reference']??''),'duplicate'=>$result['duplicate']],(int)$user['id']);
    mg_ok($result,$result['duplicate']?'Existing Microgift instance returned.':'Microgift instance issued.',$result['duplicate']?200:201);
}catch(InvalidArgumentException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),422);
}catch(RuntimeException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),409);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','microgift.instance_issue_failed','Microgift issuance failed.',['source_type'=>(string)($input['source_type']??''),'source_reference'=>(string)($input['source_reference']??'')],(int)$user['id']);
    mg_fail('Unable to issue the Microgift instance.',500);
}
