<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm.php';
require_once dirname(__DIR__) . '/rewards/_zero_value_bridge.php';

$user=mg_require_permission('merchant.campaigns.manage');$pdo=mg_db();$method=strtoupper($_SERVER['REQUEST_METHOD']??'POST');if($method!=='POST')mg_fail('Method not allowed.',405);$input=mg_input();mg_require_csrf_for_write($input);$contactPublicId=trim((string)($input['contact_id']??''));$title=trim((string)($input['title']??$input['reward_title']??''));$note=trim((string)($input['note']??''));if($contactPublicId===''||$title==='')mg_fail('Contact and reward title are required.',422);
$stmt=$pdo->prepare('SELECT * FROM merchant_crm_contacts WHERE public_id=? AND merchant_user_id=?');$stmt->execute([$contactPublicId,(int)$user['id']]);$contact=$stmt->fetch(PDO::FETCH_ASSOC);if(!$contact)mg_fail('Contact not found.',404);
$result=mg_zero_reward_issue_from_wallet($pdo,['merchant_user_id'=>(int)$user['id'],'recipient_user_id'=>(int)($contact['user_id']??0),'recipient_email'=>(string)($contact['email']??''),'title'=>$title,'description'=>$note,'source_type'=>'merchant_crm_direct_gift','source_public_id'=>$contactPublicId]);
mg_merchant_crm_record_event($pdo,(int)$user['id'],['contact_id'=>(int)$contact['id'],'event_type'=>'crm.gift.sent','source_type'=>'merchant_crm','source_public_id'=>(string)($result['wallet_item_id']??''),'summary'=>'Direct gift sent from Merchant CRM.','metadata'=>['title'=>$title]]);
mg_ok(['gift'=>$result],'Gift sent.');
