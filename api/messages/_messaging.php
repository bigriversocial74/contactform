<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/gifts/_gift.php';
require_once dirname(__DIR__) . '/communications/_communications.php';

function mg_message_microgift_participants(array $instance,array $explicitParticipants=[]): array
{
    $participants=[];
    if($explicitParticipants!==[]){
        foreach($explicitParticipants as $participantId){
            $id=(int)$participantId;
            if($id>0)$participants[$id]=$id;
        }
        return array_values($participants);
    }
    foreach(['issuer_user_id','owner_user_id','recipient_user_id'] as $field){
        $id=(int)($instance[$field]??0);
        if($id>0)$participants[$id]=$id;
    }
    return array_values($participants);
}

function mg_message_require_microgift_participant(array $instance,int $userId,array $explicitParticipants=[]): void
{
    if(!in_array($userId,mg_message_microgift_participants($instance,$explicitParticipants),true)){
        throw new RuntimeException('You cannot message participants for this Microgift.');
    }
}

function mg_message_conversation_key(PDO $pdo,array $instance,int $senderUserId,int $recipientUserId): string
{
    if($senderUserId<1||$recipientUserId<1||$senderUserId===$recipientUserId){
        throw new InvalidArgumentException('A valid Microgift conversation pair is required.');
    }
    $stmt=$pdo->prepare(
        "SELECT public_id FROM microgift_delivery_events
         WHERE instance_id=? AND event_type='sent'
           AND ((sender_user_id=? AND recipient_user_id=?) OR (sender_user_id=? AND recipient_user_id=?))
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([
        (int)$instance['id'],
        $senderUserId,$recipientUserId,
        $recipientUserId,$senderUserId,
    ]);
    $deliveryPublicId=trim((string)($stmt->fetchColumn()?:''));
    if($deliveryPublicId!=='')return 'delivery:'.$deliveryPublicId;

    $first=min($senderUserId,$recipientUserId);
    $second=max($senderUserId,$recipientUserId);
    return 'ownership:'.(string)$instance['public_id'].':'.$first.':'.$second;
}

function mg_message_validate_conversation_key(string $conversationKey): string
{
    $conversationKey=trim($conversationKey);
    if($conversationKey===''||mb_strlen($conversationKey)>190||preg_match('/^[A-Za-z0-9:._-]+$/',$conversationKey)!==1){
        throw new InvalidArgumentException('A valid Microgift conversation key is required.');
    }
    return $conversationKey;
}

function mg_message_microgift_thread(PDO $pdo,array $instance,int $actorUserId,array $participants=[],?string $conversationKey=null): array
{
    $participants=mg_message_microgift_participants($instance,$participants);
    mg_message_require_microgift_participant($instance,$actorUserId,$participants);
    if(count($participants)<2)throw new RuntimeException('A Microgift conversation requires two participants.');

    $instanceId=(int)$instance['id'];
    $thread=false;
    if($conversationKey!==null){
        $conversationKey=mg_message_validate_conversation_key($conversationKey);
        $stmt=$pdo->prepare(
            'SELECT id,public_id,microgift_instance_id,conversation_key
             FROM message_threads
             WHERE microgift_instance_id=? AND conversation_key=?
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$instanceId,$conversationKey]);
        $thread=$stmt->fetch(PDO::FETCH_ASSOC);
    }else{
        $conversationKey=mg_message_validate_conversation_key('legacy:'.(string)$instance['public_id']);
        $pppmItemId=!empty($instance['pppm_item_id'])?(int)$instance['pppm_item_id']:null;
        $legacyGiftId=!empty($instance['legacy_gift_id'])?(int)$instance['legacy_gift_id']:null;
        $stmt=$pdo->prepare(
            "SELECT id,public_id,microgift_instance_id,conversation_key
             FROM message_threads
             WHERE (microgift_instance_id=? AND conversation_key LIKE 'legacy:%')
                OR (microgift_instance_id IS NULL AND pppm_item_id=?)
                OR (microgift_instance_id IS NULL AND gift_id=?)
             ORDER BY id ASC LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$instanceId,$pppmItemId,$legacyGiftId]);
        $thread=$stmt->fetch(PDO::FETCH_ASSOC);
        if($thread){
            if(empty($thread['microgift_instance_id'])){
                $pdo->prepare('UPDATE message_threads SET microgift_instance_id=?,conversation_key=?,updated_at=NOW() WHERE id=?')
                    ->execute([$instanceId,$conversationKey,(int)$thread['id']]);
                $thread['microgift_instance_id']=$instanceId;
                $thread['conversation_key']=$conversationKey;
            }else{
                $conversationKey=(string)$thread['conversation_key'];
            }
        }
    }

    if(!$thread){
        $publicId=mg_public_uuid();
        $pdo->prepare(
            'INSERT INTO message_threads
             (public_id,gift_id,pppm_item_id,microgift_instance_id,conversation_key,created_by_user_id,subject,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,NOW(),NOW())'
        )->execute([
            $publicId,
            !empty($instance['legacy_gift_id'])?(int)$instance['legacy_gift_id']:null,
            !empty($instance['pppm_item_id'])?(int)$instance['pppm_item_id']:null,
            $instanceId,
            $conversationKey,
            $actorUserId,
            mb_substr((string)($instance['title_snapshot']??'Microgift conversation'),0,160),
        ]);
        $thread=[
            'id'=>(int)$pdo->lastInsertId(),
            'public_id'=>$publicId,
            'microgift_instance_id'=>$instanceId,
            'conversation_key'=>$conversationKey,
        ];
    }

    $participant=$pdo->prepare('INSERT IGNORE INTO message_thread_participants (thread_id,user_id,joined_at) VALUES (?,?,NOW())');
    foreach($participants as $participantId){
        $participant->execute([(int)$thread['id'],$participantId]);
    }
    return $thread;
}

