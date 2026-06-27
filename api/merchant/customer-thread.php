<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/messages/_messaging.php';
require_once dirname(__DIR__) . '/communications/_communications.php';

function mg_merchant_customer_thread_wallet_id(string $raw): string
{
    $raw=trim($raw);
    if(str_starts_with($raw,'wallet:'))$raw=substr($raw,7);
    if(preg_match('/^[0-9a-f-]{36}$/i',$raw)!==1)mg_fail('A valid wallet reward thread is required.',422);
    return strtolower($raw);
}

function mg_merchant_customer_thread_load_wallet(PDO $pdo,int $merchantUserId,string $walletId,bool $forUpdate=false): array
{
    $sql="SELECT wi.*,cc.public_id contact_public_id,cc.name contact_name,cc.email contact_email,cc.user_id contact_user_id,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type,rt.title reward_template_title,COALESCE(NULLIF(cu.display_name,''),NULLIF(cu.full_name,''),cu.email,cc.name,cc.email) customer_name,cu.email customer_email
        FROM wallet_items wi
        LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id
        LEFT JOIN campaigns c ON c.id=wi.campaign_id
        LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id
        LEFT JOIN users cu ON cu.id=COALESCE(wi.user_id,cc.user_id)
        WHERE wi.public_id=? AND wi.merchant_user_id=? AND wi.status<>'cancelled'
        LIMIT 1".($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);
    $stmt->execute([$walletId,$merchantUserId]);
    $wallet=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$wallet)mg_fail('Wallet reward thread not found.',404);
    return $wallet;
}

function mg_merchant_customer_thread_customer_user_id(array $wallet): int
{
    return (int)($wallet['user_id']??0) ?: (int)($wallet['contact_user_id']??0);
}

function mg_merchant_customer_thread_event_message(array $event,int $merchantUserId,array $wallet): array
{
    $ctx=json_decode((string)($event['event_context_json']??'{}'),true);
    if(!is_array($ctx))$ctx=[];
    $eventType=(string)($event['event_type']??'');
    $sender=(int)($ctx['sender_user_id']??0);
    if($sender<1)$sender=$eventType==='wallet_item.merchant_reply_sent'?$merchantUserId:mg_merchant_customer_thread_customer_user_id($wallet);
    $body=trim((string)($ctx['body']??$ctx['message']??$ctx['reply']??''));
    if($body==='')$body=(string)($eventType==='wallet_item.merchant_reply_sent'?'Merchant reply':'Customer message');
    return [
        'id'=>(string)($ctx['message_id']??$event['public_id']),
        'source_event_id'=>(string)$event['public_id'],
        'body'=>$body,
        'sender_user_id'=>$sender,
        'sender_name'=>$sender===$merchantUserId?'Merchant':(string)($wallet['customer_name']?:'Customer'),
        'mine'=>$sender===$merchantUserId,
        'event_type'=>$eventType,
        'created_at'=>$event['created_at']??null,
    ];
}

function mg_merchant_customer_thread_response(PDO $pdo,array $wallet,int $merchantUserId): array
{
    $stmt=$pdo->prepare("SELECT public_id,event_type,event_context_json,created_at FROM campaign_events WHERE wallet_item_id=? AND event_type IN ('wallet_item.message_sent','wallet_item.merchant_reply_sent') ORDER BY created_at ASC,id ASC");
    $stmt->execute([(int)$wallet['id']]);
    $messages=array_map(fn($row)=>mg_merchant_customer_thread_event_message($row,$merchantUserId,$wallet),$stmt->fetchAll(PDO::FETCH_ASSOC));
    return [
        'thread'=>[
            'id'=>'wallet:'.(string)$wallet['public_id'],
            'wallet_item_id'=>(string)$wallet['public_id'],
            'subject'=>(string)($wallet['reward_template_title']??$wallet['title_snapshot']??'Wallet reward conversation'),
            'customer_user_id'=>mg_merchant_customer_thread_customer_user_id($wallet),
            'customer_name'=>(string)($wallet['customer_name']?:'Customer'),
            'customer_email'=>(string)($wallet['customer_email']?:$wallet['contact_email']?:''),
            'campaign_id'=>(string)($wallet['campaign_public_id']??''),
            'campaign_title'=>(string)($wallet['campaign_title']??''),
            'campaign_type'=>(string)($wallet['campaign_type']??''),
            'status'=>(string)($wallet['status']??''),
            'messages'=>$messages,
        ],
    ];
}

