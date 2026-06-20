<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once dirname(__DIR__) . '/microgifts/_lifecycle.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';
require_once dirname(__DIR__) . '/microgifts/_delivery.php';
require_once dirname(__DIR__) . '/pppm/_ownership.php';
require_once dirname(__DIR__) . '/communications/_communications.php';
require_once dirname(__DIR__) . '/messages/_messaging.php';

function mg_action_center_users_have_public_id(PDO $pdo): bool
{
    static $hasColumn=null;
    if($hasColumn!==null)return $hasColumn;
    $stmt=$pdo->prepare("SHOW COLUMNS FROM users LIKE 'public_id'");
    $stmt->execute();
    $hasColumn=(bool)$stmt->fetch();
    return $hasColumn;
}

function mg_action_center_resolve_recipient(PDO $pdo,string $reference): int
{
    $reference=trim($reference);
    if($reference==='')return 0;
    if(mg_action_center_users_have_public_id($pdo)){
        $stmt=$pdo->prepare("SELECT id FROM users WHERE (public_id=? OR email=?) AND status='active' LIMIT 1");
        $stmt->execute([$reference,$reference]);
    }else{
        $stmt=$pdo->prepare("SELECT id FROM users WHERE email=? AND status='active' LIMIT 1");
        $stmt->execute([$reference]);
    }
    return (int)($stmt->fetchColumn()?:0);
}

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();

$actionItemId=trim((string)($input['action_item_id']??$input['id']??''));
$idempotencyKey=trim((string)($input['idempotency_key']??''));
$recipientReference=trim((string)($input['recipient_user_id']??$input['recipient']??''));
$message=trim((string)($input['message']??''));
if($actionItemId===''||$idempotencyKey===''||$recipientReference===''){
    mg_fail('Action Center item, recipient, and idempotency key are required.',422);
}
if(mb_strlen($idempotencyKey)>190)mg_fail('A valid idempotency key is required.',422);
if(mb_strlen($message)>5000)mg_fail('Message is too long.',422);

