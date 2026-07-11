<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/admin/_loyalty_quest_integrity.php';

$host=getenv('DB_HOST')?:'127.0.0.1';
$port=(int)(getenv('DB_PORT')?:3306);
$name=getenv('DB_NAME')?:'microgifter_test';
$user=getenv('DB_USER')?:'root';
$pass=getenv('DB_PASS')?:'root';
$pdo=new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",$user,$pass,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS=>true,
]);

$_SERVER['REMOTE_ADDR']='203.0.113.10';
$_SERVER['HTTP_USER_AGENT']='Microgifter Integrity Behavior Suite';
$_SERVER['HTTP_X_REQUEST_ID']='integrity-behavior-request';
$_COOKIE['mg_lq_device']=str_repeat('d',64);
if(trim((string)(getenv('MG_LOYALTY_QUEST_INTEGRITY_PEPPER')?:''))==='')putenv('MG_LOYALTY_QUEST_INTEGRITY_PEPPER=behavior-suite-integrity-pepper-0123456789');

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach([
    'loyalty_quest_integrity_signals','loyalty_quest_integrity_attempts','campaign_events','security_logs','audit_logs','rate_limits',
    'loyalty_quest_evidence','loyalty_quest_participations','campaigns','public_profiles','merchant_workspaces','schema_migrations','users'
] as $table)$pdo->exec("DROP TABLE IF EXISTS {$table}");
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

$schema=[
"CREATE TABLE users(id BIGINT UNSIGNED PRIMARY KEY,email VARCHAR(190) NOT NULL,display_name VARCHAR(190) NULL,full_name VARCHAR(190) NULL,status VARCHAR(30) NOT NULL DEFAULT 'active') ENGINE=InnoDB",
"CREATE TABLE merchant_workspaces(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,merchant_user_id BIGINT UNSIGNED NOT NULL,display_name VARCHAR(190) NULL) ENGINE=InnoDB",
"CREATE TABLE public_profiles(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,display_name VARCHAR(190) NULL) ENGINE=InnoDB",
"CREATE TABLE schema_migrations(migration_key VARCHAR(190) PRIMARY KEY,description VARCHAR(500) NULL,checksum CHAR(64) NULL,applied_at DATETIME NOT NULL) ENGINE=InnoDB",
"CREATE TABLE campaigns(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,public_id CHAR(36) NOT NULL,public_slug VARCHAR(190) NULL,title VARCHAR(180) NOT NULL,status VARCHAR(30) NOT NULL,starts_at DATETIME NULL,ends_at DATETIME NULL,updated_at DATETIME NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,campaign_type VARCHAR(80) NOT NULL) ENGINE=InnoDB",
"CREATE TABLE loyalty_quest_participations(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,public_id CHAR(36) NOT NULL,campaign_id BIGINT UNSIGNED NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,participant_user_id BIGINT UNSIGNED NOT NULL,contact_id BIGINT UNSIGNED NULL,status VARCHAR(30) NOT NULL,progress_count INT NOT NULL DEFAULT 0,required_count INT NOT NULL DEFAULT 1,completion_percent INT NOT NULL DEFAULT 0,joined_at DATETIME NULL,started_at DATETIME NULL,submitted_at DATETIME NULL,reviewed_at DATETIME NULL,completed_at DATETIME NULL,last_activity_at DATETIME NULL,created_at DATETIME NOT NULL,updated_at DATETIME NULL) ENGINE=InnoDB",
"CREATE TABLE loyalty_quest_evidence(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,public_id CHAR(36) NOT NULL,participation_id BIGINT UNSIGNED NOT NULL,campaign_id BIGINT UNSIGNED NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,participant_user_id BIGINT UNSIGNED NOT NULL,evidence_type VARCHAR(40) NOT NULL,status VARCHAR(30) NOT NULL,code_hash CHAR(64) NULL,latitude DECIMAL(10,7) NULL,longitude DECIMAL(10,7) NULL,accuracy_meters DECIMAL(10,2) NULL,distance_meters DECIMAL(10,2) NULL,proof_url VARCHAR(700) NULL,proof_note TEXT NULL,reference_id VARCHAR(190) NULL,reviewer_user_id BIGINT UNSIGNED NULL,review_note TEXT NULL,verified_at DATETIME NULL,rejected_at DATETIME NULL,metadata_json JSON NULL,created_at DATETIME NOT NULL,updated_at DATETIME NULL) ENGINE=InnoDB",
"CREATE TABLE campaign_events(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,public_id CHAR(36) NOT NULL,merchant_user_id BIGINT UNSIGNED NOT NULL,campaign_id BIGINT UNSIGNED NOT NULL,wallet_item_id BIGINT UNSIGNED NULL,contact_id BIGINT UNSIGNED NULL,event_type VARCHAR(100) NOT NULL,event_context_json JSON NULL,created_at DATETIME NOT NULL) ENGINE=InnoDB",
"CREATE TABLE audit_logs(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,user_id BIGINT UNSIGNED NULL,action VARCHAR(120) NOT NULL,entity_type VARCHAR(80) NOT NULL,metadata_json JSON NULL,ip_address VARCHAR(64) NULL,user_agent VARCHAR(255) NULL,created_at DATETIME NOT NULL) ENGINE=InnoDB",
"CREATE TABLE security_logs(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,severity VARCHAR(20) NOT NULL,event_type VARCHAR(120) NOT NULL,user_id BIGINT UNSIGNED NULL,request_id VARCHAR(80) NULL,message VARCHAR(255) NOT NULL,context_json JSON NULL,ip_address VARCHAR(64) NULL,user_agent VARCHAR(255) NULL,created_at DATETIME NOT NULL) ENGINE=InnoDB",
"CREATE TABLE rate_limits(id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,action VARCHAR(120) NOT NULL,identifier_hash CHAR(64) NOT NULL,attempts INT NOT NULL,first_seen_at DATETIME NOT NULL,last_seen_at DATETIME NOT NULL,locked_until DATETIME NULL,created_at DATETIME NOT NULL,updated_at DATETIME NOT NULL,UNIQUE KEY uq_rate_limits_action_identifier(action,identifier_hash)) ENGINE=InnoDB",
];
foreach($schema as $statement)$pdo->exec($statement);