function mg_merchant_customer_thread_existing_reply(PDO $pdo,int $walletItemDbId,string $idempotencyKey): ?array
{
    $stmt=$pdo->prepare("SELECT public_id,event_context_json FROM campaign_events WHERE wallet_item_id=? AND event_type='wallet_item.merchant_reply_sent' AND JSON_UNQUOTE(JSON_EXTRACT(event_context_json,'$.idempotency_key'))=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$walletItemDbId,$idempotencyKey]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row?:null;
}

function mg_merchant_customer_thread_send_reply(PDO $pdo,array $wallet,int $merchantUserId,string $body,string $idempotencyKey): array
{
    $customerUserId=mg_merchant_customer_thread_customer_user_id($wallet);
    if($customerUserId<1)throw new RuntimeException('This wallet reward is not linked to a customer account yet.');
    if($customerUserId===$merchantUserId)throw new RuntimeException('You cannot reply to yourself.');
    $body=mg_message_validate_body($body);
    if($idempotencyKey===''||mb_strlen($idempotencyKey)>190)throw new InvalidArgumentException('A valid reply idempotency key is required.');
    $existing=mg_merchant_customer_thread_existing_reply($pdo,(int)$wallet['id'],$idempotencyKey);
    if($existing){
        $ctx=json_decode((string)($existing['event_context_json']??'{}'),true);
        if(!is_array($ctx))$ctx=[];
        return ['message_id'=>(string)($ctx['message_id']??$existing['public_id']),'notification_id'=>$ctx['notification_id']??null,'duplicate'=>true];
    }
    $messageId='wallet-reply-'.mg_public_uuid();
    $context=[
        'wallet_item_id'=>(string)$wallet['public_id'],
        'campaign_id'=>(string)($wallet['campaign_public_id']??''),
        'contact_id'=>(string)($wallet['contact_public_id']??''),
        'sender_user_id'=>$merchantUserId,
        'recipient_user_id'=>$customerUserId,
        'merchant_user_id'=>$merchantUserId,
        'message_id'=>$messageId,
        'conversation_key'=>'wallet:'.(string)$wallet['public_id'],
        'body'=>$body,
        'idempotency_key'=>$idempotencyKey,
    ];
    $notificationId=mg_create_notification($pdo,$customerUserId,'wallet_reward_reply','Merchant replied to your reward message',$body,'/inbox.php',$context+['actor_user_id'=>$merchantUserId,'event_key'=>'wallet_reward_reply:'.$messageId]);
    $eventId=mg_public_uuid();
    $pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
        ->execute([$eventId,$merchantUserId,(int)$wallet['campaign_id'],(int)$wallet['id'],$wallet['contact_id']===null?null:(int)$wallet['contact_id'],'wallet_item.merchant_reply_sent',json_encode($context+['notification_id'=>$notificationId],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
    mg_event('wallet_item.merchant_reply_sent',['wallet_item_id'=>(string)$wallet['public_id'],'message_id'=>$messageId,'notification_id'=>$notificationId,'customer_user_id'=>$customerUserId],$merchantUserId);
    return ['message_id'=>$messageId,'notification_id'=>$notificationId?:null,'duplicate'=>false];
}

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
$user=$method==='POST'?mg_require_permission('merchant.campaigns.manage'):mg_require_permission('merchant.campaigns.view');
$pdo=mg_db();
$workspace=mg_merchant_ensure_workspace($pdo,$user);
$merchantUserId=(int)$workspace['merchant_user_id'];

if($method==='GET'){
    $walletId=mg_merchant_customer_thread_wallet_id((string)($_GET['item']??$_GET['wallet_item_id']??$_GET['thread']??''));
    $wallet=mg_merchant_customer_thread_load_wallet($pdo,$merchantUserId,$walletId,false);
    mg_ok(mg_merchant_customer_thread_response($pdo,$wallet,$merchantUserId));
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();
mg_require_csrf_for_write($input);
$walletId=mg_merchant_customer_thread_wallet_id((string)($input['wallet_item_id']??$input['thread_id']??$input['thread']??''));
$body=(string)($input['body']??$input['message']??'');
$idempotencyKey=trim((string)($input['idempotency_key']??''));
try{
    $pdo->beginTransaction();
    $wallet=mg_merchant_customer_thread_load_wallet($pdo,$merchantUserId,$walletId,true);
    $reply=mg_merchant_customer_thread_send_reply($pdo,$wallet,$merchantUserId,$body,$idempotencyKey);
    $thread=mg_merchant_customer_thread_response($pdo,$wallet,$merchantUserId);
    $pdo->commit();
    mg_ok($reply+$thread,'Merchant reply sent.',$reply['duplicate']?200:201);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(RuntimeException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),409);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','merchant.customer_thread.reply_failed','Merchant customer reply failed.',['exception_class'=>$e::class,'message'=>$e->getMessage()],$merchantUserId);mg_fail('Unable to send merchant reply.',500);}