try{
    $pdo->beginTransaction();

    $stmt=$pdo->prepare("SELECT
            ac.public_id action_item_id,ac.folder,ac.sender_user_id action_sender_user_id,
            ac.recipient_user_id action_recipient_user_id,ac.archived_at,
            i.*,p.public_id pppm_public_id,p.owner_user_id pppm_owner_user_id
        FROM microgift_inbox_items ac
        INNER JOIN microgift_instances i ON i.id=ac.instance_id
        LEFT JOIN pppm_items p ON p.id=i.pppm_item_id
        WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$actionItemId,(int)$user['id']]);
    $instance=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$instance)throw new RuntimeException('Action Center item not found.');

    $recipientUserId=mg_action_center_resolve_recipient($pdo,$recipientReference);
    if($recipientUserId<1)throw new RuntimeException('Recipient not found.');
    if($recipientUserId===(int)$user['id'])throw new RuntimeException('Choose another recipient for this Microgift.');

    if((string)$instance['folder']==='sent'){
        $eventStmt=$pdo->prepare("SELECT * FROM microgift_delivery_events WHERE idempotency_key=? AND event_type='sent' LIMIT 1 FOR UPDATE");
        $eventStmt->execute([$idempotencyKey]);
        $event=$eventStmt->fetch(PDO::FETCH_ASSOC);
        $same=$event
            &&(int)$event['instance_id']===(int)$instance['id']
            &&(int)$event['sender_user_id']===(int)$user['id']
            &&(int)$event['recipient_user_id']===$recipientUserId;
        if(!$same)throw new RuntimeException('This Action Center item has already been transferred. Use Follow Up to contact the current recipient.');
        $summary=mg_microgift_delivery_summary($pdo,(int)$instance['id']);
        $pdo->commit();
        mg_ok([
            'instance_id'=>$instance['public_id'],
            'recipient_user_id'=>$recipientUserId,
            'status'=>(string)$instance['status'],
            'duplicate'=>true,
            'delivery_event'=>[
                'event_id'=>(string)$event['public_id'],
                'event_type'=>'sent',
                'occurred_at'=>(string)$event['occurred_at'],
                'duplicate'=>true,
            ],
            'delivery_summary'=>$summary,
        ],'Existing transfer result returned.');
    }

    if((string)$instance['folder']!=='inbox')throw new RuntimeException('This Action Center item cannot be transferred.');
    if((int)$instance['owner_user_id']!==(int)$user['id'])throw new RuntimeException('You do not own this Microgift.');
    if(!in_array((string)$instance['status'],['issued','delivered'],true)){
        throw new RuntimeException('Microgift is not in a transferable lifecycle state.');
    }

    $pppmPublicId=trim((string)($instance['pppm_public_id']??''));
    if($pppmPublicId==='')throw new RuntimeException('Microgift ownership authority is unavailable.');
    if((int)($instance['pppm_owner_user_id']??0)!==(int)$user['id']){
        throw new RuntimeException('PPPM ownership does not match this Action Center item.');
    }

    $transfer=mg_pppm_transfer_owner_canonical(
        $pdo,
        $pppmPublicId,
        $recipientUserId,
        'action_center_regift',
        $idempotencyKey,
        (int)$user['id'],
        ['microgift_instance_id'=>(string)$instance['public_id'],'action_item_id'=>$actionItemId]
    );
    $duplicate=(bool)($transfer['duplicate']??false);

    $pdo->prepare("UPDATE microgift_instances
        SET owner_user_id=?,recipient_user_id=?,status='delivered',delivered_at=COALESCE(delivered_at,NOW()),updated_at=NOW()
        WHERE id=?")
        ->execute([$recipientUserId,$recipientUserId,(int)$instance['id']]);
    $instance=mg_microgift_load_instance($pdo,(string)$instance['public_id']);

    $deliveryEvent=mg_microgift_delivery_event(
        $pdo,
        $instance,
        'sent',
        (int)$user['id'],
        $recipientUserId,
        $idempotencyKey,
        $actionItemId,
        ['pppm_item_id'=>$pppmPublicId,'transfer_id'=>$transfer['transfer_id']??null,'transfer_type'=>'regift']
    );
    $duplicate=$duplicate||(bool)$deliveryEvent['duplicate'];
    $projection=mg_action_center_sent(
        $pdo,
        (int)$instance['id'],
        (int)$user['id'],
        $recipientUserId,
        [
            'sent_at'=>$deliveryEvent['occurred_at'],
            'received_at'=>$deliveryEvent['occurred_at'],
            'occurred_at'=>$deliveryEvent['occurred_at'],
        ]
    );

    $conversationKey='delivery:'.(string)$deliveryEvent['event_id'];
    $messageResult=null;
    if($message!==''){
        $messageResult=mg_message_send_microgift(
            $pdo,
            $instance,
            (int)$user['id'],
            $recipientUserId,
            $message,
            'send-note:'.hash('sha256',$idempotencyKey),
            $actionItemId,
            [(int)$user['id'],$recipientUserId],
            $conversationKey,
            'send_note',
            false
        );
    }

    $senderLabel=mg_notification_user_label($pdo,(int)$user['id']);
    $notificationId=mg_create_notification(
        $pdo,
        $recipientUserId,
        'microgift_received',
        $senderLabel.' sent you a Microgift',
        $message!==''?$message:(string)($instance['title_snapshot']??'Open your Microgift.'),
        '/inbox.php?item='.rawurlencode((string)$projection['recipient_inbox_item_id']),
        [
            'actor_user_id'=>(int)$user['id'],
            'event_key'=>'microgift_received:'.(string)$deliveryEvent['event_id'],
            'pppm_item_id'=>!empty($instance['pppm_item_id'])?(int)$instance['pppm_item_id']:null,
            'microgift_instance_id'=>(string)$instance['public_id'],
            'delivery_event_id'=>(string)$deliveryEvent['event_id'],
            'sender_user_id'=>(int)$user['id'],
            'recipient_user_id'=>$recipientUserId,
        ]
    );

    $summary=mg_microgift_delivery_summary($pdo,(int)$instance['id']);
    $pdo->commit();

    mg_audit('action_center.microgift_regifted','microgift_instance',[
        'instance_id'=>$instance['public_id'],
        'action_item_id'=>$actionItemId,
        'recipient_user_id'=>$recipientUserId,
        'sent_at'=>$deliveryEvent['occurred_at'],
        'delivery_event_id'=>$deliveryEvent['event_id'],
        'notification_id'=>$notificationId,
        'message_id'=>$messageResult['message_id']??null,
        'original_issuer_user_id'=>$instance['issuer_user_id']??null,
        'duplicate'=>$duplicate,
    ],(int)$user['id']);
    mg_event('microgift.regifted',[
        'instance_id'=>$instance['public_id'],
        'recipient_user_id'=>$recipientUserId,
        'idempotency_key'=>$idempotencyKey,
        'sent_at'=>$deliveryEvent['occurred_at'],
        'notification_id'=>$notificationId,
        'duplicate'=>$duplicate,
    ],(int)$user['id']);
    mg_ok([
        'instance_id'=>$instance['public_id'],
        'recipient_user_id'=>$recipientUserId,
        'status'=>(string)$instance['status'],
        'duplicate'=>$duplicate,
        'transfer'=>$transfer,
        'delivery_event'=>$deliveryEvent,
        'delivery_summary'=>$summary,
        'notification_id'=>$notificationId,
        'message'=>$messageResult,
        'action_center'=>$projection,
    ],$duplicate?'Existing transfer result returned.':'Microgift regifted.',$duplicate?200:201);
}catch(JsonException|InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),409);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','action_center.regift_failed','Action Center regift failed.',['exception'=>$error->getMessage()],(int)$user['id']);
    mg_fail('Unable to regift this Microgift.',500);
}
