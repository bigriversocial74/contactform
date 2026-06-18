<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once dirname(__DIR__) . '/microgifts/_engine.php';
require_once dirname(__DIR__) . '/microgifts/_delivery.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';

mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$pdo=mg_db();

$actionItemId=trim((string)($input['action_item_id']??$input['id']??''));
$idempotencyKey=trim((string)($input['idempotency_key']??''));
if($actionItemId===''||$idempotencyKey===''){
    mg_fail('Action Center item and idempotency key are required.',422);
}
if(mb_strlen($idempotencyKey)>190)mg_fail('A valid idempotency key is required.',422);

try{
    $pdo->beginTransaction();

    $stmt=$pdo->prepare(
        "SELECT ac.public_id action_item_id,ac.folder,ac.sender_user_id action_sender_user_id,
                ac.recipient_user_id action_recipient_user_id,ac.sent_at original_sent_at,
                i.*,p.public_id pppm_public_id
         FROM microgift_inbox_items ac
         INNER JOIN microgift_instances i ON i.id=ac.instance_id
         LEFT JOIN pppm_items p ON p.id=i.pppm_item_id
         WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
         LIMIT 1 FOR UPDATE"
    );
    $stmt->execute([$actionItemId,(int)$user['id']]);
    $instance=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$instance)throw new RuntimeException('Sent gift not found.');
    if((string)$instance['folder']!=='sent')throw new RuntimeException('Only a gift in Sent can be resent.');
    if((int)$instance['action_sender_user_id']!==(int)$user['id'])throw new RuntimeException('You are not the sender of this gift.');

    $recipientUserId=(int)($instance['recipient_user_id']??$instance['action_recipient_user_id']??0);
    if($recipientUserId<1)throw new RuntimeException('The current recipient is unavailable.');
    if((int)$instance['owner_user_id']!==$recipientUserId){
        throw new RuntimeException('This gift is no longer owned by the recorded recipient.');
    }
    if(!in_array((string)$instance['status'],['issued','delivered'],true)||!empty($instance['claimed_at'])||!empty($instance['redeemed_at'])){
        throw new RuntimeException('Claimed, redeemed, expired, revoked, or otherwise closed gifts cannot be resent.');
    }

    $recipientStmt=$pdo->prepare("SELECT id FROM users WHERE id=? AND status='active' LIMIT 1");
    $recipientStmt->execute([$recipientUserId]);
    if(!(int)$recipientStmt->fetchColumn())throw new RuntimeException('The recipient is not currently available.');

    $deliveryEvent=mg_microgift_delivery_event(
        $pdo,$instance,'resent',(int)$user['id'],$recipientUserId,$idempotencyKey,$actionItemId,
        [
            'pppm_item_id'=>(string)($instance['pppm_public_id']??''),
            'original_sent_at'=>$instance['original_sent_at']??null,
            'reason'=>'sender_requested_redelivery',
        ]
    );

    if(empty($deliveryEvent['duplicate'])){
        $projection=mg_action_center_sent(
            $pdo,(int)$instance['id'],(int)$user['id'],$recipientUserId,
            [
                'occurred_at'=>$deliveryEvent['occurred_at'],
                'received_at'=>$deliveryEvent['occurred_at'],
                'sender_user_id'=>(int)$user['id'],
                'recipient_user_id'=>$recipientUserId,
            ]
        );
        $pdo->prepare(
            "UPDATE microgift_inbox_items
             SET read_at=NULL,updated_at=?
             WHERE instance_id=? AND user_id=? AND folder='inbox' AND archived_at IS NULL"
        )->execute([$deliveryEvent['occurred_at'],(int)$instance['id'],$recipientUserId]);
    }else{
        $projection=[
            'sent_item_id'=>$actionItemId,
            'recipient_inbox_item_id'=>null,
        ];
    }

    $summary=mg_microgift_delivery_summary($pdo,(int)$instance['id']);
    $pdo->commit();

    mg_audit('action_center.microgift_resent','microgift_instance',[
        'instance_id'=>$instance['public_id'],
        'pppm_item_id'=>$instance['pppm_public_id']??null,
        'action_item_id'=>$actionItemId,
        'recipient_user_id'=>$recipientUserId,
        'resent_at'=>$deliveryEvent['occurred_at'],
        'delivery_event_id'=>$deliveryEvent['event_id'],
        'duplicate'=>(bool)$deliveryEvent['duplicate'],
    ],(int)$user['id']);
    mg_event('microgift.resent',[
        'instance_id'=>$instance['public_id'],
        'recipient_user_id'=>$recipientUserId,
        'idempotency_key'=>$idempotencyKey,
        'resent_at'=>$deliveryEvent['occurred_at'],
        'duplicate'=>(bool)$deliveryEvent['duplicate'],
    ],(int)$user['id']);

    mg_ok([
        'instance_id'=>$instance['public_id'],
        'pppm_item_id'=>$instance['pppm_public_id']??null,
        'recipient_user_id'=>$recipientUserId,
        'status'=>(string)$instance['status'],
        'duplicate'=>(bool)$deliveryEvent['duplicate'],
        'delivery_event'=>$deliveryEvent,
        'delivery_summary'=>$summary,
        'action_center'=>$projection,
    ],!empty($deliveryEvent['duplicate'])?'Existing resend result returned.':'Gift resent to the current recipient.',!empty($deliveryEvent['duplicate'])?200:201);
}catch(InvalidArgumentException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),422);
}catch(RuntimeException $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_fail($error->getMessage(),409);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','action_center.resend_failed','Action Center resend failed.',[
        'exception'=>$error->getMessage(),
    ],(int)$user['id']);
    mg_fail('Unable to resend this Microgift.',500);
}
