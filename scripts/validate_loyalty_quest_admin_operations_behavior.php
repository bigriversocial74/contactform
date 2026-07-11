<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/admin/_loyalty_quest_operations.php';

$host=getenv('DB_HOST')?:'127.0.0.1';
$port=(int)(getenv('DB_PORT')?:3306);
$name=getenv('DB_NAME')?:'microgifter_test';
$user=getenv('DB_USER')?:'root';
$pass=getenv('DB_PASS')?:'root';
$pdo=new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

foreach(['operational_alerts','message_delivery_jobs','message_events','campaign_events','wallet_items','loyalty_quest_evidence','loyalty_quest_participations','campaign_contacts','campaigns','public_profiles','merchant_workspaces','users'] as $table){
    $pdo->exec("DROP TABLE IF EXISTS {$table}");
}

$schema=[
"CREATE TABLE users(id BIGINT UNSIGNED PRIMARY KEY,email VARCHAR(190) NOT NULL,display_name VARCHAR(190) NULL,full_name VARCHAR(190) NULL,status VARCHAR(30) NOT NULL DEFAULT 'active')",
"CREATE TABLE merchant_workspaces(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,merchant_user_id BIGINT UNSIGNED NOT NULL,display_name VARCHAR(190) NULL)",
"CREATE TABLE public_profiles(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,display_name VARCHAR(190) NULL)",
"CREATE TABLE campaigns(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,public_id CHAR(36) NOT NULL,public_slug VARCHAR(190) NULL,title VARCHAR(180) NOT NULL,status VARCHAR(30) NOT NULL,starts_at DATETIME NULL,ends_at DATETIME NULL,issued_count INT NOT NULL DEFAULT 0,claimed_count INT NOT NULL DEFAULT 0,redeemed_count INT NOT NULL DEFAULT 0,updated_at DATETIME NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,campaign_type VARCHAR(80) NOT NULL)",
"CREATE TABLE campaign_contacts(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,public_id CHAR(36) NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,campaign_id BIGINT UNSIGNED NOT NULL,email VARCHAR(190) NOT NULL,name VARCHAR(190) NULL)",
"CREATE TABLE loyalty_quest_participations(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,public_id CHAR(36) NOT NULL,campaign_id BIGINT UNSIGNED NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,participant_user_id BIGINT UNSIGNED NOT NULL,contact_id BIGINT UNSIGNED NULL,status VARCHAR(30) NOT NULL)",
"CREATE TABLE loyalty_quest_evidence(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,public_id CHAR(36) NOT NULL,participation_id BIGINT UNSIGNED NOT NULL,campaign_id BIGINT UNSIGNED NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,participant_user_id BIGINT UNSIGNED NOT NULL,evidence_type VARCHAR(40) NOT NULL,status VARCHAR(30) NOT NULL,proof_url VARCHAR(500) NULL,proof_note TEXT NULL,latitude DECIMAL(10,7) NULL,longitude DECIMAL(10,7) NULL,created_at DATETIME NOT NULL)",
"CREATE TABLE wallet_items(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,public_id CHAR(36) NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,campaign_id BIGINT UNSIGNED NULL,source_type VARCHAR(80) NOT NULL,status VARCHAR(30) NOT NULL)",
"CREATE TABLE campaign_events(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,public_id CHAR(36) NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,campaign_id BIGINT UNSIGNED NOT NULL,wallet_item_id BIGINT UNSIGNED NULL,contact_id BIGINT UNSIGNED NULL,event_type VARCHAR(100) NOT NULL,event_context_json JSON NULL,created_at DATETIME NOT NULL)",
"CREATE TABLE message_events(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,event_type VARCHAR(100) NOT NULL)",
"CREATE TABLE message_delivery_jobs(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,public_id CHAR(36) NOT NULL,message_event_id BIGINT UNSIGNED NOT NULL,merchant_user_id BIGINT UNSIGNED NULL,campaign_id BIGINT UNSIGNED NULL,status VARCHAR(30) NOT NULL,attempt_count INT NOT NULL DEFAULT 0,max_attempts INT NOT NULL DEFAULT 3,next_attempt_at DATETIME NULL,last_error VARCHAR(500) NULL,recipient_snapshot_json JSON NULL,failed_at DATETIME NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL)",
];
foreach($schema as $statement)$pdo->exec($statement);

