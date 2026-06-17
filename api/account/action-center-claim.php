<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once dirname(__DIR__) . '/microgifts/_lifecycle.php';
require_once dirname(__DIR__) . '/microgifts/_idempotency.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();

$actionItemId=trim((string)($input['action_item_id']??$input['id']??''));
$idempotencyKey=trim((string)($input['idempotency_key']??''));
if($actionItemId===''||$idempotencyKey==='')mg_fail('Action Center item id and idempotency key are required.',422);
if(mb_strlen($idempotencyKey)>190)mg_fail('A valid idempotency key is required.',422);

try{
    $pdo->beginTransaction();

    $stmt=$pdo->prepare("SELECT ac.folder,ac.state,ac.user_id,i.*
        FROM microgift_inbox_items ac
        INNER JOIN microgift_instances i ON i.id=ac.instance_id
        WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$actionItemId,(int)$user['id']]);
    $instance=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$instance)throw new RuntimeException('Action Center item not found.');
    if((string)$instance['folder']!=='inbox')throw new RuntimeException('This Action Center item cannot be claimed.');
    if(!in_array((string)$instance['status'],['issued','delivered','claim_pending'],true)){
        throw new RuntimeException('Microgift is not in a claimable lifecycle state.');
    }
    if((string)$instance['recipient_policy']==='named_user' && (int)$instance['recipient_user_id']!==(int)$user['id']){
        throw new RuntimeException('Microgift is assigned to another recipient.');
    }
    if((int)($instance['recipient_user_id']??0)>0 && (int)$instance['recipient_user_id']!==(int)$user['id']){
        throw new RuntimeException('You are not the recipient of this Microgift.');
    }

    $input['instance_id']=(string)$instance['public_id'];
    $existing=mg_microgift_assert_claim_replay($pdo,$idempotencyKey,(string)$instance['public_id'],(int)$user['id']);
    $result=$existing
        ? ['claim_id'=>$existing['public_id'],'instance_id'=>(string)$instance['public_id'],'status'=>$existing['status'],'duplicate'=>true]
        : mg_microgift_claim($pdo,(int)$user['id'],$input);

    $instance=mg_microgift_load_instance($pdo,(string)$result['instance_id']);
    $result['action_center']=mg_action_center_project_lifecycle($pdo,$instance);
    $pdo->commit();

    mg_audit('action_center.microgift_claimed','microgift_instance',[
        'instance_id'=>$result['instance_id'],'claim_id'=>$result['claim_id'],'action_item_id'=>$actionItemId,'duplicate'=>$result['duplicate'],
    ],(int)$user['id']);
    mg_ok($result,'Microgift claim processed.',$result['duplicate']?200:201);
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),409);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','action_center.claim_failed','Action Center claim failed.',['exception'=>$error->getMessage()],(int)$user['id']);
    mg_fail('Unable to claim this Microgift.',500);
}
