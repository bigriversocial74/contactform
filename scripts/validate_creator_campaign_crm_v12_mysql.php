<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/api/bootstrap.php';
require_once dirname(__DIR__).'/includes/creator-campaigns.php';
require_once dirname(__DIR__).'/includes/merchant-crm-directory.php';
require_once dirname(__DIR__).'/includes/merchant-crm-creator-campaign-bridge.php';

function cccrm12_assert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$pdo=mg_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$suffix=substr(bin2hex(random_bytes(8)),0,12);
cccrm12_assert(mg_creator_campaign_crm_installed($pdo),'Phase 12 CRM schema is incomplete.');

$tables=mg_creator_campaign_crm_required_tables();$placeholders=implode(',',array_fill(0,count($tables),'?'));
$stmt=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$placeholders})");$stmt->execute($tables);
cccrm12_assert((int)$stmt->fetchColumn()===count($tables),'Phase 12 required tables are missing.');
$stmt=$pdo->query("SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='merchant_crm_creator_campaign_events' AND index_name='uq_merchant_crm_cc_event_source' AND non_unique=0");
cccrm12_assert((int)$stmt->fetchColumn()===1,'Projection source idempotency index is missing.');
$stmt=$pdo->query("SELECT COUNT(*) FROM permissions WHERE slug IN ('merchant.creator_crm.view','merchant.creator_crm.manage')");
cccrm12_assert((int)$stmt->fetchColumn()===2,'Phase 12 permissions are incomplete.');

$merchantEmail="cccrm12-merchant-{$suffix}@example.test";
$creatorEmail="cccrm12-creator-{$suffix}@example.test";
$customerEmail="cccrm12-customer-{$suffix}@example.test";
$insertUser=$pdo->prepare("INSERT INTO users(email,password_hash,full_name,display_name,status,created_at,updated_at) VALUES(?,?,?,?,'active',NOW(),NOW())");
$insertUser->execute([$merchantEmail,password_hash('Phase12Validation!42',PASSWORD_DEFAULT),'Phase 12 Merchant','Phase 12 Merchant']);$merchantId=(int)$pdo->lastInsertId();
$insertUser->execute([$creatorEmail,password_hash('Phase12Validation!42',PASSWORD_DEFAULT),'Phase 12 Creator','Phase 12 Creator']);$creatorUserId=(int)$pdo->lastInsertId();
$insertUser->execute([$customerEmail,password_hash('Phase12Validation!42',PASSWORD_DEFAULT),'Phase 12 Customer','Phase 12 Customer']);$customerUserId=(int)$pdo->lastInsertId();
$merchantUser=['id'=>$merchantId,'roles'=>['admin'],'permissions'=>[]];

$workspacePublic=mg_merchant_crm_uuid();
$pdo->prepare("INSERT INTO merchant_workspaces(public_id,merchant_user_id,display_name,default_currency,timezone,status,eligibility_status,onboarding_percent,created_at,updated_at) VALUES(?,?,?,'USD','UTC','active','eligible',100,NOW(),NOW())")
    ->execute([$workspacePublic,$merchantId,'Phase 12 CRM Workspace']);
$workspaceId=(int)$pdo->lastInsertId();
$creatorProfilePublic=mg_creator_campaign_public_id('cp');
$pdo->prepare("INSERT INTO creator_profiles(public_id,user_id,display_name,slug,bio,status,created_at,updated_at) VALUES(?,?,?,?,?,'active',NOW(),NOW())")
    ->execute([$creatorProfilePublic,$creatorUserId,'Phase 12 Creator',"phase12-creator-{$suffix}",'Creator Campaign CRM validation profile.']);
$creatorProfileId=(int)$pdo->lastInsertId();

$created=mg_creator_campaign_create_draft($pdo,$merchantUser,[
    'idempotency_key'=>"cccrm12-create-{$suffix}",'internal_reference'=>"CCCRM12-{$suffix}",
    'title'=>'Phase 12 CRM Validation','description'=>'Creator Campaign CRM lifecycle validation campaign.',
    'objective'=>'Product sales','category'=>'Hospitality','access_mode'=>'open','timezone'=>'UTC',
]);
$campaign=$created['campaign'];$campaignId=(int)$campaign['id'];$campaignPublic=(string)$campaign['public_id'];

$applicationPublic=mg_creator_campaign_public_id('cca');
$pdo->prepare("INSERT INTO creator_campaign_applications(public_id,campaign_id,creator_profile_id,creator_user_id,status,cover_note,creator_snapshot_json,submitted_at,lock_version,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES(?,?,?,?, 'submitted','Phase 12 CRM validation',JSON_OBJECT('display_name','Phase 12 Creator'),NOW(),1,?,?,NOW(),NOW())")
    ->execute([$applicationPublic,$campaignId,$creatorProfileId,$creatorUserId,$merchantId,$merchantId]);