$now=gmdate('Y-m-d H:i:s');
$ago=static fn(int $hours):string=>gmdate('Y-m-d H:i:s',time()-$hours*3600);

$pdo->exec("INSERT INTO users(id,email,display_name,full_name,status) VALUES (1,'alpha-owner@example.test','Alpha Owner','Alpha Owner','active'),(2,'beta-owner@example.test','Beta Owner','Beta Owner','active'),(101,'participant-one@example.test','Participant One','Participant One','active'),(102,'participant-two@example.test','Participant Two','Participant Two','active')");
$pdo->exec("INSERT INTO merchant_workspaces(merchant_user_id,display_name) VALUES (1,'Alpha Coffee'),(2,'Beta Books')");
$pdo->exec("INSERT INTO public_profiles(user_id,display_name) VALUES (1,'Alpha Public'),(2,'Beta Public')");
$pdo->prepare("INSERT INTO campaigns(public_id,public_slug,title,status,starts_at,ends_at,issued_count,claimed_count,redeemed_count,updated_at,merchant_user_id,campaign_type) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute(['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','alpha-quest','Alpha Visit Quest','active',$ago(72),null,3,2,1,$now,1,'loyalty_quest']);
$pdo->prepare("INSERT INTO campaigns(public_id,public_slug,title,status,starts_at,ends_at,issued_count,claimed_count,redeemed_count,updated_at,merchant_user_id,campaign_type) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute(['bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','beta-quest','Beta Reading Quest','paused',$ago(96),null,1,0,0,$ago(4),2,'loyalty_quest']);
$pdo->prepare("INSERT INTO campaigns(public_id,public_slug,title,status,starts_at,ends_at,issued_count,claimed_count,redeemed_count,updated_at,merchant_user_id,campaign_type) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute(['cccccccc-cccc-4ccc-8ccc-cccccccccccc','other-campaign','Not a Quest','active',$ago(24),null,0,0,0,$now,1,'contest_giveaway']);

$pdo->exec("INSERT INTO campaign_contacts(public_id,merchant_user_id,campaign_id,email,name) VALUES ('dddddddd-dddd-4ddd-8ddd-dddddddddd01',1,1,'participant-one@example.test','Participant One'),('dddddddd-dddd-4ddd-8ddd-dddddddddd02',2,2,'participant-two@example.test','Participant Two')");
$pdo->exec("INSERT INTO loyalty_quest_participations(public_id,campaign_id,merchant_user_id,participant_user_id,contact_id,status) VALUES ('eeeeeeee-eeee-4eee-8eee-eeeeeeeeee01',1,1,101,1,'pending_review'),('eeeeeeee-eeee-4eee-8eee-eeeeeeeeee02',1,1,102,1,'completed'),('eeeeeeee-eeee-4eee-8eee-eeeeeeeeee03',2,2,102,2,'in_progress')");
$evidence=$pdo->prepare("INSERT INTO loyalty_quest_evidence(public_id,participation_id,campaign_id,merchant_user_id,participant_user_id,evidence_type,status,proof_url,proof_note,latitude,longitude,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
$evidence->execute(['ffffffff-ffff-4fff-8fff-ffffffff0001',1,1,1,101,'photo','submitted','https://private.example.test/proof.jpg','Private participant proof',33.4484,-112.0740,$ago(30)]);
$evidence->execute(['ffffffff-ffff-4fff-8fff-ffffffff0002',3,2,2,102,'geolocation','verified',null,null,34.0522,-118.2437,$ago(4)]);
$pdo->exec("INSERT INTO wallet_items(public_id,merchant_user_id,campaign_id,source_type,status) VALUES ('11111111-1111-4111-8111-111111111111',1,1,'loyalty_quest','redeemed'),('22222222-2222-4222-8222-222222222222',1,1,'loyalty_quest','issued'),('33333333-3333-4333-8333-333333333333',2,2,'loyalty_quest','issued')");
$pdo->exec("INSERT INTO message_events(event_type) VALUES ('loyalty_quest.quest_invitation'),('loyalty_quest.reward_delivered'),('other.event')");
$delivery=$pdo->prepare("INSERT INTO message_delivery_jobs(public_id,message_event_id,merchant_user_id,campaign_id,status,attempt_count,max_attempts,next_attempt_at,last_error,recipient_snapshot_json,failed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
$delivery->execute(['44444444-4444-4444-8444-444444444441',1,1,1,'failed',3,5,$now,'Provider rejected message',json_encode(['name'=>'Participant One','email'=>'secret.person@example.test']),$ago(2),$ago(3),$ago(2)]);
$delivery->execute(['44444444-4444-4444-8444-444444444442',2,2,2,'processing',1,5,$now,null,json_encode(['name'=>'Participant Two','email'=>'other.person@example.test']),null,$ago(2),$ago(1)]);
$delivery->execute(['44444444-4444-4444-8444-444444444443',3,1,1,'failed',1,3,$now,'Unrelated failure',json_encode(['email'=>'unrelated@example.test']),$ago(1),$ago(2),$ago(1)]);
$pdo->prepare("INSERT INTO campaign_events(public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,?)")
    ->execute(['55555555-5555-4555-8555-555555555555',1,1,null,null,'quest.admin_paused',json_encode(['operator_user_id'=>9,'reason'=>'Paused after an operational safety review.']),$ago(1)]);

$campaignResult=mg_lqo_campaigns($pdo,'all','',100);
$campaigns=$campaignResult['items'];
$evidenceQueue=mg_lqo_evidence_queue($pdo,100);
$deliveryQueue=mg_lqo_delivery_queue($pdo,100);
$summary=mg_lqo_summary($campaigns,$evidenceQueue,$deliveryQueue);
$events=mg_lqo_recent_events($pdo,50);

$checks=[];
$check=static function(string $name,bool $ok,mixed $actual=null)use(&$checks):void{$checks[]=['name'=>$name,'ok'=>$ok,'actual'=>$actual];};
$check('quest-only campaign scope',count($campaigns)===2,array_column($campaigns,'title'));
$check('cross-merchant operations view',count(array_unique(array_map(static fn(array $row):int=>(int)$row['merchant']['id'],$campaigns)))===2,array_column(array_column($campaigns,'merchant'),'id'));
$check('campaign aggregate participants',($campaigns[0]['participants']+$campaigns[1]['participants'])===3,array_column($campaigns,'participants'));
$check('pending evidence queue only',count($evidenceQueue)===1&&$evidenceQueue[0]['id']==='ffffffff-ffff-4fff-8fff-ffffffff0001',$evidenceQueue);
$evidenceJson=json_encode($evidenceQueue,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$check('evidence PII excluded',!str_contains($evidenceJson,'participant-one@example.test')&&!str_contains($evidenceJson,'Private participant proof')&&!str_contains($evidenceJson,'proof.jpg')&&!str_contains($evidenceJson,'33.4484'),$evidenceJson);
$check('review age and nudge eligibility',($evidenceQueue[0]['age_hours']??0)>=24&&!empty($evidenceQueue[0]['can_nudge']),$evidenceQueue[0]??null);
$check('delivery queue excludes unrelated events',count($deliveryQueue)===2,array_column($deliveryQueue,'id'));
$maskedEmails=array_column(array_column($deliveryQueue,'recipient'),'email_masked');
$check('delivery recipient masked',in_array('se••••••••@example.test',$maskedEmails,true)&&!in_array('secret.person@example.test',$maskedEmails,true),$maskedEmails);
$check('stale processing detected',count(array_filter($deliveryQueue,static fn(array $row):bool=>!empty($row['stale_processing'])))===1,$deliveryQueue);
$check('summary totals',($summary['campaigns']??0)===2&&($summary['pending_review']??0)===1&&($summary['delivery_failures']??0)===2&&($summary['redeemed']??0)===1,$summary);
$check('recent audited action',count($events)===1&&$events[0]['event_type']==='quest.admin_paused'&&$events[0]['operator_user_id']===9,$events);
$shortRejected=false;try{mg_lqo_require_reason(['reason'=>'too short']);}catch(InvalidArgumentException){$shortRejected=true;}
$check('operator reason minimum',$shortRejected);
$check('valid operator reason',mg_lqo_require_reason(['reason'=>'Documented operational review reason.'])==='Documented operational review reason.');

$failed=array_values(array_filter($checks,static fn(array $row):bool=>!$row['ok']));
echo json_encode(['ok'=>$failed===[],'checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed===[]?0:1);
