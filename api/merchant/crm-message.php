<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/gifts/_gift.php';
require_once dirname(__DIR__) . '/communications/_communications.php';
require_once dirname(__DIR__) . '/communications/_delivery.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm.php';
require_once dirname(__DIR__, 2) . '/includes/mail.php';

function mg_crm_message_uuid(): string { return mg_public_uuid(); }
function mg_crm_message_event(PDO $pdo,array $contact,string $type,array $context=[]): void
{
    $stmt=$pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([mg_crm_message_uuid(),(int)$contact['merchant_user_id'],(int)$contact['campaign_id'],null,(int)$contact['id'],$type,json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
}
function mg_crm_message_thread(PDO $pdo,int $merchantId,int $recipientId,array $contact): array
{
    $key='crm:'.(string)$contact['public_id'].':'.min($merchantId,$recipientId).':'.max($merchantId,$recipientId);
    $hasKey=(bool)$pdo->query("SHOW COLUMNS FROM message_threads LIKE 'conversation_key'")->fetch();
    if($hasKey){$stmt=$pdo->prepare('SELECT * FROM message_threads WHERE conversation_key=? LIMIT 1 FOR UPDATE');$stmt->execute([$key]);$thread=$stmt->fetch(PDO::FETCH_ASSOC);}
    else{$stmt=$pdo->prepare('SELECT mt.* FROM message_threads mt INNER JOIN message_thread_participants a ON a.thread_id=mt.id AND a.user_id=? INNER JOIN message_thread_participants b ON b.thread_id=mt.id AND b.user_id=? WHERE mt.gift_id IS NULL ORDER BY mt.id DESC LIMIT 1 FOR UPDATE');$stmt->execute([$merchantId,$recipientId]);$thread=$stmt->fetch(PDO::FETCH_ASSOC);}
    if(!$thread){$public=mg_crm_message_uuid();$subject=mb_substr('CRM: '.((string)($contact['name']??$contact['email']??'Customer')),0,160);if($hasKey){$pdo->prepare('INSERT INTO message_threads (public_id,gift_id,created_by_user_id,subject,conversation_key,created_at,updated_at) VALUES (?,NULL,?,?,?,NOW(),NOW())')->execute([$public,$merchantId,$subject,$key]);}else{$pdo->prepare('INSERT INTO message_threads (public_id,gift_id,created_by_user_id,subject,created_at,updated_at) VALUES (?,NULL,?,?,NOW(),NOW())')->execute([$public,$merchantId,$subject]);}$thread=['id'=>(int)$pdo->lastInsertId(),'public_id'=>$public,'conversation_key'=>$key];}
    $p=$pdo->prepare('INSERT IGNORE INTO message_thread_participants (thread_id,user_id,joined_at) VALUES (?,?,NOW())');$p->execute([(int)$thread['id'],$merchantId]);$p->execute([(int)$thread['id'],$recipientId]);
    return $thread;
}
function mg_crm_message_insert(PDO $pdo,array $thread,int $merchantId,int $recipientId,string $body,string $key,string $sourceRef): array
{
    $existing=$pdo->prepare('SELECT public_id FROM messages WHERE sender_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');$existing->execute([$merchantId,$key]);$old=$existing->fetchColumn();if($old)return ['message_id'=>(string)$old,'duplicate'=>true];
    $mid=mg_crm_message_uuid();$pdo->prepare("INSERT INTO messages (public_id,thread_id,sender_user_id,recipient_user_id,body,idempotency_key,source_type,source_reference,created_at) VALUES (?,?,?,?,?,?,?, ?,NOW())")->execute([$mid,(int)$thread['id'],$merchantId,$recipientId,$body,$key,'merchant_crm',$sourceRef]);
    $pdo->prepare('UPDATE message_threads SET updated_at=NOW() WHERE id=?')->execute([(int)$thread['id']]);$pdo->prepare('UPDATE message_thread_participants SET last_read_at=NOW() WHERE thread_id=? AND user_id=?')->execute([(int)$thread['id'],$merchantId]);
    return ['message_id'=>$mid,'duplicate'=>false];
}
function mg_crm_message_email_payload(array $contact,array $campaign,string $body): array
{
    $name=trim((string)($contact['name']??'')) ?: 'there';$merchant='Microgifter merchant';$subject='A merchant sent you a Microgifter message';$url=mg_app_base_url().'/inbox.php';
    $html=mg_email_layout('Microgifter message','<p style="margin:0 0 14px;color:#334155;font-size:16px;line-height:1.6;">Hi '.mg_mail_escape($name).',</p><p style="margin:0 0 14px;color:#334155;font-size:16px;line-height:1.6;">'.nl2br(mg_mail_escape($body)).'</p>'.mg_email_button($url,'Open Microgifter inbox').'<p style="margin:14px 0 0;color:#64748b;font-size:13px;line-height:1.6;">Create or sign into your Microgifter account with this email to keep the conversation connected.</p>','Open Microgifter to reply.');
    return ['subject'=>$subject,'html'=>$html,'text'=>"Hi {$name},\n\n{$body}\n\nOpen Microgifter: {$url}",'email'=>(string)$contact['email'],'campaign_public_id'=>(string)$campaign['public_id'],'contact_public_id'=>(string)$contact['public_id'],'campaign_type'=>(string)$campaign['campaign_type'],'message_type'=>'merchant_crm_message'];
}

mg_require_method('POST');
$user=mg_require_permission('merchant.campaigns.manage');$merchantId=(int)$user['id'];$pdo=mg_db();mg_merchant_ensure_workspace($pdo,$user);$input=mg_input();mg_require_csrf_for_write($input);
$contactRef=strtolower(trim((string)($input['contact_id']??$input['contact']??'')));$body=mg_message_validate_body($input['message']??$input['body']??'');$idem=trim((string)($input['idempotency_key']??''));if($contactRef===''||strlen($contactRef)!==36)mg_fail('Contact is required.',422);if($idem==='')$idem=substr('crm-message:'.hash('sha256',$merchantId.'|'.$contactRef.'|'.$body.'|'.microtime(true)),0,190);
try{
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('SELECT cc.*,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type,c.public_id FROM campaign_contacts cc INNER JOIN campaigns c ON c.id=cc.campaign_id WHERE cc.public_id=? AND cc.merchant_user_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$contactRef,$merchantId]);$contact=$stmt->fetch(PDO::FETCH_ASSOC);if(!$contact){$pdo->rollBack();mg_fail('CRM contact not found.',404);} $campaign=['public_id'=>(string)$contact['campaign_public_id'],'title'=>(string)$contact['campaign_title'],'campaign_type'=>(string)$contact['campaign_type']];
    $recipientId=(int)($contact['user_id']??0);$result=['delivered_via'=>'email_fallback','thread_id'=>null,'message_id'=>null,'notification_id'=>null,'email_delivery'=>null,'duplicate'=>false];
    if($recipientId>0){$thread=mg_crm_message_thread($pdo,$merchantId,$recipientId,$contact);$msg=mg_crm_message_insert($pdo,$thread,$merchantId,$recipientId,$body,$idem,(string)$contact['public_id']);$notification=mg_create_notification($pdo,$recipientId,'merchant_crm_message','New message from a merchant',$body,'/inbox.php?thread='.rawurlencode((string)$thread['public_id']),['actor_user_id'=>$merchantId,'event_key'=>'crm.message.'.$msg['message_id'],'thread_id'=>(int)$thread['id'],'campaign_id'=>(int)$contact['campaign_id'],'contact_id'=>(int)$contact['id'],'campaign_type'=>(string)$contact['campaign_type']]);$result=['delivered_via'=>'microgifter_message','thread_id'=>(string)$thread['public_id'],'message_id'=>$msg['message_id'],'notification_id'=>$notification?:null,'email_delivery'=>null,'duplicate'=>$msg['duplicate']];mg_crm_message_event($pdo,$contact,'crm.message.sent',['thread_id'=>$thread['public_id'],'message_id'=>$msg['message_id'],'recipient_user_id'=>$recipientId,'campaign_type'=>(string)$contact['campaign_type']]);}
    else{$payload=mg_crm_message_email_payload($contact,$campaign,$body);$delivery=mg_delivery_enqueue($pdo,['idempotency_key'=>'crm-email:'.$idem,'event_type'=>'campaign.outbound_email','category'=>'campaign','channel'=>'email','template_key'=>'campaign.merchant_crm_message','recipient_user_id'=>0,'recipient_snapshot'=>['email'=>(string)$contact['email'],'name'=>(string)($contact['name']??'')],'payload'=>$payload,'max_attempts'=>3]);$result['email_delivery']=$delivery;mg_crm_message_event($pdo,$contact,'crm.message.email_fallback_queued',['delivery'=>$delivery,'campaign_type'=>(string)$contact['campaign_type']]);}
    mg_merchant_crm_record_event($pdo,['merchant_user_id'=>$merchantId,'campaign_id'=>(int)$contact['campaign_id'],'campaign_type'=>(string)$contact['campaign_type'],'event_type'=>'crm.message.sent','source_type'=>'merchant_crm_message','source_public_id'=>(string)$contact['public_id'],'user_id'=>$recipientId>0?$recipientId:null,'email'=>(string)$contact['email'],'name'=>(string)($contact['name']??''),'metadata'=>$result]);
    $pdo->commit();mg_ok(['contact_id'=>(string)$contact['public_id'],'campaign_id'=>(string)$contact['campaign_public_id'],'campaign_type'=>(string)$contact['campaign_type'],'message'=>$result],'CRM message queued.');
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','merchant.crm_message.failed','Unable to send CRM message.',['exception_class'=>$error::class,'message'=>$error->getMessage()],$merchantId);mg_fail('Unable to send CRM message.',500);}
