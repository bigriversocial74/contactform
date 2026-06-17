<?php
declare(strict_types=1);
require_once __DIR__ . '/_payments.php';
require_once dirname(__DIR__) . '/entitlements/_lifecycle.php';
mg_require_method('POST');
$provider=trim((string)($_GET['provider']??''));
$payload=file_get_contents('php://input')?:'';
$signature=(string)($_SERVER['HTTP_X_MG_SIGNATURE']??'');
$event=json_decode($payload,true);
if($provider===''||!is_array($event)||!mg_payment_verify_signature($provider,$payload,$signature))mg_fail('Invalid dispute webhook.',401);
$eventId=trim((string)($event['id']??''));
$type=trim((string)($event['type']??''));
$data=is_array($event['data']??null)?$event['data']:[];
$orderPublicId=trim((string)($data['order_id']??$data['metadata']['order_id']??''));
$map=['dispute.opened'=>'opened','charge.dispute.created'=>'opened','dispute.merchant_won'=>'merchant_won','charge.dispute.won'=>'merchant_won','dispute.customer_won'=>'customer_won','charge.dispute.lost'=>'customer_won'];
if($eventId===''||$orderPublicId===''||!isset($map[$type]))mg_fail('Unsupported dispute event.',422);
$pdo=mg_db();
try{$pdo->prepare("INSERT INTO payment_webhook_events (public_id,provider_key,provider_event_id,event_type,signature_valid,status,payload_hash,payload_json,received_at) VALUES (?,?,?,?,1,'received',?,?,NOW())")->execute([mg_public_uuid(),$provider,$eventId,$type,hash('sha256',$payload),$payload]);}catch(Throwable $e){if(str_contains($e->getMessage(),'Duplicate'))mg_ok(['duplicate'=>true]);throw $e;}
$pdo->beginTransaction();
try{
 $order=$pdo->prepare('SELECT id FROM commerce_orders WHERE public_id=? LIMIT 1 FOR UPDATE');$order->execute([$orderPublicId]);$orderId=(int)$order->fetchColumn();if($orderId<1)throw new RuntimeException('Order not found.');
 $result=mg_entitlements_apply_dispute($pdo,$orderId,$map[$type],$provider.':'.$eventId,null);
 $pdo->prepare("UPDATE payment_webhook_events SET status='processed',processed_at=NOW() WHERE provider_key=? AND provider_event_id=?")->execute([$provider,$eventId]);
 $pdo->commit();mg_ok(['processed'=>true,'result'=>$result]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$pdo->prepare("UPDATE payment_webhook_events SET status='failed',failure_message=? WHERE provider_key=? AND provider_event_id=?")->execute([mb_substr($e->getMessage(),0,500),$provider,$eventId]);mg_fail('Unable to apply dispute policy.',500);}
