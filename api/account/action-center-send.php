<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once dirname(__DIR__) . '/microgifts/_lifecycle.php';
require_once dirname(__DIR__) . '/microgifts/_action_center_projection.php';
require_once dirname(__DIR__) . '/microgifts/_delivery.php';
require_once dirname(__DIR__) . '/pppm/_ownership.php';
require_once dirname(__DIR__) . '/communications/_communications.php';
require_once dirname(__DIR__) . '/messages/_messaging.php';
require_once dirname(__DIR__) . '/stamps/_stamps.php';

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

function mg_action_center_wallet_action_id(string $actionItemId): ?string
{
    $value=trim($actionItemId);
    if(!str_starts_with($value,'wallet-'))return null;
    $walletId=strtolower(substr($value,7));
    return preg_match('/^[a-f0-9-]{36}$/',$walletId)===1?$walletId:null;
}

function mg_action_center_load_wallet_item_for_user(PDO $pdo,string $walletId,int $userId,string $userEmail): ?array
{
    $stmt=$pdo->prepare("SELECT wi.*,cc.email contact_email,c.public_id campaign_public_id,rt.public_id reward_template_public_id,rt.title reward_template_title,rt.description reward_template_description,rt.redemption_instructions
        FROM wallet_items wi
        LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id
        LEFT JOIN campaigns c ON c.id=wi.campaign_id
        LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id
        WHERE wi.public_id=? AND wi.status<>'cancelled'
          AND (wi.user_id=? OR (?<>'' AND (LOWER(cc.email)=? OR LOWER(wi.source_id)=?)))
        LIMIT 1 FOR UPDATE");
    $stmt->execute([$walletId,$userId,$userEmail,$userEmail,$userEmail]);
    $item=$stmt->fetch(PDO::FETCH_ASSOC);
    return $item?:null;
}

function mg_action_center_wallet_event(PDO $pdo,array $item,string $eventType,array $context=[]): void
{
    if(empty($item['campaign_id']))return;
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([
            mg_microgift_uuid(),
            (int)$item['merchant_user_id'],
            (int)$item['campaign_id'],
            (int)$item['id'],
            $item['contact_id']===null?null:(int)$item['contact_id'],
            $eventType,
            mg_microgift_json($context+['wallet_item_id'=>(string)$item['public_id']]),
        ]);
}

function mg_action_center_send_wallet_item(PDO $pdo,array $item,array $sender,int $recipientUserId,string $actionItemId,string $idempotencyKey,string $message): array
{
    $senderUserId=(int)$sender['id'];
    $status=(string)($item['status']??'issued');
    if(!empty($item['expires_at'])&&strtotime((string)$item['expires_at'])<time()&&!in_array($status,['redeemed','expired','cancelled'],true)){
        $pdo->prepare("UPDATE wallet_items SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$item['id']]);
        mg_action_center_wallet_event($pdo,$item,'wallet_item.expired',['action_item_id'=>$actionItemId]);
        throw new RuntimeException('Wallet item has expired.');
    }
    if(in_array($status,['redeemed','expired','cancelled'],true))throw new RuntimeException('Wallet item cannot be transferred.');
    if($recipientUserId===$senderUserId)throw new RuntimeException('Choose another recipient for this Microgift.');

    $title=trim((string)($item['title_snapshot']??$item['reward_template_title']??'Microgifter reward'));
    $occurredAt=date('Y-m-d H:i:s');
    $pdo->prepare("UPDATE wallet_items SET user_id=?,contact_id=NULL,source_id=NULL,status='issued',viewed_at=NULL,claimed_at=NULL,updated_at=? WHERE id=?")
        ->execute([$recipientUserId,$occurredAt,(int)$item['id']]);
    mg_action_center_wallet_event($pdo,$item,'wallet_item.regifted',[
        'action_item_id'=>$actionItemId,
        'idempotency_key'=>$idempotencyKey,
        'sender_user_id'=>$senderUserId,
        'recipient_user_id'=>$recipientUserId,
        'message'=>$message,
    ]);

    $senderLabel=mg_notification_user_label($pdo,$senderUserId);
    $notificationId=mg_create_notification(
        $pdo,
        $recipientUserId,
        'microgift_received',
        $senderLabel.' sent you a Microgift',
        $message!==''?$message:($title!==''?$title:'Open your Microgift.'),
        '/inbox.php?item='.rawurlencode($actionItemId),
        [
            'actor_user_id'=>$senderUserId,
            'event_key'=>'wallet_item_regifted:'.hash('sha256',$idempotencyKey.'|'.$actionItemId),
            'wallet_item_id'=>(string)$item['public_id'],
            'sender_user_id'=>$senderUserId,
            'recipient_user_id'=>$recipientUserId,
        ]
    );

    return [
        'instance_id'=>$actionItemId,
        'wallet_item_id'=>(string)$item['public_id'],
        'recipient_user_id'=>$recipientUserId,
        'status'=>'sent',
        'duplicate'=>false,
        'delivery_event'=>[
            'event_id'=>'wallet-'.hash('sha256',$idempotencyKey.'|'.$actionItemId),
            'event_type'=>'sent',
            'occurred_at'=>$occurredAt,
            'duplicate'=>false,
        ],
        'delivery_summary'=>[
            'sent_at'=>$occurredAt,
            'recipient_user_id'=>$recipientUserId,
        ],
        'notification_id'=>$notificationId,
        'action_center'=>[
            'sent_item_id'=>null,
            'recipient_inbox_item_id'=>$actionItemId,
        ],
    ];
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

    $recipientUserId=mg_action_center_resolve_recipient($pdo,$recipientReference);
    if($recipientUserId<1)throw new RuntimeException('Recipient not found.');

    $walletId=mg_action_center_wallet_action_id($actionItemId);
    if($walletId!==null){
        $walletItem=mg_action_center_load_wallet_item_for_user($pdo,$walletId,(int)$user['id'],strtolower(trim((string)($user['email']??''))));
        if(!$walletItem)throw new RuntimeException('Action Center item not found.');
        $stampLedger=mg_stamp_debit_send($pdo,(int)$user['id'],(int)$user['id'],'regift_send',$idempotencyKey,[
            'source_type'=>'action_center_wallet_regift',
            'source_id'=>$walletId,
            'reference'=>$actionItemId,
            'metadata'=>[
                'wallet_item_id'=>$walletId,
                'recipient_user_id'=>$recipientUserId,
            ],
        ]);
        $result=mg_action_center_send_wallet_item($pdo,$walletItem,$user,$recipientUserId,$actionItemId,$idempotencyKey,$message);
        $result['stamp_ledger']=$stampLedger;
        $pdo->commit();
        mg_audit('action_center.wallet_item_regifted','wallet_item',[
            'wallet_item_id'=>$walletId,
            'action_item_id'=>$actionItemId,
            'recipient_user_id'=>$recipientUserId,
            'sent_at'=>$result['delivery_event']['occurred_at'],
            'notification_id'=>$result['notification_id'],
            'stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id']??null,
        ],(int)$user['id']);
        mg_event('wallet_item.regifted',[
            'wallet_item_id'=>$walletId,
            'recipient_user_id'=>$recipientUserId,
            'idempotency_key'=>$idempotencyKey,
            'sent_at'=>$result['delivery_event']['occurred_at'],
            'notification_id'=>$result['notification_id'],
            'stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id']??null,
        ],(int)$user['id']);
        mg_ok($result,'Wallet reward regifted.',201);
    }

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

    $stampLedger=mg_stamp_debit_send($pdo,(int)$user['id'],(int)$user['id'],'regift_send',$idempotencyKey,[
        'source_type'=>'action_center_regift',
        'source_id'=>(string)$instance['public_id'],
        'reference'=>$actionItemId,
        'metadata'=>[
            'microgift_instance_id'=>(string)$instance['public_id'],
            'pppm_item_id'=>$pppmPublicId,
            'recipient_user_id'=>$recipientUserId,
        ],
    ]);

    $transfer=mg_pppm_transfer_owner_canonical(
        $pdo,
        $pppmPublicId,
        $recipientUserId,
        'action_center_regift',
        $idempotencyKey,
        (int)$user['id'],
        ['microgift_instance_id'=>(string)$instance['public_id'],'action_item_id'=>$actionItemId,'stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id']??null]
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
        ['pppm_item_id'=>$pppmPublicId,'transfer_id'=>$transfer['transfer_id']??null,'transfer_type'=>'regift','stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id']??null]
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
        'stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id']??null,
        'original_issuer_user_id'=>$instance['issuer_user_id']??null,
        'duplicate'=>$duplicate,
    ],(int)$user['id']);
    mg_event('microgift.regifted',[
        'instance_id'=>$instance['public_id'],
        'recipient_user_id'=>$recipientUserId,
        'idempotency_key'=>$idempotencyKey,
        'sent_at'=>$deliveryEvent['occurred_at'],
        'notification_id'=>$notificationId,
        'stamp_ledger_entry_id'=>$stampLedger['entry']['entry_id']??null,
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
        'stamp_ledger'=>$stampLedger,
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