$applicationId=(int)$pdo->lastInsertId();

$event=mg_creator_campaign_participation_event($pdo,[
    'campaign_id'=>$campaignId,'application_id'=>$applicationId,'actor_user_id'=>$merchantId,
    'event_type'=>'application.submitted','from_status'=>'draft','to_status'=>'submitted',
    'idempotency_key'=>"cccrm12-participation-{$suffix}",'context'=>['validation'=>'phase12'],
]);
cccrm12_assert(empty($event['idempotent_replay']),'Initial participation event replayed.');
$replay=mg_creator_campaign_participation_event($pdo,[
    'campaign_id'=>$campaignId,'application_id'=>$applicationId,'actor_user_id'=>$merchantId,
    'event_type'=>'application.submitted','from_status'=>'draft','to_status'=>'submitted',
    'idempotency_key'=>"cccrm12-participation-{$suffix}",'context'=>['validation'=>'phase12'],
]);
cccrm12_assert(!empty($replay['idempotent_replay'])&&$replay['public_id']===$event['public_id'],'Participation idempotency failed.');

$stmt=$pdo->prepare("SELECT mc.id,mc.public_id,mc.lifecycle_stage,r.id relationship_id,r.event_count,e.projection_status,ce.campaign_id legacy_campaign_id
 FROM merchant_crm_contacts mc
 INNER JOIN merchant_crm_contact_creator_campaigns r ON r.crm_contact_id=mc.id AND r.relationship_type='creator_partner'
 INNER JOIN merchant_crm_creator_campaign_events e ON e.crm_contact_id=mc.id AND e.source_event_key=?
 LEFT JOIN merchant_crm_contact_events ce ON ce.id=e.crm_event_id
 WHERE mc.merchant_user_id=? AND mc.user_id=? LIMIT 1");
$stmt->execute(['participation:'.strtolower((string)$event['public_id']),$merchantId,$creatorUserId]);$creatorContact=$stmt->fetch(PDO::FETCH_ASSOC);
cccrm12_assert((bool)$creatorContact,'Creator partner was not projected into canonical CRM.');
cccrm12_assert((string)$creatorContact['lifecycle_stage']==='custom','Creator partner lifecycle stage was not preserved as custom.');
cccrm12_assert((int)$creatorContact['event_count']===1,'Idempotent participation replay duplicated the relationship event count.');
cccrm12_assert((string)$creatorContact['projection_status']==='completed','Creator partner projection did not complete.');
cccrm12_assert($creatorContact['legacy_campaign_id']===null,'Creator Campaign database ID leaked into the legacy CRM event campaign foreign key.');

$purchase=mg_creator_campaign_tracking_record_conversion($pdo,[
    'campaign_id'=>$campaignPublic,'event_type'=>'purchase','event_key'=>"cccrm12.purchase.{$suffix}",
    'session_key'=>"session-{$suffix}",'visitor_key'=>"visitor-{$suffix}",'request_key'=>"purchase-request-{$suffix}",
    'target_path'=>'/checkout.php','metadata'=>[
        'crm_identity'=>['user_id'=>$customerUserId,'email'=>$customerEmail,'name'=>'Phase 12 Customer'],
        'amount_minor'=>4200,'currency'=>'USD','order_reference'=>"order-{$suffix}",
    ],
]);
cccrm12_assert(!empty($purchase['crm_projection']['projected']),'Trusted purchase conversion was not projected in real time.');
$stmt=$pdo->prepare("SELECT mc.id,mc.lifecycle_stage,r.relationship_type,e.projection_status,ce.campaign_id legacy_campaign_id
 FROM merchant_crm_contacts mc
 INNER JOIN merchant_crm_contact_creator_campaigns r ON r.crm_contact_id=mc.id AND r.creator_campaign_id=? AND r.relationship_type='customer'
 INNER JOIN merchant_crm_creator_campaign_events e ON e.crm_contact_id=mc.id AND e.source_event_key=?
 LEFT JOIN merchant_crm_contact_events ce ON ce.id=e.crm_event_id
 WHERE mc.merchant_user_id=? AND mc.user_id=? LIMIT 1");
$stmt->execute([$campaignId,'tracking:'.strtolower((string)$purchase['public_id']),$merchantId,$customerUserId]);$customerContact=$stmt->fetch(PDO::FETCH_ASSOC);
cccrm12_assert((bool)$customerContact,'Attributed customer was not stored in canonical CRM.');
cccrm12_assert((string)$customerContact['lifecycle_stage']==='customer','Trusted purchase did not advance the canonical customer lifecycle stage.');
cccrm12_assert((string)$customerContact['projection_status']==='completed','Trusted conversion projection did not complete.');
cccrm12_assert($customerContact['legacy_campaign_id']===null,'Trusted conversion used the legacy campaign foreign key.');

$beforeAnonymous=(int)$pdo->query("SELECT COUNT(*) FROM merchant_crm_contacts WHERE merchant_user_id={$merchantId}")->fetchColumn();
$anonymous=mg_creator_campaign_tracking_record_conversion($pdo,[
    'campaign_id'=>$campaignPublic,'event_type'=>'lead','event_key'=>"cccrm12.anonymous.{$suffix}",
    'session_key'=>"anon-session-{$suffix}",'visitor_key'=>"anon-visitor-{$suffix}",'request_key'=>"anon-request-{$suffix}",
    'target_path'=>'/learn-more.php','metadata'=>['source'=>'anonymous_validation'],
]);
$afterAnonymous=(int)$pdo->query("SELECT COUNT(*) FROM merchant_crm_contacts WHERE merchant_user_id={$merchantId}")->fetchColumn();
cccrm12_assert(!empty($anonymous['crm_projection']['skipped'])&&($anonymous['crm_projection']['reason']??'')==='identity_unresolved','Anonymous conversion was not privacy-skipped.');
cccrm12_assert($beforeAnonymous===$afterAnonymous,'Anonymous tracking hashes created a CRM contact.');
$stmt=$pdo->prepare("SELECT projection_status,error_code FROM merchant_crm_creator_campaign_events WHERE source_event_key=? LIMIT 1");
$stmt->execute(['tracking:'.strtolower((string)$anonymous['public_id'])]);$anonymousProjection=$stmt->fetch(PDO::FETCH_ASSOC);
cccrm12_assert(($anonymousProjection['projection_status']??'')==='skipped'&&($anonymousProjection['error_code']??'')==='identity_unresolved','Anonymous projection audit is incomplete.');

$directEventPublic=mg_creator_campaign_public_id('ccpe');
$pdo->prepare("INSERT INTO creator_campaign_participation_events(public_id,campaign_id,application_id,actor_user_id,event_type,from_status,to_status,context_json,created_at) VALUES(?,?,?,?, 'application.review_started','submitted','under_review',JSON_OBJECT('validation','reconcile'),NOW())")
    ->execute([$directEventPublic,$campaignId,$applicationId,$merchantId]);
$reconciliation=mg_creator_campaign_crm_reconcile($pdo,$merchantUser,$campaignPublic,500);
cccrm12_assert((int)$reconciliation['projected_count']>=1,'Reconciliation did not recover an unprojected participation event.');
$stmt=$pdo->prepare("SELECT projection_status FROM merchant_crm_creator_campaign_events WHERE source_event_key=? LIMIT 1");$stmt->execute(['participation:'.strtolower($directEventPublic)]);
cccrm12_assert((string)$stmt->fetchColumn()==='completed','Recovered participation projection did not complete.');
$stmt=$pdo->prepare("SELECT COUNT(*) FROM merchant_crm_creator_campaign_projection_runs WHERE public_id=? AND status IN ('completed','completed_with_errors')");$stmt->execute([$reconciliation['run_id']]);
cccrm12_assert((int)$stmt->fetchColumn()===1,'Projection run audit was not completed.');

$directory=mg_merchant_crm_directory_list($pdo,$merchantId,'',250,0);
$directory=mg_merchant_crm_creator_campaign_enrich_directory($pdo,$merchantId,$directory,'');
$creatorVisible=false;$customerVisible=false;
foreach($directory['contacts']??[] as $contact){
    if((int)($contact['user_id']??0)===$creatorUserId&&!empty($contact['creator_campaign_count']))$creatorVisible=true;
    if((int)($contact['user_id']??0)===$customerUserId&&!empty($contact['creator_campaign_count']))$customerVisible=true;
}
cccrm12_assert($creatorVisible&&$customerVisible,'Creator Campaign relationships are not visible in the canonical CRM directory.');
$stmt=$pdo->prepare('SELECT COUNT(*) FROM merchant_crm_contact_campaigns WHERE merchant_user_id=? AND crm_contact_id IN (?,?)');
$stmt->execute([$merchantId,(int)$creatorContact['id'],(int)$customerContact['id']]);
cccrm12_assert((int)$stmt->fetchColumn()===0,'Creator Campaign relationships contaminated the legacy campaign link table.');

$list=mg_creator_campaign_crm_list($pdo,$merchantUser,['campaign_id'=>$campaignPublic,'limit'=>100]);
cccrm12_assert(!empty($list['schema_ready'])&&count($list['contacts']??[])>=2,'Phase 12 merchant read model did not return canonical relationships.');

echo json_encode([
    'ok'=>true,'tables'=>count($tables),'creator_partner_projected'=>true,'participation_idempotency'=>true,
    'trusted_customer_projected'=>true,'anonymous_identity_skipped'=>true,'reconciliation_recovered'=>true,
    'canonical_directory_visible'=>true,'legacy_campaign_links'=>0,'projection_run_id'=>$reconciliation['run_id'],
],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
