<?php
declare(strict_types=1);

require_once __DIR__ . '/_engine.php';
require_once dirname(__DIR__) . '/pppm/_pppm.php';

function mg_microgift_delivery_event(PDO $pdo,array $instance,string $eventType,int $senderUserId,int $recipientUserId,string $idempotencyKey,?string $actionItemPublicId=null,array $metadata=[]): array
{
    if(!in_array($eventType,['sent','resent','delivered'],true)){
        throw new InvalidArgumentException('Invalid Microgift delivery event type.');
    }
    if($senderUserId<1||$recipientUserId<1||trim($idempotencyKey)===''){
        throw new InvalidArgumentException('Valid sender, recipient, and idempotency key are required.');
    }
    if(mb_strlen($idempotencyKey)>190){
        throw new InvalidArgumentException('A valid idempotency key is required.');
    }

    $stmt=$pdo->prepare('SELECT * FROM microgift_delivery_events WHERE idempotency_key=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$idempotencyKey]);
    $existing=$stmt->fetch(PDO::FETCH_ASSOC);
    if($existing){
        $same=(int)$existing['instance_id']===(int)$instance['id']
            &&(string)$existing['event_type']===$eventType
            &&(int)$existing['sender_user_id']===$senderUserId
            &&(int)$existing['recipient_user_id']===$recipientUserId;
        if(!$same)throw new RuntimeException('Idempotency key is already bound to a different delivery event.');
        return [
            'event_id'=>(string)$existing['public_id'],
            'event_type'=>(string)$existing['event_type'],
            'occurred_at'=>(string)$existing['occurred_at'],
            'duplicate'=>true,
        ];
    }

    $occurredAt=(string)$pdo->query('SELECT NOW()')->fetchColumn();
    $publicId=mg_microgift_uuid();
    $pdo->prepare(
        'INSERT INTO microgift_delivery_events
         (public_id,instance_id,pppm_item_id,event_type,sender_user_id,recipient_user_id,
          action_item_public_id,idempotency_key,occurred_at,metadata_json,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,NOW())'
    )->execute([
        $publicId,(int)$instance['id'],isset($instance['pppm_item_id'])?(int)$instance['pppm_item_id']:null,
        $eventType,$senderUserId,$recipientUserId,$actionItemPublicId,$idempotencyKey,$occurredAt,
        mg_microgift_json($metadata),
    ]);

    mg_microgift_event(
        $pdo,
        'microgift.'.$eventType,
        (int)$instance['id'],
        isset($instance['template_id'])?(int)$instance['template_id']:null,
        $senderUserId,
        'action_center',
        $idempotencyKey,
        array_merge($metadata,[
            'delivery_event_id'=>$publicId,
            'sender_user_id'=>$senderUserId,
            'recipient_user_id'=>$recipientUserId,
            'occurred_at'=>$occurredAt,
        ])
    );

    return [
        'event_id'=>$publicId,
        'event_type'=>$eventType,
        'occurred_at'=>$occurredAt,
        'duplicate'=>false,
    ];
}

function mg_microgift_delivery_summary(PDO $pdo,int $instanceId): array
{
    $stmt=$pdo->prepare(
        "SELECT
           MIN(CASE WHEN event_type='sent' THEN occurred_at END) AS first_sent_at,
           MAX(CASE WHEN event_type='resent' THEN occurred_at END) AS last_resent_at,
           SUM(CASE WHEN event_type='resent' THEN 1 ELSE 0 END) AS resend_count,
           MAX(occurred_at) AS last_delivery_event_at
         FROM microgift_delivery_events
         WHERE instance_id=?"
    );
    $stmt->execute([$instanceId]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC)?:[];
    return [
        'first_sent_at'=>$row['first_sent_at']??null,
        'last_resent_at'=>$row['last_resent_at']??null,
        'resend_count'=>(int)($row['resend_count']??0),
        'last_delivery_event_at'=>$row['last_delivery_event_at']??null,
    ];
}
