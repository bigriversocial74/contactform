<?php
declare(strict_types=1);
require_once __DIR__ . '/_distribution.php';

mg_require_method('POST');
$sourceId=trim((string)($_GET['source']??''));
$signature=strtolower(trim((string)($_SERVER['HTTP_X_MICROGIFTER_SIGNATURE']??'')));
$timestamp=trim((string)($_SERVER['HTTP_X_MICROGIFTER_TIMESTAMP']??''));
$raw=file_get_contents('php://input');
if($sourceId===''||!is_string($raw)||$raw==='')mg_fail('Invalid webhook request.',422);
if(!ctype_digit($timestamp)||abs(time()-(int)$timestamp)>300)mg_fail('Webhook timestamp is invalid.',401);
$rootSecret=trim((string)getenv('MG_DISTRIBUTION_WEBHOOK_SECRET'));
if($rootSecret==='')mg_fail('Distribution webhooks are not configured.',503);
$pdo=mg_db();
$stmt=$pdo->prepare("SELECT * FROM distribution_source_connections WHERE public_id=? AND status='active' LIMIT 1");
$stmt->execute([$sourceId]);
$source=$stmt->fetch();
if(!$source)mg_fail('Webhook source not found.',404);
$connectionSecret=hash_hmac('sha256',$sourceId.'|'.(string)$source['provider_key'],$rootSecret);
$expected=hash_hmac('sha256',$timestamp.'.'.$raw,$connectionSecret);
if($signature===''||!hash_equals($expected,$signature))mg_fail('Webhook signature is invalid.',401);
$payload=json_decode($raw,true);
if(!is_array($payload))mg_fail('Webhook payload must be JSON.',422);
$event=mg_distribution_normalize_event($payload);
$attemptId=mg_distribution_uuid();
try{
 $pdo->beginTransaction();
 $existing=$pdo->prepare('SELECT public_id,status,payload_checksum FROM distribution_source_events WHERE merchant_user_id=? AND idempotency_key=? LIMIT 1 FOR UPDATE');
 $existing->execute([(int)$source['merchant_user_id'],$event['idempotency_key']]);
 $duplicate=$existing->fetch();
 if($duplicate){
  if(!hash_equals((string)$duplicate['payload_checksum'],$event['payload_checksum']))mg_fail('Webhook idempotency conflict.',409);
  $pdo->prepare("INSERT INTO distribution_webhook_attempts (public_id,connection_id,direction,event_type,status,http_status,request_checksum,occurred_at) VALUES (?,?,'inbound',?,'accepted',200,?,NOW())")
   ->execute([$attemptId,(int)$source['id'],$event['event_type'],$event['payload_checksum']]);
  $pdo->commit();
  mg_ok(['event_id'=>$duplicate['public_id'],'duplicate'=>true],'Webhook already processed.');
 }
 $eventId=mg_distribution_uuid();
 $pdo->prepare("INSERT INTO distribution_source_events (public_id,connection_id,merchant_user_id,program_id,source_type,external_event_id,event_type,idempotency_key,payload_json,payload_checksum,status,received_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,'validated',NOW(),NOW(),NOW())")
  ->execute([$eventId,(int)$source['id'],(int)$source['merchant_user_id'],$source['program_id']?:null,$event['source_type'],$event['external_event_id'],$event['event_type'],$event['idempotency_key'],$event['payload_json'],$event['payload_checksum']]);
 $eventDbId=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO distribution_webhook_attempts (public_id,connection_id,source_event_id,direction,event_type,status,http_status,request_checksum,occurred_at) VALUES (?,?,?,'inbound',?,'accepted',202,?,NOW())")
  ->execute([$attemptId,(int)$source['id'],$eventDbId,$event['event_type'],$event['payload_checksum']]);
 $pdo->prepare('UPDATE distribution_source_connections SET last_event_at=NOW(),updated_at=NOW() WHERE id=?')->execute([(int)$source['id']]);
 $pdo->commit();
 mg_ok(['event_id'=>$eventId,'duplicate'=>false],'Webhook accepted.',202);
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 mg_fail('Unable to process webhook.',500);
}
