<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';

final class MgWebhookIntakeException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus=409){parent::__construct($message);}
}

function mg_webhook_intake_install(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS provider_webhook_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,provider_key VARCHAR(80) NOT NULL,provider_event_id VARCHAR(190) NOT NULL,event_type VARCHAR(120) NOT NULL,payload_hash CHAR(64) NOT NULL,payload_json JSON NULL,status ENUM('accepted','processed','retryable','quarantined','rejected') NOT NULL DEFAULT 'accepted',dispatch_key VARCHAR(190) NULL,error_message VARCHAR(500) NULL,received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,processed_at DATETIME NULL,PRIMARY KEY(id),UNIQUE KEY uq_provider_webhook_public_id(public_id),UNIQUE KEY uq_provider_webhook_event(provider_key,provider_event_id),KEY idx_provider_webhook_status(status,received_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS provider_webhook_quarantine (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,public_id CHAR(36) NOT NULL,provider_key VARCHAR(80) NOT NULL,provider_event_id VARCHAR(190) NOT NULL,reason VARCHAR(160) NOT NULL,payload_hash CHAR(64) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_provider_webhook_quarantine_public_id(public_id),KEY idx_provider_webhook_quarantine(provider_key,provider_event_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function mg_webhook_intake_ensure(PDO $pdo): void{if(!$pdo->inTransaction())mg_webhook_intake_install($pdo);}function mg_webhook_json(array $v): string{return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}function mg_webhook_scalar(PDO $pdo,string $sql,array $p=[]): mixed{$s=$pdo->prepare($sql);$s->execute($p);return $s->fetchColumn();}
function mg_webhook_redact(array $payload): array{foreach($payload as $k=>$v){$lk=strtolower((string)$k);$secret=str_contains($lk,'secret')||str_contains($lk,'token')||str_contains($lk,'signature')||str_contains($lk,'password')||str_contains($lk,'private');$payload[$k]=$secret?'[REDACTED]':(is_array($v)?mg_webhook_redact($v):$v);}return $payload;}
function mg_webhook_signature(string $secret,string $timestamp,string $body): string{return hash_hmac('sha256',$timestamp.'.'.$body,$secret);}
function mg_webhook_verify_signature(string $secret,string $timestamp,string $body,string $signature,int $now,int $tolerance=300): bool{if($secret===''||$timestamp===''||$signature==='')return false;if(abs($now-(int)$timestamp)>$tolerance)return false;return hash_equals(mg_webhook_signature($secret,$timestamp,$body),$signature);}
function mg_webhook_quarantine(PDO $pdo,string $provider,string $eventId,string $reason,string $hash): void{$pdo->prepare('INSERT INTO provider_webhook_quarantine (public_id,provider_key,provider_event_id,reason,payload_hash,created_at) VALUES (?,?,?,?,?,NOW())')->execute([mg_public_uuid(),$provider,$eventId,$reason,$hash]);}
function mg_webhook_intake(PDO $pdo,array $input,callable $dispatcher,?callable $failureHook=null): array
{
    mg_webhook_intake_ensure($pdo);$provider=trim((string)($input['provider_key']??''));$eventId=trim((string)($input['provider_event_id']??''));$type=trim((string)($input['event_type']??''));$body=(string)($input['raw_body']??'');$secret=(string)($input['secret']??'');$timestamp=(string)($input['timestamp']??'');$signature=(string)($input['signature']??'');$now=(int)($input['now']??time());
    if($provider===''||$eventId===''||$type===''||$body==='')throw new MgWebhookIntakeException('Invalid webhook envelope.',422);
    $hash=hash('sha256',$body);$payload=json_decode($body,true);if(!is_array($payload))throw new MgWebhookIntakeException('Invalid webhook payload.',422);
    $owns=!$pdo->inTransaction();if($owns)$pdo->beginTransaction();
    try{
        if(!mg_webhook_verify_signature($secret,$timestamp,$body,$signature,$now)){mg_webhook_quarantine($pdo,$provider,$eventId,'signature_invalid',$hash);if($owns)$pdo->commit();throw new MgWebhookIntakeException('Invalid webhook signature.',401);}
        $existing=$pdo->prepare('SELECT * FROM provider_webhook_events WHERE provider_key=? AND provider_event_id=? LIMIT 1 FOR UPDATE');$existing->execute([$provider,$eventId]);
        if($row=$existing->fetch(PDO::FETCH_ASSOC)){if(!hash_equals((string)$row['payload_hash'],$hash)||(string)$row['event_type']!==$type){mg_webhook_quarantine($pdo,$provider,$eventId,'conflicting_replay',$hash);$pdo->prepare("UPDATE provider_webhook_events SET status='quarantined',error_message='conflicting replay' WHERE id=?")->execute([(int)$row['id']]);if($owns)$pdo->commit();throw new MgWebhookIntakeException('Webhook replay conflicts with recorded event.',409);}if($owns)$pdo->commit();return ['event_id'=>$row['public_id'],'status'=>$row['status'],'duplicate'=>true];}
        $public=mg_public_uuid();$pdo->prepare("INSERT INTO provider_webhook_events (public_id,provider_key,provider_event_id,event_type,payload_hash,payload_json,status,received_at) VALUES (?,?,?,?,?,?, 'accepted',NOW())")->execute([$public,$provider,$eventId,$type,$hash,mg_webhook_json(mg_webhook_redact($payload))]);
        if($failureHook)$failureHook('after_record',['event_id'=>$public]);
        try{$result=$dispatcher($pdo,$provider,$type,$payload);$pdo->prepare("UPDATE provider_webhook_events SET status='processed',dispatch_key=?,processed_at=NOW() WHERE public_id=?")->execute([(string)($result['dispatch_key']??$type),$public]);if($owns)$pdo->commit();return ['event_id'=>$public,'status'=>'processed','duplicate'=>false,'dispatch'=>$result];}
        catch(Throwable $dispatchError){$pdo->prepare("UPDATE provider_webhook_events SET status='retryable',error_message=? WHERE public_id=?")->execute([mb_substr($dispatchError->getMessage(),0,500),$public]);if($owns)$pdo->commit();return ['event_id'=>$public,'status'=>'retryable','duplicate'=>false,'error'=>$dispatchError->getMessage()];}
    }catch(Throwable $e){if($owns&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}
