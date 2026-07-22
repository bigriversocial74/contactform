<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/gifts/_gift.php';
require_once __DIR__ . '/_crm_ops.php';

function mg_messages_source_type(?string $sourceType, ?string $conversationKey = null, ?string $pppmId = null): string
{
    $sourceType=trim((string)$sourceType);$conversationKey=trim((string)$conversationKey);
    if($sourceType!==''&&$sourceType!=='messaging')return $sourceType;
    if($conversationKey!==''&&str_starts_with($conversationKey,'social_direct:'))return 'social_chat';
    if($conversationKey!==''&&str_starts_with($conversationKey,'store_canvas:'))return 'store_canvas_direct';
    if($conversationKey!==''&&str_starts_with($conversationKey,'crm:'))return 'merchant_crm_message';
    if($conversationKey!==''&&str_starts_with($conversationKey,'creator_campaign:'))return 'creator_campaign_message';
    if(trim((string)$pppmId)!=='')return 'pppm_message';
    return $sourceType!==''?$sourceType:'messaging';
}

function mg_messages_source_label(string $sourceType): string
{
    return match($sourceType){
        'social_chat'=>'Feed Chat','store_canvas_direct'=>'Store Canvas','store_canvas_reply'=>'Store Canvas Reply',
        'merchant_crm_message','merchant_crm'=>'Merchant CRM','creator_campaign_message','creator_campaign_system'=>'Creator Campaign',
        'action_center_follow_up'=>'Action Center Follow Up','action_center'=>'Action Center','pppm_message'=>'PPPM / IN-OUT Box','messaging'=>'Messages',
        default=>ucwords(str_replace(['_','-'],' ',$sourceType)),
    };
}

function mg_messages_source_system(string $sourceType): string
{
    if($sourceType==='social_chat')return 'social_feed';
    if(str_starts_with($sourceType,'store_canvas'))return 'store_canvas';
    if($sourceType==='merchant_crm_message'||$sourceType==='merchant_crm')return 'merchant_crm';
    if(str_starts_with($sourceType,'creator_campaign'))return 'creator_campaigns';
    if(str_starts_with($sourceType,'action_center')||$sourceType==='pppm_message')return 'in_out_box';
    return 'messages';
}