$migration=(string)file_get_contents(dirname(__DIR__).'/database/loyalty_quest_integrity_controls_v1.sql');
$pdo->exec($migration);
$pdo->exec($migration);

$now=gmdate('Y-m-d H:i:s');
$ago=static fn(int $seconds):string=>gmdate('Y-m-d H:i:s',time()-$seconds);
$users=[[1,'merchant@example.test','Merchant','Merchant'],[9,'admin@example.test','Admin','Admin']];
for($id=101;$id<=125;$id++)$users[]=[$id,"participant{$id}@example.test","Participant {$id}","Participant {$id}"];
$insertUser=$pdo->prepare("INSERT INTO users(id,email,display_name,full_name,status) VALUES (?,?,?,?,'active')");
foreach($users as $row)$insertUser->execute($row);
$pdo->exec("INSERT INTO merchant_workspaces(merchant_user_id,display_name) VALUES (1,'Integrity Test Merchant')");
$pdo->exec("INSERT INTO public_profiles(user_id,display_name) VALUES (1,'Integrity Merchant')");
$pdo->prepare("INSERT INTO campaigns(id,public_id,public_slug,title,status,starts_at,ends_at,updated_at,merchant_user_id,campaign_type) VALUES (1,?,?,?,?,?,?,?,1,'loyalty_quest')")
    ->execute(['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','integrity-quest','Integrity Test Quest','active',$ago(3600),null,$now]);
$pdo->prepare("INSERT INTO campaigns(id,public_id,public_slug,title,status,starts_at,ends_at,updated_at,merchant_user_id,campaign_type) VALUES (2,?,?,?,?,?,?,?,1,'contest_giveaway')")
    ->execute(['bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','not-a-quest','Not a Quest','active',$ago(3600),null,$now]);

