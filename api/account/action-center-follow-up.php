<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once dirname(__DIR__) . '/messages/_messaging.php';
require_once dirname(__DIR__) . '/stamps/_stamps.php';

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);

$actionItemId=trim((string)($input['action_item_id']??$input['id']??''));
$message=trim((string)($input['message']??''));
$idempotencyKey=trim((string)($input['idempotency_key']??''));
if($actionItemId===''||$message===''||$idempotencyKey===''){
    mg_fail('Sent item, message, and idempotency key are required.',422);
}
if(mb_strlen($idempotencyKey)>190)mg_fail('A valid idempotency key is required.',422);

$pdo=mg_db();
try{
    $pdo->beginTransaction();
    $stmt=$pdo->prepare(
        "SELECT ac.public_id action_item_id,ac.folder,
                ac.sender_user_id action_sender_user_id,
                ac.recipient_user_id action_recipient_user_id,
                i.*
         FROM microgift_inbox_items ac
         INNER JOIN microgift_instances i ON i.id=ac.instance_id
         WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$actionItemId,(int)$user['id']]);
    $instance=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$instance)throw new RuntimeException('Sent Microgift not found.');
    if((string)$instance['folder']!=='sent')throw new RuntimeException('Follow Up is available only from Sent.');
    if((int)$instance['action_sender_user_id']!==(int)$user['id']){
        throw new RuntimeException('Only the most recent sender can follow up.');
    }

    $recipientUserId=(int)($instance['action_recipient_user_id']??0);
    if($recipientUserId<1)throw new RuntimeException('The current recipient is unavailable.');
    if((int)$instance['owner_user_id']!==$recipientUserId||(int)$instance['recipient_user_id']!==$recipientUserId){
        throw new RuntimeException('This recipient no longer owns the Microgift.');
    }
    if(!in_array((string)$instance['status'],['issued','delivered'],true)||!empty($instance['claimed_at'])||!empty($instance['redeemed_at'])){
        throw new RuntimeException('Messaging is unavailable after the Microgift is closed.');
    }

    $recipientStmt=$pdo->prepare("SELECT id FROM users WHERE id=? AND status='active' LIMIT 1");
    $recipientStmt->execute([$recipientUserId]);
    if(!(int)$recipientStmt->fetchColumn())throw new RuntimeException('The current recipient is not available.');

    $conversationKey=mg_message_conversation_key($pdo,$instance,(int)$user['id'],$recipientUserId);
    $result=mg_message_send_microgift(
        $pdo,
        $instance,
        (int)$user['id'],
        $recipientUserId,
        $message,
        $idempotencyKey,
        $actionItemId,
        [(int)$user['id'],$recipientUserId],
        $conversationKey,
        'follow_up',
        true
    );
    $stampLedger=!empty($result['duplicate'])?null:mg_stamp_debit_send($pdo,(int)$user['id'],(int)$user['id'],'direct_microgift_send',$idempotencyKey,[
        'source_type'=>'action_center_follow_up',
        'source_id'=>(string)($result['message_id']??$idempotencyKey),
        'reference'=>$actionItemId,
        'metadata'=>[
            'thread_id'=>$result['thread_id']??null,
            'message_id'=>$result['message_id']??null,
            'instance_id'=>(string)$instance['public_id'],
            'recipient_user_id'=>$recipientUserId,
        ],
    ]);
    $pdo->commit();

    mg_audit('action_center.follow_up_sent','message_thread',[
        'thread_id'=>$result['thread_id'],
        'message_id'=>$result['message_id'],
        'instance_id'=>$instance['public_id'],
        'action_item_id'=>$actionItemId,
        'recipient_user_id'=>$recipientUserId,
        'conversation_key'=>$result['conversation_key'],
        'notification_id'=>$result['notification_id'],
        'stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id']??null,
        'duplicate'=>$result['duplicate'],
    ],(int)$user['id']);

    mg_ok([
        'thread_id'=>$result['thread_id'],
        'message_id'=>$result['message_id'],
        'instance_id'=>$instance['public_id'],
        'recipient_user_id'=>$recipientUserId,
        'conversation_key'=>$result['conversation_key'],
        'notification_id'=>$result['notification_id'],
        'stamp_ledger'=>$stampLedger,
        'status'=>'accepted',
        'duplicate'=>$result['duplicate'],
    ],$result['duplicate']?'Existing result returned.':'Message sent.',$result['duplicate']?200:202);
}catch(JsonException|InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),409);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','action_center.follow_up_failed','Action Center follow up failed.',['exception'=>$error->getMessage()],(int)$user['id']);
    mg_fail('Unable to send this message.',500);
}