function mg_messages_source_context(PDO $pdo,string $sourceType,string $sourceReference,int $threadId=0):array
{
    if($sourceType==='social_chat'&&$sourceReference!=='')return ['conversation_key'=>$sourceReference,'source_label'=>'Feed Chat'];
    if(str_starts_with($sourceType,'creator_campaign')){
        $stmt=$pdo->prepare(
            "SELECT c.public_id campaign_public_id,c.title campaign_name,p.public_id participant_public_id,
                    COALESCE(NULLIF(u.display_name,''),NULLIF(u.full_name,''),u.email) creator_name,
                    mc.public_id message_context_public_id,mc.status thread_status
             FROM creator_campaign_message_contexts mc
             INNER JOIN creator_campaigns c ON c.id=mc.campaign_id
             INNER JOIN creator_campaign_participants p ON p.id=mc.participant_id
             INNER JOIN users u ON u.id=mc.creator_user_id
             WHERE mc.thread_id=? OR mc.public_id=? OR p.public_id=? LIMIT 1"
        );
        $stmt->execute([$threadId,$sourceReference,$sourceReference]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row)return [];
        return ['campaign_id'=>(string)$row['campaign_public_id'],'campaign_title'=>(string)$row['campaign_name'],'participant_id'=>(string)$row['participant_public_id'],'creator_name'=>(string)$row['creator_name'],'message_context_id'=>(string)$row['message_context_public_id'],'thread_status'=>(string)$row['thread_status']];
    }
    if(!in_array($sourceType,['merchant_crm_message','merchant_crm'],true)||$sourceReference==='')return [];
    $stmt=$pdo->prepare(
        "SELECT cc.public_id contact_public_id,cc.email,cc.name,cc.source contact_source,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type,
                COALESCE(NULLIF(mp.display_name,''),NULLIF(mu.display_name,''),mu.email) merchant_name
         FROM campaign_contacts cc INNER JOIN campaigns c ON c.id=cc.campaign_id INNER JOIN users mu ON mu.id=cc.merchant_user_id
         LEFT JOIN public_profiles mp ON mp.user_id=cc.merchant_user_id WHERE cc.public_id=? LIMIT 1"
    );
    $stmt->execute([$sourceReference]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)return [];
    return ['contact_id'=>(string)$row['contact_public_id'],'contact_email'=>(string)$row['email'],'contact_name'=>(string)($row['name']??''),'contact_source'=>(string)($row['contact_source']??''),'campaign_id'=>(string)$row['campaign_public_id'],'campaign_title'=>(string)$row['campaign_title'],'campaign_type'=>(string)$row['campaign_type'],'merchant_name'=>(string)($row['merchant_name']??'Merchant')];
}

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');$user=mg_require_permission('gift.message.send');$id=trim((string)($_GET['id']??''));
if($id===''||strlen($id)!==36||preg_match('/^[a-f0-9-]{36}$/i',$id)!==1)mg_fail('Invalid thread identifier.',422);
$pdo=mg_db();
$stmt=$pdo->prepare('SELECT mt.id,mt.public_id,mt.subject,mt.gift_id,mt.pppm_item_id,mt.conversation_key,g.public_id gift_public_id,g.title gift_title,pi.public_id pppm_public_id FROM message_threads mt INNER JOIN message_thread_participants mtp ON mtp.thread_id=mt.id LEFT JOIN gifts g ON g.id=mt.gift_id LEFT JOIN pppm_items pi ON pi.id=mt.pppm_item_id WHERE mt.public_id=? AND mtp.user_id=? LIMIT 1');
$stmt->execute([strtolower($id),(int)$user['id']]);$thread=$stmt->fetch(PDO::FETCH_ASSOC);if(!$thread)mg_fail('Thread not found.',404);
if($method==='GET'){
    $messagesStmt=$pdo->prepare("SELECT m.public_id,m.body,m.created_at,m.sender_user_id,m.recipient_user_id,m.source_type,m.source_reference,u.display_name sender_name,u.full_name sender_full_name FROM messages m INNER JOIN users u ON u.id=m.sender_user_id WHERE m.thread_id=? AND COALESCE(m.moderation_status,'clear') NOT IN('hidden','removed') ORDER BY m.created_at ASC,m.id ASC");
    $messagesStmt->execute([(int)$thread['id']]);
    $messages=array_map(static function(array $row)use($user,$thread):array{$sourceType=mg_messages_source_type(isset($row['source_type'])?(string)$row['source_type']:null,isset($thread['conversation_key'])?(string)$thread['conversation_key']:null,isset($thread['pppm_public_id'])?(string)$thread['pppm_public_id']:null);return ['id'=>(string)$row['public_id'],'body'=>(string)$row['body'],'sender_id'=>(int)$row['sender_user_id'],'recipient_id'=>$row['recipient_user_id']!==null?(int)$row['recipient_user_id']:null,'sender_name'=>trim((string)($row['sender_name']??$row['sender_full_name']??'Microgifter user')),'created_at'=>$row['created_at']??null,'mine'=>(int)$row['sender_user_id']===(int)$user['id'],'source'=>['type'=>$sourceType,'label'=>mg_messages_source_label($sourceType),'system'=>mg_messages_source_system($sourceType),'reference'=>isset($row['source_reference'])?(string)$row['source_reference']:'']];},$messagesStmt->fetchAll(PDO::FETCH_ASSOC));
    $latest=$messages!==[]?$messages[array_key_last($messages)]:null;$latestSourceType=$latest?(string)($latest['source']['type']??'messaging'):mg_messages_source_type(null,(string)($thread['conversation_key']??''),(string)($thread['pppm_public_id']??''));$latestReference=$latest?(string)($latest['source']['reference']??''):'';
    if($latestReference===''&&$latestSourceType==='social_chat')$latestReference=(string)($thread['conversation_key']??'');
    if($latestReference===''&&str_starts_with($latestSourceType,'creator_campaign'))$latestReference=trim(substr((string)($thread['conversation_key']??''),strlen('creator_campaign:')));
    $sourceContext=mg_messages_source_context($pdo,$latestSourceType,$latestReference,(int)$thread['id']);$crmOps=mg_message_crm_ops_get($pdo,(int)$thread['id'],(int)$user['id']);
    $pdo->prepare('UPDATE message_thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int)$thread['id'],(int)$user['id']]);
    mg_ok(['thread'=>['id'=>(string)$thread['public_id'],'subject'=>(string)($thread['subject']??$thread['gift_title']??'Conversation'),'gift_id'=>$thread['gift_public_id']!==null?(string)$thread['gift_public_id']:null,'pppm_id'=>$thread['pppm_public_id']!==null?(string)$thread['pppm_public_id']:null,'conversation_key'=>(string)($thread['conversation_key']??''),'source'=>['type'=>$latestSourceType,'label'=>mg_messages_source_label($latestSourceType),'system'=>mg_messages_source_system($latestSourceType),'reference'=>$latestReference,'context'=>$sourceContext],'crm_ops'=>$crmOps,'messages'=>$messages]]);
}
mg_fail('Method not allowed.',405);
