<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/gifts/_gift.php';
require_once dirname(__DIR__) . '/communications/_communications.php';

function mg_message_microgift_participants(array $instance): array
{
    $participants=[];
    foreach(['issuer_user_id','owner_user_id','recipient_user_id'] as $field){
        $id=(int)($instance[$field]??0);
        if($id>0)$participants[$id]=$id;
    }
    return array_values($participants);
}

function mg_message_require_microgift_participant(array $instance,int $userId): void
{
    if(!in_array($userId,mg_message_microgift_participants($instance),true)){
        throw new RuntimeException('You cannot message participants for this Microgift.');
    }
}

function mg_message_microgift_thread(PDO $pdo,array $instance,int $actorUserId): array
{
    mg_message_require_microgift_participant($instance,$actorUserId);

    $clauses=['microgift_instance_id=?'];
    $params=[(int)$instance['id']];
    if(!empty($instance['pppm_item_id'])){
        $clauses[]='pppm_item_id=?';
        $params[]=(int)$instance['pppm_item_id'];
    }
    if(!empty($instance['legacy_gift_id'])){
        $clauses[]='gift_id=?';
        $params[]=(int)$instance['legacy_gift_id'];
    }

    $stmt=$pdo->prepare('SELECT id,public_id,microgift_instance_id FROM message_threads WHERE '.implode(' OR ',$clauses).' ORDER BY microgift_instance_id IS NOT NULL DESC,updated_at DESC,id DESC LIMIT 1 FOR UPDATE');
    $stmt->execute($params);
    $thread=$stmt->fetch(PDO::FETCH_ASSOC);

    if($thread){
        if(empty($thread['microgift_instance_id'])){
            $pdo->prepare('UPDATE message_threads SET microgift_instance_id=?,updated_at=NOW() WHERE id=?')
                ->execute([(int)$instance['id'],(int)$thread['id']]);
            $thread['microgift_instance_id']=(int)$instance['id'];
        }
    }else{
        $publicId=mg_public_uuid();
        $pdo->prepare('INSERT INTO message_threads (public_id,gift_id,pppm_item_id,microgift_instance_id,created_by_user_id,subject,created_at,updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())')
            ->execute([
                $publicId,
                !empty($instance['legacy_gift_id'])?(int)$instance['legacy_gift_id']:null,
                !empty($instance['pppm_item_id'])?(int)$instance['pppm_item_id']:null,
                (int)$instance['id'],
                $actorUserId,
                mb_substr((string)($instance['title_snapshot']??'Microgift conversation'),0,160),
            ]);
        $thread=['id'=>(int)$pdo->lastInsertId(),'public_id'=>$publicId,'microgift_instance_id'=>(int)$instance['id']];
    }

    $participant=$pdo->prepare('INSERT IGNORE INTO message_thread_participants (thread_id,user_id,joined_at) VALUES (?,?,NOW())');
    foreach(mg_message_microgift_participants($instance) as $participantId){
        $participant->execute([(int)$thread['id'],$participantId]);
    }
    return $thread;
}

function mg_message_send_microgift(PDO $pdo,array $instance,int $senderUserId,int $recipientUserId,string $body,string $idempotencyKey,string $sourceReference): array
{
    $body=mg_message_validate_body($body);
    if($idempotencyKey===''||mb_strlen($idempotencyKey)>190)throw new InvalidArgumentException('A valid message idempotency key is required.');
    if($sourceReference===''||mb_strlen($sourceReference)>190)throw new InvalidArgumentException('A valid message source reference is required.');

    $participants=mg_message_microgift_participants($instance);
    if(!in_array($senderUserId,$participants,true))throw new RuntimeException('You cannot message participants for this Microgift.');
    if($recipientUserId<1||$recipientUserId===$senderUserId||!in_array($recipientUserId,$participants,true))throw new RuntimeException('Message recipient is not authorized for this Microgift.');

    $thread=mg_message_microgift_thread($pdo,$instance,$senderUserId);
    $existing=$pdo->prepare('SELECT public_id,thread_id,recipient_user_id,body,source_reference FROM messages WHERE sender_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([$senderUserId,$idempotencyKey]);
    $row=$existing->fetch(PDO::FETCH_ASSOC);
    if($row){
        $same=(int)$row['thread_id']===(int)$thread['id']
            && (int)$row['recipient_user_id']===$recipientUserId
            && hash_equals((string)$row['body'],$body)
            && (string)$row['source_reference']===$sourceReference;
        if(!$same)throw new RuntimeException('Idempotency key is already bound to a different message request.');
        return ['thread_id'=>(string)$thread['public_id'],'message_id'=>(string)$row['public_id'],'recipient_user_id'=>$recipientUserId,'duplicate'=>true];
    }

    $messagePublicId=mg_public_uuid();
    $pdo->prepare("INSERT INTO messages (public_id,thread_id,sender_user_id,recipient_user_id,body,idempotency_key,source_type,source_reference,created_at) VALUES (?,?,?,?,?,?,'action_center',?,NOW())")
        ->execute([$messagePublicId,(int)$thread['id'],$senderUserId,$recipientUserId,$body,$idempotencyKey,$sourceReference]);
    $pdo->prepare('UPDATE message_threads SET updated_at=NOW() WHERE id=?')->execute([(int)$thread['id']]);
    $pdo->prepare('UPDATE message_thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int)$thread['id'],$senderUserId]);

    $notificationPublicId=mg_public_uuid();
    $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,gift_id,pppm_item_id,thread_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())')
        ->execute([
            $notificationPublicId,$recipientUserId,'message','New Microgift message',mb_substr($body,0,500),
            '/inbox.php?thread='.rawurlencode((string)$thread['public_id']),
            !empty($instance['legacy_gift_id'])?(int)$instance['legacy_gift_id']:null,
            !empty($instance['pppm_item_id'])?(int)$instance['pppm_item_id']:null,
            (int)$thread['id'],
        ]);
    mg_queue_notification_deliveries($pdo,(int)$pdo->lastInsertId(),$recipientUserId,'message');

    $pdo->prepare("INSERT INTO microgift_events (public_id,instance_id,event_type,actor_user_id,source_type,source_reference,payload_json,created_at) VALUES (?,?,'message.sent',?,'action_center',?,?,NOW())")
        ->execute([mg_public_uuid(),(int)$instance['id'],$senderUserId,$sourceReference,json_encode(['thread_id'=>$thread['public_id'],'message_id'=>$messagePublicId,'recipient_user_id'=>$recipientUserId],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);

    return ['thread_id'=>(string)$thread['public_id'],'message_id'=>$messagePublicId,'recipient_user_id'=>$recipientUserId,'duplicate'=>false];
}
