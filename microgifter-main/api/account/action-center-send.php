<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once dirname(__DIR__) . '/microgifts/_lifecycle.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';
require_once dirname(__DIR__) . '/pppm/_ownership.php';

function mg_action_center_users_have_public_id(PDO $pdo): bool
{
    static $hasColumn=null;
    if($hasColumn!==null)return $hasColumn;
    $stmt=$pdo->prepare("SHOW COLUMNS FROM users LIKE 'public_id'");
    $stmt->execute();
    $hasColumn=(bool)$stmt->fetch();
    return $hasColumn;
}

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();

$actionItemId=trim((string)($input['action_item_id']??$input['id']??''));
$idempotencyKey=trim((string)($input['idempotency_key']??''));
$recipientReference=trim((string)($input['recipient_user_id']??$input['recipient']??''));
if($actionItemId===''||$idempotencyKey===''||$recipientReference===''){
    mg_fail('Action Center item, recipient, and idempotency key are required.',422);
}
if(mb_strlen($idempotencyKey)>190)mg_fail('A valid idempotency key is required.',422);

try{
    $pdo->beginTransaction();

    $stmt=$pdo->prepare("SELECT ac.*,i.*,p.public_id pppm_public_id
        FROM microgift_inbox_items ac
        INNER JOIN microgift_instances i ON i.id=ac.instance_id
        LEFT JOIN pppm_items p ON p.id=i.pppm_item_id
        WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$actionItemId,(int)$user['id']]);
    $instance=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$instance)throw new RuntimeException('Action Center item not found.');
    if((string)$instance['folder']!=='inbox')throw new RuntimeException('This Action Center item cannot be sent.');
    if((int)$instance['owner_user_id']!==(int)$user['id'])throw new RuntimeException('You do not own this Microgift.');
    if(!in_array((string)$instance['status'],['issued','delivered','claim_pending'],true)){
        throw new RuntimeException('Microgift is not in a sendable lifecycle state.');
    }

    if(ctype_digit($recipientReference)){
        $recipientStmt=$pdo->prepare("SELECT id FROM users WHERE id=? AND status='active' LIMIT 1");
        $recipientStmt->execute([(int)$recipientReference]);
    }elseif(mg_action_center_users_have_public_id($pdo)){
        $recipientStmt=$pdo->prepare("SELECT id FROM users WHERE (public_id=? OR email=?) AND status='active' LIMIT 1");
        $recipientStmt->execute([$recipientReference,$recipientReference]);
    }else{
        $recipientStmt=$pdo->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1");
        $recipientStmt->execute([$recipientReference]);
    }
    $recipientUserId=(int)($recipientStmt->fetchColumn()?:0);
    if($recipientUserId<1)throw new RuntimeException('Recipient not found.');

    $duplicate=false;
    $transfer=null;
    if($recipientUserId===(int)$user['id']){
        $duplicate=true;
    }else{
        $pppmPublicId=trim((string)($instance['pppm_public_id']??''));
        if($pppmPublicId==='')throw new RuntimeException('Microgift ownership authority is unavailable.');
        $transfer=mg_pppm_transfer_owner_canonical(
            $pdo,
            $pppmPublicId,
            $recipientUserId,
            'action_center_send',
            $idempotencyKey,
            (int)$user['id'],
            ['microgift_instance_id'=>(string)$instance['public_id'],'action_item_id'=>$actionItemId]
        );
        $duplicate=(bool)($transfer['duplicate']??false);
        $pdo->prepare("UPDATE microgift_instances
            SET issuer_user_id=?,owner_user_id=?,recipient_user_id=?,status='delivered',delivered_at=COALESCE(delivered_at,NOW()),updated_at=NOW()
            WHERE id=?")
            ->execute([(int)$user['id'],$recipientUserId,$recipientUserId,(int)$instance['id']]);
        $instance=mg_microgift_load_instance($pdo,(string)$instance['public_id']);
    }

    $projection=mg_action_center_sent($pdo,(int)$instance['id'],(int)$user['id'],$recipientUserId);
    $pdo->commit();

    mg_audit('action_center.microgift_sent','microgift_instance',[
        'instance_id'=>$instance['public_id'],'action_item_id'=>$actionItemId,'recipient_user_id'=>$recipientUserId,'duplicate'=>$duplicate,
    ],(int)$user['id']);
    mg_event('microgift.sent',[
        'instance_id'=>$instance['public_id'],'recipient_user_id'=>$recipientUserId,'idempotency_key'=>$idempotencyKey,'duplicate'=>$duplicate,
    ],(int)$user['id']);
    mg_ok([
        'instance_id'=>$instance['public_id'],'recipient_user_id'=>$recipientUserId,'status'=>(string)$instance['status'],
        'duplicate'=>$duplicate,'transfer'=>$transfer,'action_center'=>$projection,
    ],$duplicate?'Existing send result returned.':'Microgift sent.',$duplicate?200:201);
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),409);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','action_center.send_failed','Action Center send failed.',['exception'=>$error->getMessage()],(int)$user['id']);
    mg_fail('Unable to send this Microgift.',500);
}
