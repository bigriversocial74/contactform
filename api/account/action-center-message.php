<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once dirname(__DIR__) . '/messages/_messaging.php';

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);

$actionItemId=trim((string)($input['action_item_id']??$input['id']??''));
$message=trim((string)($input['message']??''));
$idempotencyKey=trim((string)($input['idempotency_key']??''));
if($actionItemId===''||$message===''||$idempotencyKey==='')mg_fail('Action Center item, message, and idempotency key are required.',422);

$pdo=mg_db();
try{
    $pdo->beginTransaction();
    $stmt=$pdo->prepare("SELECT ac.sender_user_id,ac.recipient_user_id,
            i.id,i.public_id,i.title_snapshot,i.issuer_user_id,i.owner_user_id,i.recipient_user_id instance_recipient_user_id,
            i.legacy_gift_id,i.pppm_item_id
        FROM microgift_inbox_items ac
        INNER JOIN microgift_instances i ON i.id=ac.instance_id
        WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$actionItemId,(int)$user['id']]);
    $item=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$item)throw new RuntimeException('Action Center item not found.');

    $instance=[
        'id'=>(int)$item['id'],
        'public_id'=>(string)$item['public_id'],
        'title_snapshot'=>(string)$item['title_snapshot'],
        'issuer_user_id'=>(int)$item['issuer_user_id'],
        'owner_user_id'=>$item['owner_user_id']!==null?(int)$item['owner_user_id']:null,
        'recipient_user_id'=>$item['instance_recipient_user_id']!==null?(int)$item['instance_recipient_user_id']:null,
        'legacy_gift_id'=>$item['legacy_gift_id']!==null?(int)$item['legacy_gift_id']:null,
        'pppm_item_id'=>$item['pppm_item_id']!==null?(int)$item['pppm_item_id']:null,
    ];

    $participants=mg_message_microgift_participants($instance);
    $recipientUserId=(int)($input['recipient_user_id']??0);
    if($recipientUserId<1){
        foreach($participants as $participant){
            if($participant!==(int)$user['id']){$recipientUserId=$participant;break;}
        }
    }

    $result=mg_message_send_microgift(
        $pdo,$instance,(int)$user['id'],$recipientUserId,$message,$idempotencyKey,$actionItemId
    );
    $pdo->commit();

    mg_audit('action_center.message_sent','message_thread',[
        'thread_id'=>$result['thread_id'],
        'message_id'=>$result['message_id'],
        'instance_id'=>$instance['public_id'],
        'recipient_user_id'=>$result['recipient_user_id'],
        'duplicate'=>$result['duplicate'],
    ],(int)$user['id']);
    mg_ok([
        'thread_id'=>$result['thread_id'],
        'message_id'=>$result['message_id'],
        'instance_id'=>$instance['public_id'],
        'recipient_user_id'=>$result['recipient_user_id'],
        'status'=>'accepted',
        'duplicate'=>$result['duplicate'],
    ],$result['duplicate']?'Existing message result returned.':'Message accepted.',$result['duplicate']?200:202);
}catch(JsonException|InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),409);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','action_center.message_failed','Action Center message failed.',['exception'=>$error->getMessage()],(int)$user['id']);
    mg_fail('Unable to send this message.',500);
}
