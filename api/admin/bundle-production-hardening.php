<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__,2).'/includes/admin-permission-matrix.php';
require_once dirname(__DIR__).'/bundles/_provider_reversal.php';
$pdo=mg_db();$user=mg_authenticated_user();
if(!$user||(int)($user['id']??0)<1)mg_fail('Sign in to continue.',401);
if(!mg_admin_permission_user_has($user,'commerce.manage')&&!mg_admin_permission_user_has($user,'admin'))mg_fail('Admin commerce access is required.',403);
$actor=(int)$user['id'];$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));$input=$method==='POST'?mg_input():[];$action=strtolower(trim((string)($input['action']??$_GET['action']??'dashboard')));
try{
 if($method==='GET'&&$action==='dashboard'){
  $summary=[
   'pending_reversals'=>(int)$pdo->query("SELECT COUNT(*) FROM gift_bundle_settlement_adjustments WHERE adjustment_status='dispatch_pending'")->fetchColumn(),
   'failed_reversals'=>(int)$pdo->query("SELECT COUNT(*) FROM gift_bundle_settlement_adjustments WHERE adjustment_status='failed'")->fetchColumn(),
   'open_dead_letters'=>(int)$pdo->query("SELECT COUNT(*) FROM gift_bundle_provider_dead_letters WHERE status IN ('open','retrying')")->fetchColumn(),
   'open_incidents'=>(int)$pdo->query("SELECT COUNT(*) FROM gift_bundle_settlement_incidents WHERE status IN ('open','investigating')")->fetchColumn(),
   'dispatch_enabled'=>mg_bundle_reversal_dispatch_enabled(),'live_enabled'=>mg_bundle_reversal_live_enabled(),'payment_mode'=>mg_payment_mode()
  ];
  $items=$pdo->query("SELECT public_id,source_type,source_public_id,failure_code,failure_message,status,retry_count,next_retry_at,created_at FROM gift_bundle_provider_dead_letters ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
  $incidents=$pdo->query("SELECT public_id,incident_type,severity,status,summary,created_at,resolved_at FROM gift_bundle_settlement_incidents ORDER BY created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
  mg_ok(['summary'=>$summary,'dead_letters'=>$items,'incidents'=>$incidents]);
 }
 if($method==='POST'){
  mg_require_csrf_for_write($input);
  if(trim((string)($input['confirmation']??''))!=='RECOVER')mg_fail('Type RECOVER to confirm.',422);
  if($action==='retry_dead_letter'){
   $pdo->beginTransaction();
   $stmt=$pdo->prepare("SELECT * FROM gift_bundle_provider_dead_letters WHERE public_id=? LIMIT 1 FOR UPDATE");$stmt->execute([trim((string)($input['id']??''))]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new InvalidArgumentException('Dead letter not found.');
   $pdo->prepare("UPDATE gift_bundle_provider_dead_letters SET status='retrying',retry_count=retry_count+1,next_retry_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
   if((string)$row['source_type']==='reversal'&&!empty($row['source_public_id']))$pdo->prepare("UPDATE gift_bundle_settlement_adjustments SET adjustment_status='dispatch_pending',next_dispatch_at=NOW(),failure_code=NULL,failure_message=NULL,updated_at=NOW() WHERE public_id=?")->execute([(string)$row['source_public_id']]);
   $pdo->commit();mg_ok(['retried'=>true]);
  }
  if($action==='resolve_incident'){
   $note=trim((string)($input['note']??''));if($note==='')mg_fail('Resolution note is required.',422);
   $stmt=$pdo->prepare("UPDATE gift_bundle_settlement_incidents SET status='resolved',resolved_by_user_id=?,resolution_note=?,resolved_at=NOW(),updated_at=NOW() WHERE public_id=? AND status<>'resolved'");$stmt->execute([$actor,$note,trim((string)($input['id']??''))]);
   mg_ok(['resolved'=>$stmt->rowCount()===1]);
  }
 }
 mg_fail('Unsupported operation.',405);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail_unexpected($e,'bundle.production.hardening.failure','Unable to process production recovery.',500,[],$actor);}
