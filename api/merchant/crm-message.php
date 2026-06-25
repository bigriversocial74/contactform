<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm.php';

$user=mg_require_permission('merchant.campaigns.manage');$pdo=mg_db();$method=strtoupper($_SERVER['REQUEST_METHOD']??'POST');if($method!=='POST')mg_fail('Method not allowed.',405);$input=mg_input();mg_require_csrf_for_write($input);$contactPublicId=trim((string)($input['contact_id']??''));$body=trim((string)($input['body']??''));if($contactPublicId===''||$body==='')mg_fail('Contact and message are required.',422);
$stmt=$pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE public_id=? AND merchant_user_id=?');$stmt->execute([$contactPublicId,(int)$user['id']]);$contact=$stmt->fetch(PDO::FETCH_ASSOC);if(!$contact)mg_fail('Contact not found.',404);
$threadKey='crm:'.$contactPublicId.':merchant:'.(int)$user['id'];$threadId=mg_message_thread_for_key($pdo,$threadKey,(int)$user['id']);$messageId=mg_message_insert($pdo,$threadId,(int)$user['id'],$body,['source_type'=>'merchant_crm','contact_id'=>(int)$contact['id']]);
if((int)($contact['user_id']??0)>0){mg_message_add_participant($pdo,$threadId,(int)$contact['user_id']);mg_create_notification($pdo,(int)$contact['user_id'],'merchant_crm_message','Message from Microgifter merchant',mb_substr($body,0,220),'/messages.php?thread='.$threadId,['actor_user_id'=>(int)$user['id'],'thread_id'=>$threadId]);}
mg_merchant_crm_record_event($pdo,(int)$user['id'],['contact_id'=>(int)$contact['id'],'event_type'=>'crm.message.sent','source_type'=>'merchant_crm','source_public_id'=>(string)$messageId,'summary'=>'Merchant CRM message sent.']);
mg_ok(['message_id'=>$messageId,'thread_id'=>$threadId],'Message sent.');
