<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';

final class MgDeliveryException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus=409){parent::__construct($message);}
}

function mg_delivery_install_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS message_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,event_key VARCHAR(190) NOT NULL,event_fingerprint CHAR(64) NOT NULL,event_type VARCHAR(100) NOT NULL,category VARCHAR(60) NOT NULL DEFAULT 'transactional',payload_json JSON NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_message_events_public_id(public_id),UNIQUE KEY uq_message_events_key(event_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS message_delivery_jobs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,message_event_id BIGINT UNSIGNED NOT NULL,recipient_user_id BIGINT UNSIGNED NULL,channel ENUM('in_app','email','sms','webhook') NOT NULL,template_key VARCHAR(120) NOT NULL,status ENUM('queued','processing','retrying','delivered','failed','dead_letter','suppressed') NOT NULL DEFAULT 'queued',attempt_count INT NOT NULL DEFAULT 0,max_attempts INT NOT NULL DEFAULT 3,next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,provider_message_id VARCHAR(190) NULL,last_error VARCHAR(500) NULL,recipient_snapshot_json JSON NULL,payload_snapshot_json JSON NULL,delivered_at DATETIME NULL,failed_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_message_delivery_jobs_public_id(public_id),UNIQUE KEY uq_message_delivery_jobs_event_channel(message_event_id,channel,recipient_user_id),KEY idx_message_delivery_jobs_claim(status,next_attempt_at,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS message_delivery_attempts (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,job_id BIGINT UNSIGNED NOT NULL,attempt_no INT NOT NULL,provider_key VARCHAR(80) NOT NULL,status ENUM('success','transient_failure','permanent_failure') NOT NULL,error_code VARCHAR(100) NULL,error_message VARCHAR(500) NULL,provider_response_json JSON NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_message_delivery_attempts_public_id(public_id),UNIQUE KEY uq_message_delivery_attempts_job_attempt(job_id,attempt_no)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS message_provider_callbacks (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,provider_key VARCHAR(80) NOT NULL,provider_event_id VARCHAR(190) NOT NULL,job_id BIGINT UNSIGNED NOT NULL,event_type VARCHAR(100) NOT NULL,payload_hash CHAR(64) NOT NULL,payload_json JSON NULL,status ENUM('processed','ignored') NOT NULL DEFAULT 'processed',received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,processed_at DATETIME NULL,PRIMARY KEY(id),UNIQUE KEY uq_message_provider_callbacks_public_id(public_id),UNIQUE KEY uq_message_provider_callbacks_event(provider_key,provider_event_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS message_suppression_rules (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,user_id BIGINT UNSIGNED NOT NULL,channel ENUM('in_app','email','sms','webhook') NOT NULL,category VARCHAR(60) NOT NULL,status ENUM('active','inactive') NOT NULL DEFAULT 'active',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_message_suppression(user_id,channel,category)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function mg_delivery_ensure_schema(PDO $pdo): void
{
    if(!$pdo->inTransaction())mg_delivery_install_schema($pdo);
}
function mg_delivery_user_exists(PDO $pdo,int $userId): bool
{
    if($userId<1)return false;
    $stmt=$pdo->prepare('SELECT 1 FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$userId]);
    return (bool)$stmt->fetchColumn();
}
function mg_delivery_json(array $value): string {return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function mg_delivery_redact(array $payload): array
{
    $out=[];
    foreach($payload as $key=>$value){$lk=strtolower((string)$key);$secret=str_contains($lk,'token')||str_contains($lk,'secret')||str_contains($lk,'password')||str_contains($lk,'claim_code')||str_contains($lk,'authorization')||str_contains($lk,'private');$out[$key]=$secret?'[REDACTED]':(is_array($value)?mg_delivery_redact($value):$value);}
    return $out;
}
function mg_delivery_fingerprint(array $input): string
{
    $data=['recipient_user_id'=>(int)($input['recipient_user_id']??0),'channel'=>(string)($input['channel']??''),'template_key'=>(string)($input['template_key']??''),'category'=>(string)($input['category']??'transactional'),'payload'=>mg_delivery_redact((array)($input['payload']??[]))];
    return hash('sha256',mg_delivery_json($data));
}
function mg_delivery_suppressed(PDO $pdo,int $userId,string $channel,string $category): bool
{
    if($userId<1||$category==='security')return false;
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM message_suppression_rules WHERE user_id=? AND channel=? AND category=? AND status='active'");$stmt->execute([$userId,$channel,$category]);return (int)$stmt->fetchColumn()>0;
}
function mg_delivery_enqueue(PDO $pdo,array $input,?callable $failureHook=null): array
{
    mg_delivery_ensure_schema($pdo);
    $key=trim((string)($input['idempotency_key']??''));$channel=(string)($input['channel']??'');$template=trim((string)($input['template_key']??''));$recipient=(int)($input['recipient_user_id']??0);$category=(string)($input['category']??'transactional');
    if($key===''||$template===''||$recipient<1||!in_array($channel,['in_app','email','sms','webhook'],true))throw new MgDeliveryException('Invalid message delivery request.',422);
    $payload=mg_delivery_redact((array)($input['payload']??[]));$fingerprint=mg_delivery_fingerprint($input);$owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
    try{
        $jobRecipientId=mg_delivery_user_exists($pdo,$recipient)?$recipient:null;
        $find=$pdo->prepare('SELECT e.*,j.public_id job_public_id,j.status job_status FROM message_events e INNER JOIN message_delivery_jobs j ON j.message_event_id=e.id WHERE e.event_key=? LIMIT 1 FOR UPDATE');$find->execute([$key]);
        if($row=$find->fetch(PDO::FETCH_ASSOC)){if(!hash_equals((string)$row['event_fingerprint'],$fingerprint))throw new MgDeliveryException('Message event conflicts with the recorded payload.',409);if($owns)$pdo->commit();return ['event_id'=>$row['public_id'],'job_id'=>$row['job_public_id'],'status'=>$row['job_status'],'duplicate'=>true];}
        $eventPublic=mg_public_uuid();$jobPublic=mg_public_uuid();$status=mg_delivery_suppressed($pdo,$jobRecipientId??0,$channel,$category)?'suppressed':'queued';
        $pdo->prepare('INSERT INTO message_events (public_id,event_key,event_fingerprint,event_type,category,payload_json,created_at) VALUES (?,?,?,?,?,?,NOW())')->execute([$eventPublic,$key,$fingerprint,(string)($input['event_type']??'message.event'),$category,mg_delivery_json($payload)]);
        $eventId=(int)$pdo->lastInsertId();
        if($failureHook)$failureHook('after_event',['event_id'=>$eventPublic]);
        $snapshot=(array)($input['recipient_snapshot']??[]);if($jobRecipientId===null)$snapshot['recipient_user_id']=$recipient;
        $pdo->prepare('INSERT INTO message_delivery_jobs (public_id,message_event_id,recipient_user_id,channel,template_key,status,max_attempts,recipient_snapshot_json,payload_snapshot_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([$jobPublic,$eventId,$jobRecipientId,$channel,$template,$status,(int)($input['max_attempts']??3),mg_delivery_json($snapshot),mg_delivery_json($payload)]);
        if($failureHook)$failureHook('before_complete',['job_id'=>$jobPublic]);
        if($owns)$pdo->commit();return ['event_id'=>$eventPublic,'job_id'=>$jobPublic,'status'=>$status,'duplicate'=>false];
    }catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function mg_delivery_claim_next(PDO $pdo): ?array
{
    mg_delivery_ensure_schema($pdo);
    $stmt=$pdo->query("SELECT * FROM message_delivery_jobs WHERE status IN ('queued','retrying') AND next_attempt_at<=NOW() ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED");$job=$stmt->fetch(PDO::FETCH_ASSOC);if(!$job)return null;
    $pdo->prepare("UPDATE message_delivery_jobs SET status='processing',updated_at=NOW() WHERE id=? AND status IN ('queued','retrying')")->execute([(int)$job['id']]);$job['status']='processing';return $job;
}
function mg_delivery_process_job(PDO $pdo,array $job,array $providerResult,?callable $failureHook=null): array
{
    mg_delivery_ensure_schema($pdo);
    $owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
    try{
        $lock=$pdo->prepare('SELECT * FROM message_delivery_jobs WHERE id=? FOR UPDATE');$lock->execute([(int)$job['id']]);$job=$lock->fetch(PDO::FETCH_ASSOC);if(!$job)throw new MgDeliveryException('Delivery job not found.',404);if(in_array((string)$job['status'],['delivered','failed','dead_letter','suppressed'],true)){if($owns)$pdo->commit();return ['duplicate'=>true,'status'=>$job['status']];}
        $attempt=(int)$job['attempt_count']+1;$kind=(string)($providerResult['status']??'success');
        $pdo->prepare('INSERT INTO message_delivery_attempts (public_id,job_id,attempt_no,provider_key,status,error_code,error_message,provider_response_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')->execute([mg_public_uuid(),(int)$job['id'],$attempt,(string)($providerResult['provider_key']??'sandbox'),$kind,$providerResult['error_code']??null,$providerResult['error_message']??null,mg_delivery_json($providerResult)]);
        if($failureHook)$failureHook('after_attempt',['job_id'=>$job['public_id'],'attempt'=>$attempt]);
        if($kind==='success'){$status='delivered';$pdo->prepare("UPDATE message_delivery_jobs SET status='delivered',attempt_count=?,provider_message_id=?,delivered_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$attempt,$providerResult['provider_message_id']??null,(int)$job['id']]);}
        elseif($kind==='transient_failure'&&$attempt<(int)$job['max_attempts']){$status='retrying';$pdo->prepare("UPDATE message_delivery_jobs SET status='retrying',attempt_count=?,last_error=?,next_attempt_at=DATE_ADD(NOW(),INTERVAL ? SECOND),updated_at=NOW() WHERE id=?")->execute([$attempt,$providerResult['error_message']??'Transient failure',(int)($providerResult['retry_after_seconds']??60),(int)$job['id']]);}
        else{$status=$kind==='permanent_failure'?'failed':'dead_letter';$pdo->prepare('UPDATE message_delivery_jobs SET status=?,attempt_count=?,last_error=?,failed_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$status,$attempt,$providerResult['error_message']??'Delivery failed',(int)$job['id']]);}
        if($owns)$pdo->commit();return ['status'=>$status,'attempt_no'=>$attempt,'duplicate'=>false];
    }catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function mg_delivery_process_callback(PDO $pdo,string $provider,string $eventId,string $type,array $payload): array
{
    mg_delivery_ensure_schema($pdo);$hash=hash('sha256',mg_delivery_json($payload));$jobPublic=(string)($payload['job_id']??'');$stmt=$pdo->prepare('SELECT id FROM message_delivery_jobs WHERE public_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$jobPublic]);$jobId=(int)$stmt->fetchColumn();if($jobId<1)throw new MgDeliveryException('Callback delivery job not found.',404);
    $existing=$pdo->prepare('SELECT * FROM message_provider_callbacks WHERE provider_key=? AND provider_event_id=? LIMIT 1 FOR UPDATE');$existing->execute([$provider,$eventId]);if($row=$existing->fetch(PDO::FETCH_ASSOC)){if(!hash_equals((string)$row['payload_hash'],$hash)||$row['event_type']!==$type)throw new MgDeliveryException('Provider callback conflicts with the recorded payload.',409);return ['duplicate'=>true,'status'=>$row['status']];}
    $pdo->prepare('INSERT INTO message_provider_callbacks (public_id,provider_key,provider_event_id,job_id,event_type,payload_hash,payload_json,status,received_at,processed_at) VALUES (?,?,?,?,?,?,?,' . "'processed'" . ',NOW(),NOW())')->execute([mg_public_uuid(),$provider,$eventId,$jobId,$type,$hash,mg_delivery_json($payload)]);return ['duplicate'=>false,'status'=>'processed'];
}