$insertParticipation=$pdo->prepare("INSERT INTO loyalty_quest_participations(id,public_id,campaign_id,merchant_user_id,participant_user_id,contact_id,status,progress_count,required_count,completion_percent,joined_at,started_at,completed_at,last_activity_at,created_at,updated_at) VALUES (?,?,?,?,?,NULL,?,?,?,?,?,?,?,?,?,?)");
$insertParticipation->execute([1,'11111111-1111-4111-8111-111111111111',1,1,101,'in_progress',0,1,0,$ago(5),$ago(5),null,$now,$ago(5),$now]);
$insertParticipation->execute([2,'22222222-2222-4222-8222-222222222222',1,1,102,'pending_review',0,1,0,$ago(7200),$ago(7200),null,$now,$ago(7200),$now]);
for($i=0;$i<8;$i++){
    $id=10+$i;
    $insertParticipation->execute([$id,sprintf('33333333-3333-4333-8333-%012d',$id),1,1,101,'completed',1,1,100,$ago(80000),$ago(80000),$ago(3600+$i),$now,$ago(80000),$now]);
}

$requestContext=mg_lqi_gate_request($pdo,101,'submit','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
$campaign=['id'=>1,'public_id'=>'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','merchant_user_id'=>1,'rules'=>['minimum_completion_seconds'=>60]];
$participation=['id'=>1,'public_id'=>'11111111-1111-4111-8111-111111111111','joined_at'=>$ago(5),'started_at'=>$ago(5)];
$participant=['id'=>101];
mg_lqi_record_attempt($pdo,$campaign,101,'submit',$requestContext,'allowed');
for($id=103;$id<=107;$id++){
    $pdo->prepare("INSERT INTO loyalty_quest_integrity_attempts(public_id,campaign_id,merchant_user_id,participant_user_id,action_type,outcome,ip_hash,device_hash,request_hash,created_at) VALUES (?,1,1,?,'submit','allowed',?,?,?,NOW())")
        ->execute([mg_public_uuid(),$id,$requestContext['ip_hash'],$requestContext['device_hash'],hash('sha256','request-'.$id)]);
}

$duplicateSource=['reference_id'=>'ORDER-7788','proof_url'=>null,'proof_note'=>'Shared receipt proof note with enough identifying detail.'];
$duplicateFingerprint=mg_lqi_evidence_fingerprint($duplicateSource);
$insertEvidence=$pdo->prepare("INSERT INTO loyalty_quest_evidence(public_id,participation_id,campaign_id,merchant_user_id,participant_user_id,evidence_type,status,code_hash,latitude,longitude,accuracy_meters,distance_meters,proof_url,proof_note,reference_id,evidence_fingerprint,ip_hash,device_hash,integrity_score,integrity_status,verified_at,rejected_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$insertEvidence->execute(['44444444-4444-4444-8444-444444444444',2,1,1,102,'receipt','verified',null,null,null,null,null,null,$duplicateSource['proof_note'],$duplicateSource['reference_id'],$duplicateFingerprint,str_repeat('1',64),str_repeat('2',64),0,'clear',$ago(7200),null,null,$ago(7200),$now]);
$insertEvidence->execute(['55555555-5555-4555-8555-555555555555',1,1,1,101,'geolocation','verified',null,33.4484,-112.0740,15,10,null,null,null,null,str_repeat('3',64),str_repeat('4',64),0,'clear',$ago(1800),null,null,$ago(1800),$now]);
for($i=0;$i<3;$i++)$insertEvidence->execute([sprintf('66666666-6666-4666-8666-%012d',$i),1,1,1,101,'receipt','rejected',null,null,null,null,null,null,'Rejected evidence example '.($i+1),null,null,str_repeat('5',64),str_repeat('6',64),0,'clear',null,$ago(86400),null,$ago(86400),$now]);
$sharedCode=hash('sha256','SHARED-CODE');
for($id=103;$id<=122;$id++)$insertEvidence->execute([sprintf('77777777-7777-4777-8777-%012d',$id),1,1,1,$id,'manual_code','verified',$sharedCode,null,null,null,null,null,null,null,null,str_repeat('7',64),str_repeat('8',64),0,'clear',$ago(120),null,null,$ago(120),$now]);

$currentEvidence=[
    'reference_id'=>$duplicateSource['reference_id'],
    'proof_url'=>null,
    'proof_note'=>$duplicateSource['proof_note'],
    'verification_type'=>'geolocation',
    'latitude'=>34.0522,
    'longitude'=>-118.2437,
    'code_hash'=>$sharedCode,
];
$integrity=mg_lqi_evaluate($pdo,$campaign,$participation,$participant,$currentEvidence,$requestContext);
$currentId='88888888-8888-4888-8888-888888888888';
$insertEvidence->execute([$currentId,1,1,1,101,'geolocation','submitted',$sharedCode,34.0522,-118.2437,10,20,null,$currentEvidence['proof_note'],$currentEvidence['reference_id'],$integrity['evidence_fingerprint'],$integrity['ip_hash'],$integrity['device_hash'],$integrity['score'],$integrity['status'],null,null,null,$now,$now]);
$currentDbId=(int)$pdo->lastInsertId();
$signalIds=mg_lqi_persist_signals($pdo,$campaign,$participation,$participant,$currentDbId,$integrity);
mg_lqi_persist_signals($pdo,$campaign,$participation,$participant,$currentDbId,$integrity);
mg_lqi_security_event($campaign,$participation,$integrity,101);

$singleEvidence='99999999-9999-4999-8999-999999999999';
$insertEvidence->execute([$singleEvidence,2,1,1,102,'receipt','submitted',null,null,null,null,null,null,'Standalone integrity review note','REVIEW-1',mg_lqi_hash('evidence','standalone'),str_repeat('9',64),str_repeat('a',64),90,'review',null,null,null,$now,$now]);
$singleEvidenceId=(int)$pdo->lastInsertId();
$singleSignal='aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
$pdo->prepare("INSERT INTO loyalty_quest_integrity_signals(public_id,campaign_id,merchant_user_id,participant_user_id,participation_id,evidence_id,signal_type,severity,score,status,source_hash,context_json,created_at,updated_at) VALUES (?,1,1,102,2,?,'duplicate_evidence','critical',90,'open',?,?,NOW(),NOW())")
    ->execute([$singleSignal,$singleEvidenceId,mg_lqi_hash('signal','single'),json_encode(['distance_km'=>600,'ip_hash'=>'unsafe-hash','proof_url'=>'https://private.example.test/proof'])]);

$pdo->beginTransaction();
$confirmed=mg_lqi_admin_resolve($pdo,9,$singleSignal,'confirmed','Confirmed coordinated duplicate evidence after review.');
$pdo->commit();
$blockedState=(string)$pdo->query("SELECT integrity_status FROM loyalty_quest_evidence WHERE id={$singleEvidenceId}")->fetchColumn();
$pdo->beginTransaction();
$cleared=mg_lqi_admin_resolve($pdo,9,$singleSignal,'cleared','Cleared after documented administrator re-review.');
$pdo->commit();
$clearedState=(string)$pdo->query("SELECT integrity_status FROM loyalty_quest_evidence WHERE id={$singleEvidenceId}")->fetchColumn();
$adminSignals=mg_lqi_admin_signals($pdo,'cleared','all','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','',50);
$singleOutput=array_values(array_filter($adminSignals,static fn(array $row):bool=>$row['id']==='aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'))[0]??[];

$types=array_values(array_unique(array_map(static fn(array $signal):string=>(string)$signal['type'],$integrity['signals'])));
$storedSignalCount=(int)$pdo->query("SELECT COUNT(*) FROM loyalty_quest_integrity_signals WHERE evidence_id={$currentDbId}")->fetchColumn();
$migrationCount=(int)$pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE migration_key='loyalty_quest_integrity_controls_v1'")->fetchColumn();
$columnCount=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='loyalty_quest_evidence' AND COLUMN_NAME IN ('evidence_fingerprint','ip_hash','device_hash','integrity_score','integrity_status')")->fetchColumn();
$rateLimitCount=(int)$pdo->query("SELECT COUNT(*) FROM rate_limits WHERE action LIKE 'loyalty_quest.submit.%'")->fetchColumn();
$rawStorage=json_encode([
    $pdo->query('SELECT ip_hash,device_hash,request_hash FROM loyalty_quest_integrity_attempts')->fetchAll(PDO::FETCH_ASSOC),
    $pdo->query('SELECT ip_hash,device_hash,evidence_fingerprint FROM loyalty_quest_evidence')->fetchAll(PDO::FETCH_ASSOC),
],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$adminJson=json_encode($singleOutput,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
$eventCount=(int)$pdo->query("SELECT COUNT(*) FROM campaign_events WHERE event_type IN ('quest.admin_integrity_confirmed','quest.admin_integrity_cleared')")->fetchColumn();
$auditCount=(int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action IN ('admin.loyalty_quest_integrity_confirmed','admin.loyalty_quest_integrity_cleared')")->fetchColumn();
$walletTable=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='wallet_items'")->fetchColumn();

$checks=[];
$check=static function(string $name,bool $ok,mixed $actual=null)use(&$checks):void{$checks[]=['name'=>$name,'ok'=>$ok,'actual'=>$actual];};
$check('migration repeat execution',$migrationCount===1&&$columnCount===5,['migration_count'=>$migrationCount,'column_count'=>$columnCount]);
$check('schema ready',mg_lqi_schema_ready($pdo));
$check('database backed request throttles',$rateLimitCount===3,$rateLimitCount);
$check('raw IP and device values are not stored',!str_contains($rawStorage,'203.0.113.10')&&!str_contains($rawStorage,str_repeat('d',64)),$rawStorage);
$check('duplicate evidence routes review',$integrity['decision']==='review'&&in_array('duplicate_evidence',$types,true),$types);
$check('shared IP velocity',in_array('shared_ip_velocity',$types,true),$types);
$check('shared device velocity',in_array('shared_device_velocity',$types,true),$types);
$check('rapid completion',in_array('rapid_completion',$types,true),$types);
$check('rejection and reward velocity',in_array('rejection_history',$types,true)&&in_array('reward_velocity',$types,true),$types);
$check('impossible travel',in_array('impossible_travel',$types,true),$types);
$check('evidence scoped signal idempotency',$storedSignalCount===count($integrity['signals'])&&count($signalIds)===$storedSignalCount,['stored'=>$storedSignalCount,'expected'=>count($integrity['signals'])]);
$check('confirmed signal blocks evidence',$confirmed['status']==='confirmed'&&$blockedState==='blocked',$blockedState);
$check('confirmed signal can be cleared',$cleared['old_status']==='confirmed'&&$cleared['status']==='cleared'&&$clearedState==='clear',$clearedState);
$check('sensitive context excluded',!str_contains($adminJson,'unsafe-hash')&&!str_contains($adminJson,'private.example.test')&&str_contains($adminJson,'distance_km'),$adminJson);
$check('participant email masked',str_contains($adminJson,'pa')&&!str_contains($adminJson,'participant102@example.test'),$adminJson);
$check('admin event and audit evidence',$eventCount===2&&$auditCount===2,['events'=>$eventCount,'audits'=>$auditCount]);
$check('no parallel reward authority',$walletTable===0,$walletTable);

$failed=array_values(array_filter($checks,static fn(array $row):bool=>!$row['ok']));
echo json_encode(['ok'=>$failed===[],'checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($failed===[]?0:1);