function mg_message_send_microgift(
    PDO $pdo,
    array $instance,
    int $senderUserId,
    int $recipientUserId,
    string $body,
    string $idempotencyKey,
    string $sourceReference,
    array $authorizedParticipants=[],
    ?string $conversationKey=null,
    string $messageType='message',
    bool $notify=true
): array {
    $body=mg_message_validate_body($body);
    if($idempotencyKey===''||mb_strlen($idempotencyKey)>190)throw new InvalidArgumentException('A valid message idempotency key is required.');
    if($sourceReference===''||mb_strlen($sourceReference)>190)throw new InvalidArgumentException('A valid message source reference is required.');
    if(!in_array($messageType,['message','send_note','follow_up'],true))throw new InvalidArgumentException('Invalid Microgift message type.');

    $participants=mg_message_microgift_participants($instance,$authorizedParticipants);
    if(!in_array($senderUserId,$participants,true))throw new RuntimeException('You cannot message participants for this Microgift.');
    if($recipientUserId<1||$recipientUserId===$senderUserId||!in_array($recipientUserId,$participants,true))throw new RuntimeException('Message recipient is not authorized for this Microgift.');

    if($conversationKey===null&&$authorizedParticipants===[]&&$messageType==='message'){
        $thread=mg_message_microgift_thread($pdo,$instance,$senderUserId,$participants,null);
        $conversationKey=(string)$thread['conversation_key'];
    }else{
        $conversationKey??=mg_message_conversation_key($pdo,$instance,$senderUserId,$recipientUserId);
        $thread=mg_message_microgift_thread($pdo,$instance,$senderUserId,$participants,$conversationKey);
    }
    $existing=$pdo->prepare('SELECT public_id,thread_id,recipient_user_id,body,source_reference,source_type FROM messages WHERE sender_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
    $existing->execute([$senderUserId,$idempotencyKey]);
    $row=$existing->fetch(PDO::FETCH_ASSOC);
    $sourceType=$messageType==='follow_up'?'action_center_follow_up':'action_center';
    if($row){
        $same=(int)$row['thread_id']===(int)$thread['id']
            && (int)$row['recipient_user_id']===$recipientUserId
            && hash_equals((string)$row['body'],$body)
            && (string)$row['source_reference']===$sourceReference
            && (string)$row['source_type']===$sourceType;
        if(!$same)throw new RuntimeException('Idempotency key is already bound to a different message request.');
        return [
            'thread_id'=>(string)$thread['public_id'],
            'message_id'=>(string)$row['public_id'],
            'recipient_user_id'=>$recipientUserId,
            'conversation_key'=>$conversationKey,
            'notification_id'=>null,
            'duplicate'=>true,
        ];
    }

    $messagePublicId=mg_public_uuid();
    $pdo->prepare('INSERT INTO messages (public_id,thread_id,sender_user_id,recipient_user_id,body,idempotency_key,source_type,source_reference,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([$messagePublicId,(int)$thread['id'],$senderUserId,$recipientUserId,$body,$idempotencyKey,$sourceType,$sourceReference]);
    $pdo->prepare('UPDATE message_threads SET updated_at=NOW() WHERE id=?')->execute([(int)$thread['id']]);
    $pdo->prepare('UPDATE message_thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int)$thread['id'],$senderUserId]);

    $notificationPublicId='';
    if($notify){
        $notificationType=$messageType==='follow_up'?'microgift_follow_up':'message';
        $notificationTitle=$messageType==='follow_up'?'A Microgift sender followed up':'New Microgift message';
        $notificationPublicId=mg_create_notification(
            $pdo,
            $recipientUserId,
            $notificationType,
            $notificationTitle,
            $body,
            '/inbox.php?thread='.rawurlencode((string)$thread['public_id']),
            [
                'actor_user_id'=>$senderUserId,
                'event_key'=>$notificationType.':'.$messagePublicId,
                'gift_id'=>!empty($instance['legacy_gift_id'])?(int)$instance['legacy_gift_id']:null,
                'pppm_item_id'=>!empty($instance['pppm_item_id'])?(int)$instance['pppm_item_id']:null,
                'thread_id'=>(int)$thread['id'],
                'microgift_instance_id'=>(string)$instance['public_id'],
                'message_id'=>$messagePublicId,
            ]
        );
    }

    $eventType=$messageType==='follow_up'?'microgift.follow_up_sent':'message.sent';
    $pdo->prepare('INSERT INTO microgift_events (public_id,instance_id,event_type,actor_user_id,source_type,source_reference,payload_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([
            mg_public_uuid(),
            (int)$instance['id'],
            $eventType,
            $senderUserId,
            $sourceType,
            $sourceReference,
            json_encode([
                'thread_id'=>$thread['public_id'],
                'message_id'=>$messagePublicId,
                'recipient_user_id'=>$recipientUserId,
                'conversation_key'=>$conversationKey,
                'message_type'=>$messageType,
            ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
        ]);

    return [
        'thread_id'=>(string)$thread['public_id'],
        'message_id'=>$messagePublicId,
        'recipient_user_id'=>$recipientUserId,
        'conversation_key'=>$conversationKey,
        'notification_id'=>$notificationPublicId?:null,
        'duplicate'=>false,
    ];
}
