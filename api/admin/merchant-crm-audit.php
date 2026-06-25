<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
mg_require_method('GET');
$user = mg_require_api_user();
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$perms = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
if (!in_array('super_admin',$roles,true) && !in_array('merchant.campaigns.view',$perms,true)) mg_fail('Permission denied.',403);
$root = dirname(__DIR__,2);
$checks=[];
$add=function(string $status,string $message,array $context=[])use(&$checks){$checks[]=['status'=>$status,'message'=>$message,'context'=>$context];};
$pass=function(string $m,array $c=[])use($add){$add('pass',$m,$c);};
$fail=function(string $m,array $c=[])use($add){$add('fail',$m,$c);};
$files=['merchant-crm.php','includes/merchant-crm.php','includes/merchant-crm-view.php','assets/js/merchant-crm.js','api/merchant/merchant-crm.php','api/merchant/campaign-contacts.php','api/merchant/campaign-timeline.php','api/merchant/followups.php','api/merchant/crm-messages.php','api/public/campaigns/signup.php','api/public/campaigns/engage.php','api/public/campaigns/contest-entry.php','api/public/campaigns/qr-pickup.php','api/public/campaigns/_followups.php','api/communications/campaign-followup-worker.php'];
foreach($files as $file){is_file($root.'/'.$file)?$pass('File exists: '.$file):$fail('Missing file: '.$file);} 
$contracts=[
 'includes/merchant-crm.php'=>['mg_merchant_crm_record_event','total_purchase_cents','total_rewards_issued','merchant_crm_contact_events'],
 'api/merchant/merchant-crm.php'=>['segments','sources','recent_events','followups'],
 'api/merchant/campaign-contacts.php'=>['crm_contact_id','followups_queued_count','total_purchase_cents'],
 'api/merchant/campaign-timeline.php'=>['crm_event','followup_job','merchant_crm_contact_events'],
 'api/merchant/followups.php'=>['campaign_followup_rules','campaign_followup_jobs','dedupe_key'],
 'assets/js/merchant-crm.js'=>['data-crm-segments','data-crm-followups','Recent CRM events'],
 'includes/merchant-crm-view.php'=>['CRM intelligence','data-crm-segments','data-crm-recent-events'],
 'api/public/campaigns/signup.php'=>['mg_merchant_crm_record_event','crm_contact_id'],
 'api/public/campaigns/engage.php'=>['mg_merchant_crm_record_event','campaign.engaged'],
 'api/public/campaigns/contest-entry.php'=>['mg_merchant_crm_record_event','contest.entered'],
 'api/public/campaigns/qr-pickup.php'=>['mg_merchant_crm_record_event','qr.scanned'],
 'api/public/campaigns/_followups.php'=>['dedupe_key','INSERT IGNORE','mg_campaign_followup_schedule'],
];
foreach($contracts as $file=>$needles){$path=$root.'/'.$file;if(!is_file($path))continue;$content=file_get_contents($path)?:'';foreach($needles as $needle){str_contains($content,$needle)?$pass('Contract found in '.$file.': '.$needle):$fail('Missing contract in '.$file.': '.$needle);}}
try{
 $pdo=mg_db();
 $tables=['merchant_crm_contacts','merchant_crm_contact_events','merchant_crm_contact_campaigns','merchant_crm_notes','campaign_contacts','campaign_events','campaign_followup_rules','campaign_followup_jobs','wallet_items'];
 foreach($tables as $table){$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$stmt->execute([$table]);((int)$stmt->fetchColumn()>0)?$pass('Table exists: '.$table):$fail('Missing table: '.$table);} 
 $columns=['merchant_crm_contacts'=>['public_id','merchant_user_id','user_id','primary_email','lifecycle_stage','crm_status','last_campaign_type','last_source_type','total_purchase_cents','total_rewards_issued','total_rewards_claimed','total_rewards_redeemed'],'merchant_crm_contact_events'=>['public_id','merchant_user_id','crm_contact_id','campaign_id','campaign_type','event_type','source_type','source_public_id','value_cents','metadata_json'],'campaign_followup_rules'=>['public_id','merchant_user_id','campaign_id','trigger_event','delay_seconds','channel','message_mode','status'],'campaign_followup_jobs'=>['public_id','dedupe_key','rule_id','campaign_id','contact_id','wallet_item_id','trigger_event','status','due_at']];
 foreach($columns as $table=>$cols){foreach($cols as $column){$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$stmt->execute([$table,$column]);((int)$stmt->fetchColumn()>0)?$pass('Column exists: '.$table.'.'.$column):$fail('Missing column: '.$table.'.'.$column);}}
}catch(Throwable $e){$fail('Database audit failed.',['exception_class'=>$e::class,'message'=>$e->getMessage()]);}
$failures=array_values(array_filter($checks,static fn($c)=>$c['status']==='fail'));
$score=count($checks)===0?0:(int)round(((count($checks)-count($failures))/count($checks))*10);
mg_ok(['score'=>$score,'status'=>count($failures)===0?'passed':'failed','summary'=>['checks'=>count($checks),'passed'=>count($checks)-count($failures),'failed'=>count($failures)],'checks'=>$checks],count($failures)===0?'Merchant CRM intelligence audit passed.':'Merchant CRM audit has failures.');
