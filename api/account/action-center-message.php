<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once dirname(__DIR__) . '/messages/_messaging.php';

function mg_action_center_message_wallet_action_id(string $actionItemId): ?string
{
    $value=trim($actionItemId);
    if(!str_starts_with($value,'wallet-'))return null;
    $walletId=strtolower(substr($value,7));
    return preg_match('/^[a-f0-9-]{36}$/',$walletId)===1?$walletId:null;
}

function mg_action_center_message_load_wallet_item(PDO $pdo,string $walletId,int $userId,string $userEmail): ?array
{
    $stmt=$pdo->prepare("SELECT wi.*,cc.email contact_email,c.public_id campaign_public_id,c.title campaign_title,rt.public_id reward_template_public_id,rt.title reward_template_title,u.display_name merchant_display_name,u.full_name merchant_full_name,u.email merchant_email
        FROM wallet_items wi
        LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id
        LEFT JOIN campaigns c ON c.id=wi.campaign_id
        LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id
        LEFT JOIN users u ON u.id=wi.merchant_user_id
        WHERE wi.public_id=? AND wi.status<>'cancelled'
          AND (wi.user_id=? OR (?<>'' AND (LOWER(cc.email)=? OR LOWER(wi.source_id)=?)))
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$walletId,$userId,$userEmail,$userEmail,$userEmail]);
    $item=$stmt->fetch(PDO::FETCH_ASSOC);
    return $item?:null;
}

