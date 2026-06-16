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
    $sourceReference=trim((string)($input['source_reference']??''));
    $merchantUserId=(int)($input['merchant_user_id']??0);
    $locationReference=trim((string)($input['location_reference']??''));
    $existing=$instanceId!==''&&$idempotencyKey!==''&&$sourceReference!==''&&$merchantUserId>0
        ? mg_microgift_assert_redemption_replay($pdo,$idempotencyKey,$instanceId,(int)$user['id'],$merchantUserId,$locationReference,$sourceReference)
        : null;
    $result=$existing
        ? ['redemption_id'=>$existing['public_id'],'instance_id'=>$instanceId,'status'=>$existing['status'],'duplicate'=>true]
        : mg_microgift_redeem($pdo,(int)$user['id'],$input);
    $instance=mg_microgift_load_instance($pdo,(string)$result['instance_id']);
    $redemptionStmt=$pdo->prepare('SELECT id,merchant_user_id,location_id FROM microgift_redemptions WHERE public_id=? LIMIT 1');
    $redemptionStmt->execute([(string)$result['redemption_id']]);
    $redemption=$redemptionStmt->fetch(PDO::FETCH_ASSOC)?:[];
    $result['action_center']=mg_action_center_project_lifecycle($pdo,$instance,[
        'redemption_id'=>(int)($redemption['id']??0)?:null,
        'merchant_user_id'=>(int)($redemption['merchant_user_id']??0)?:null,
        'location_id'=>(int)($redemption['location_id']??0)?:null,
        'can_tip'=>1,
    ]);
    $pdo->commit();
    mg_audit('microgift.redemption_completed','microgift_instance',['instance_id'=>$result['instance_id'],'redemption_id'=>$result['redemption_id']],(int)$user['id']);
    mg_event('microgift.redemption_completed',$result,(int)$user['id']);
    mg_ok($result,'Microgift redemption processed.',$result['duplicate']?200:201);
} catch (InvalidArgumentException $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($e->getMessage(),422);
} catch (Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail('Unable to redeem this Microgift.',409);
}
