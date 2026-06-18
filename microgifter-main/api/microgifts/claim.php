<?php
declare(strict_types=1);
require_once __DIR__ . '/_lifecycle.php';
require_once __DIR__ . '/_idempotency.php';
require_once __DIR__ . '/_action_center_projection.php';
mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();
try {
    $pdo->beginTransaction();
    $instanceId=trim((string)($input['instance_id']??''));
    $idempotencyKey=trim((string)($input['idempotency_key']??''));
    $existing=$instanceId!==''&&$idempotencyKey!==''
        ? mg_microgift_assert_claim_replay($pdo,$idempotencyKey,$instanceId,(int)$user['id'])
        : null;
    $result=$existing
        ? ['claim_id'=>$existing['public_id'],'instance_id'=>$instanceId,'status'=>$existing['status'],'duplicate'=>true]
        : mg_microgift_claim($pdo,(int)$user['id'],$input);
    $instance=mg_microgift_load_instance($pdo,(string)$result['instance_id']);
    $result['action_center']=mg_action_center_project_lifecycle($pdo,$instance);
    $pdo->commit();
    mg_audit('microgift.claim_completed','microgift_instance',['instance_id'=>$result['instance_id'],'claim_id'=>$result['claim_id']],(int)$user['id']);
    mg_ok($result,'Microgift claim processed.',$result['duplicate']?200:201);
} catch (InvalidArgumentException $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),422);
} catch (Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail('Unable to claim this Microgift.',409);
}