function mg_action_center_message_existing_wallet_event(PDO $pdo,int $walletItemId,string $idempotencyKey): ?array
{
    $stmt=$pdo->prepare("SELECT public_id,event_context_json FROM campaign_events WHERE wallet_item_id=? AND event_type='wallet_item.message_sent' AND JSON_UNQUOTE(JSON_EXTRACT(event_context_json,'$.idempotency_key'))=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$walletItemId,$idempotencyKey]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_action_center_message_wallet_event(PDO $pdo,array $item,string $eventType,array $context=[]): string
{
    if(empty($item['campaign_id']))return '';
    $publicId=mg_public_uuid();
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([
            $publicId,
            (int)$item['merchant_user_id'],
            (int)$item['campaign_id'],
            (int)$item['id'],
            $item['contact_id']===null?null:(int)$item['contact_id'],
            $eventType,
            json_encode($context+['wallet_item_id'=>(string)$item['public_id']],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
        ]);
    return $publicId;
}

function mg_action_center_message_merchant_alert(PDO $pdo,int $merchantUserId,string $title,string $body,string $actionUrl,array $context): string
{
    if($merchantUserId<1)return '';
    return mg_create_operational_alert($pdo,$merchantUserId,'wallet_reward_message','info',$title,$body,$actionUrl,$context+['merchant_user_id'=>$merchantUserId]);
}

function mg_action_center_message_wallet_item(PDO $pdo,array $item,array $user,string $message,string $idempotencyKey,string $actionItemId): array
{
    $senderUserId=(int)$user['id'];
    $merchantUserId=(int)($item['merchant_user_id']??0);
    if($merchantUserId<1)throw new RuntimeException('Merchant recipient is unavailable.');
    if($merchantUserId===$senderUserId)throw new RuntimeException('You cannot message yourself for this reward.');
    if(!in_array((string)$item['status'],['claimed','redeemed'],true))throw new RuntimeException('Voucher messaging is available after the reward is claimed.');

    $existing=mg_action_center_message_existing_wallet_event($pdo,(int)$item['id'],$idempotencyKey);
    if($existing){
        $context=json_decode((string)($existing['event_context_json']??'{}'),true);
        if(!is_array($context))$context=[];
        return [
            'thread_id'=>(string)($context['thread_id']??''),
            'message_id'=>(string)($context['message_id']??$existing['public_id']),
            'recipient_user_id'=>$merchantUserId,
            'conversation_key'=>(string)($context['conversation_key']??('wallet:'.(string)$item['public_id'])),
            'notification_id'=>$context['notification_id']??null,
            'merchant_alert_id'=>$context['merchant_alert_id']??null,
            'duplicate'=>true,
        ];
    }

    $title=trim((string)($item['reward_template_title']??$item['title_snapshot']??'Wallet reward'));
    $conversationKey='wallet:'.(string)$item['public_id'];
    $messageId='wallet-message-'.mg_public_uuid();
    $body=$message;
    $context=[
        'actor_user_id'=>$senderUserId,
        'event_key'=>'wallet_reward_message:'.hash('sha256',$idempotencyKey.'|'.$actionItemId),
        'wallet_item_id'=>(string)$item['public_id'],
        'campaign_id'=>(string)($item['campaign_public_id']??''),
        'message_id'=>$messageId,
        'conversation_key'=>$conversationKey,
        'sender_user_id'=>$senderUserId,
        'recipient_user_id'=>$merchantUserId,
        'merchant_user_id'=>$merchantUserId,
    ];
    $notificationId=mg_create_notification($pdo,$merchantUserId,'wallet_reward_message','New message about '.$title,$body,'/inbox.php',$context);
    $merchantAlertId=mg_action_center_message_merchant_alert($pdo,$merchantUserId,'New reward message',$body,'/inbox.php',$context+['notification_id'=>$notificationId]);
    $eventId=mg_action_center_message_wallet_event($pdo,$item,'wallet_item.message_sent',$context+[
        'idempotency_key'=>$idempotencyKey,
        'notification_id'=>$notificationId,
        'merchant_alert_id'=>$merchantAlertId,
    ]);
    mg_event('wallet_item.message_sent',['wallet_item_id'=>(string)$item['public_id'],'campaign_event_id'=>$eventId,'notification_id'=>$notificationId,'merchant_alert_id'=>$merchantAlertId,'merchant_user_id'=>$merchantUserId],$senderUserId);
    return [
        'thread_id'=>$conversationKey,
        'message_id'=>$messageId,
        'recipient_user_id'=>$merchantUserId,
        'conversation_key'=>$conversationKey,
        'notification_id'=>$notificationId?:null,
        'merchant_alert_id'=>$merchantAlertId?:null,
        'duplicate'=>false,
    ];
}

function mg_action_center_message_instance_merchant_user(PDO $pdo,int $instanceId): int
{
    $stmt=$pdo->prepare("SELECT merchant_user_id FROM microgift_redemptions WHERE instance_id=? AND status='completed' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$instanceId]);
    return (int)($stmt->fetchColumn()?:0);
}

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

    $walletId=mg_action_center_message_wallet_action_id($actionItemId);
    if($walletId!==null){
        $walletItem=mg_action_center_message_load_wallet_item($pdo,$walletId,(int)$user['id'],strtolower(trim((string)($user['email']??''))));
        if(!$walletItem)throw new RuntimeException('Action Center wallet reward not found.');
        $walletResult=mg_action_center_message_wallet_item($pdo,$walletItem,$user,$message,$idempotencyKey,$actionItemId);
        $pdo->commit();
        mg_audit('action_center.wallet_reward_message_sent','wallet_item',[
            'wallet_item_id'=>$walletId,
            'message_id'=>$walletResult['message_id'],
            'merchant_user_id'=>$walletResult['recipient_user_id'],
            'notification_id'=>$walletResult['notification_id'],
            'merchant_alert_id'=>$walletResult['merchant_alert_id'],
            'duplicate'=>$walletResult['duplicate'],
        ],(int)$user['id']);
        mg_ok($walletResult+['status'=>'accepted'],$walletResult['duplicate']?'Existing message result returned.':'Message accepted.',$walletResult['duplicate']?200:202);
    }

    $stmt=$pdo->prepare("SELECT ac.folder,ac.sender_user_id action_sender_user_id,ac.recipient_user_id action_recipient_user_id,
            i.id,i.public_id,i.title_snapshot,i.issuer_user_id,i.owner_user_id,i.recipient_user_id instance_recipient_user_id,
            i.legacy_gift_id,i.pppm_item_id,i.status,i.claimed_at,i.redeemed_at
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
        'status'=>(string)$item['status'],
        'claimed_at'=>$item['claimed_at'],
        'redeemed_at'=>$item['redeemed_at'],
    ];

    $folder=(string)$item['folder'];
    $actionSenderUserId=(int)($item['action_sender_user_id']??0);
    $actionRecipientUserId=(int)($item['action_recipient_user_id']??0);
    if($folder==='sent'){
        if($actionSenderUserId!==(int)$user['id'])throw new RuntimeException('You are not the sender of this transfer.');
        if($actionRecipientUserId<1||(int)$instance['owner_user_id']!==$actionRecipientUserId){
            throw new RuntimeException('This recipient no longer owns the Microgift.');
        }
        $recipientUserId=$actionRecipientUserId;
    }else{
        if((int)$instance['owner_user_id']!==(int)$user['id'])throw new RuntimeException('You do not own this Microgift conversation.');
        $recipientUserId=$actionSenderUserId;
    }
    if($recipientUserId<1||$recipientUserId===(int)$user['id'])throw new RuntimeException('Message recipient is unavailable.');

    $requestedRecipientUserId=(int)($input['recipient_user_id']??0);
    if($requestedRecipientUserId>0&&$requestedRecipientUserId!==$recipientUserId){
        throw new RuntimeException('Message recipient does not match this transfer.');
    }

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
        'message',
        true
    );
    $merchantAlertId=null;
    $merchantUserId=mg_action_center_message_instance_merchant_user($pdo,(int)$instance['id']);
    if(empty($result['duplicate'])&&$merchantUserId>0&&$merchantUserId===$recipientUserId){
        $merchantAlertId=mg_action_center_message_merchant_alert($pdo,$merchantUserId,'New Microgift voucher message',$message,'/inbox.php',[
            'actor_user_id'=>(int)$user['id'],
            'message_id'=>$result['message_id'],
            'thread_id'=>$result['thread_id'],
            'microgift_instance_id'=>$instance['public_id'],
            'notification_id'=>$result['notification_id'],
        ]);
    }
    $stampLedger=null;
    $pdo->commit();

    mg_audit('action_center.message_sent','message_thread',[
        'thread_id'=>$result['thread_id'],
        'message_id'=>$result['message_id'],
        'instance_id'=>$instance['public_id'],
        'recipient_user_id'=>$result['recipient_user_id'],
        'conversation_key'=>$result['conversation_key'],
        'notification_id'=>$result['notification_id'],
        'merchant_alert_id'=>$merchantAlertId,
        'stamp_ledger_entry_id'=>null,
        'duplicate'=>$result['duplicate'],
    ],(int)$user['id']);
    mg_ok([
        'thread_id'=>$result['thread_id'],
        'message_id'=>$result['message_id'],
        'instance_id'=>$instance['public_id'],
        'recipient_user_id'=>$result['recipient_user_id'],
        'conversation_key'=>$result['conversation_key'],
        'notification_id'=>$result['notification_id'],
        'merchant_alert_id'=>$merchantAlertId,
        'stamp_ledger'=>$stampLedger,
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
