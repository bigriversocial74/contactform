<?php
declare(strict_types=1);
require_once __DIR__ . '/_payment_events.php';
require_once __DIR__ . '/_recovery_webhook.php';
require_once dirname(__DIR__) . '/payments/_payments.php';
mg_require_method('POST');
$provider=trim((string)($_GET['provider']??'stripe'));
$payload=file_get_contents('php://input')?:'';
$signature=(string)($_SERVER['HTTP_X_MG_SIGNATURE']??'');
$event=json_decode($payload,true);
if(!is_array($event)||!mg_payment_verify_signature($provider,$payload,$signature))mg_fail('Invalid tip payment webhook.',401);
$eventId=trim((string)($event['id']??''));
$type=trim((string)($event['type']??''));
if($eventId===''||$type==='')mg_fail('Invalid tip payment event.',422);
$pdo=mg_db();
try{
    $pdo->prepare("INSERT INTO payment_webhook_events (public_id,provider_key,provider_event_id,event_type,signature_valid,status,payload_hash,payload_json,received_at) VALUES (?,?,?,?,1,'received',?,?,NOW())")
        ->execute([mg_public_uuid(),$provider,$eventId,$type,hash('sha256',$payload),$payload]);
}catch(Throwable $e){
    if(str_contains($e->getMessage(),'Duplicate'))mg_ok(['duplicate'=>true],'Tip payment webhook already received.');
    throw $e;
}
$pdo->beginTransaction();
try{
    $pdo->prepare("UPDATE payment_webhook_events SET status='processing' WHERE provider_key=? AND provider_event_id=?")->execute([$provider,$eventId]);
    // Standard settlement remains delegated to mg_tip_process_payment_event_result() by the recovery-aware router.
    $result=mg_tip_route_payment_event($pdo,$provider,$event);
    $webhookStatus=!empty($result['ignored'])?'ignored':'processed';
    $pdo->prepare('UPDATE payment_webhook_events SET status=?,processed_at=NOW(),failure_message=NULL WHERE provider_key=? AND provider_event_id=?')->execute([$webhookStatus,$provider,$eventId]);
    $pdo->commit();
    mg_ok(['received'=>true,'result'=>$result]);
}catch(InvalidArgumentException|RuntimeException $e){
    if($pdo->inTransaction())$pdo->rollBack();
    $pdo->prepare("UPDATE payment_webhook_events SET status='failed',failure_message=? WHERE provider_key=? AND provider_event_id=?")->execute([mb_substr($e->getMessage(),0,500),$provider,$eventId]);
    mg_fail($e->getMessage(),409);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    $pdo->prepare("UPDATE payment_webhook_events SET status='failed',failure_message=? WHERE provider_key=? AND provider_event_id=?")->execute([mb_substr($e->getMessage(),0,500),$provider,$eventId]);
    mg_fail('Unable to process tip payment webhook.',500);
}
