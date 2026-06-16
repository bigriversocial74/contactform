<?php
declare(strict_types=1);
require_once __DIR__ . '/_distribution.php';

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=mg_require_permission('distribution.allocations.manage');
$pdo=mg_db();
if($method==='GET'){
 $limit=max(1,min(100,(int)($_GET['limit']??25)));
 $stmt=$pdo->prepare("SELECT dij.public_id,dij.item_sequence,dij.status,dij.attempts,dij.max_attempts,dij.next_attempt_at,dij.request_json,da.public_id AS allocation_id,dp.public_id AS program_id FROM distribution_issuance_jobs dij INNER JOIN distribution_allocations da ON da.id=dij.allocation_id INNER JOIN distribution_programs dp ON dp.id=da.program_id WHERE dp.merchant_user_id=? AND dij.status IN ('queued','failed') AND (dij.next_attempt_at IS NULL OR dij.next_attempt_at<=NOW()) ORDER BY dij.created_at ASC LIMIT ".$limit);
 $stmt->execute([(int)$user['id']]);mg_ok(['jobs'=>$stmt->fetchAll()]);
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);
$action=trim((string)($input['action']??'claim'));$jobId=trim((string)($input['job_id']??''));
$pdo->beginTransaction();
try{
 $stmt=$pdo->prepare("SELECT dij.*,da.program_id,da.quantity,da.unit_value_cents,dp.merchant_user_id FROM distribution_issuance_jobs dij INNER JOIN distribution_allocations da ON da.id=dij.allocation_id INNER JOIN distribution_programs dp ON dp.id=da.program_id WHERE dij.public_id=? AND dp.merchant_user_id=? LIMIT 1 FOR UPDATE");$stmt->execute([$jobId,(int)$user['id']]);$job=$stmt->fetch();if(!$job)mg_fail('Issuance job not found.',404);
 if($action==='claim'){
  if(!in_array((string)$job['status'],['queued','failed'],true)||(!empty($job['next_attempt_at'])&&strtotime((string)$job['next_attempt_at'])>time()))mg_fail('Issuance job is not available.',409);
  if((int)$job['attempts']>=(int)$job['max_attempts'])mg_fail('Issuance job has exhausted retries.',409);
  $worker=trim((string)($input['worker_id']??'manual-worker'));$pdo->prepare("UPDATE distribution_issuance_jobs SET status='processing',attempts=attempts+1,locked_at=NOW(),locked_by=?,updated_at=NOW() WHERE id=?")->execute([$worker,(int)$job['id']]);$pdo->commit();mg_ok(['job_id'=>$jobId,'request'=>json_decode((string)$job['request_json'],true)?:[],'attempt'=>(int)$job['attempts']+1],'Issuance job claimed.');
 }
 if($action==='complete'){
  $pppmId=trim((string)($input['pppm_id']??''));if($pppmId==='')mg_fail('PPPM item ID is required.',422);
  $itemStmt=$pdo->prepare('SELECT id FROM pppm_items WHERE public_id=? LIMIT 1');$itemStmt->execute([$pppmId]);$pppmDbId=$itemStmt->fetchColumn();if(!$pppmDbId)mg_fail('PPPM item not found.',404);
  if((string)$job['status']!=='processing')mg_fail('Issuance job is not processing.',409);
  $pdo->prepare("UPDATE distribution_issuance_jobs SET status='issued',pppm_item_id=?,locked_at=NULL,locked_by=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$pppmDbId,(int)$job['id']]);
  $remaining=$pdo->prepare("SELECT COUNT(*) FROM distribution_issuance_jobs WHERE allocation_id=? AND status<>'issued'");$remaining->execute([(int)$job['allocation_id']]);
  if((int)$remaining->fetchColumn()===0){$pdo->prepare("UPDATE distribution_allocations SET status='issued',issued_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$job['allocation_id']]);$pdo->prepare('UPDATE distribution_programs SET reserved_cents=GREATEST(0,reserved_cents-?),issued_cents=issued_cents+?,issued_items=issued_items+1,updated_at=NOW() WHERE id=?')->execute([(int)$job['unit_value_cents'],(int)$job['unit_value_cents'],(int)$job['program_id']]);}
  $pdo->commit();mg_ok(['job_id'=>$jobId,'pppm_id'=>$pppmId,'status'=>'issued'],'Issuance job completed.');
 }
 if($action==='fail'){
  if((string)$job['status']!=='processing')mg_fail('Issuance job is not processing.',409);$message=substr(trim((string)($input['failure_message']??'Issuance failed.')),0,500);$attempts=(int)$job['attempts'];$dead=$attempts>=(int)$job['max_attempts'];$next=$dead?null:date('Y-m-d H:i:s',time()+min(3600,30*(2**max(0,$attempts-1))));$pdo->prepare("UPDATE distribution_issuance_jobs SET status=?,next_attempt_at=?,failure_message=?,locked_at=NULL,locked_by=NULL,updated_at=NOW() WHERE id=?")->execute([$dead?'dead_letter':'failed',$next,$message,(int)$job['id']]);$pdo->commit();mg_ok(['job_id'=>$jobId,'status'=>$dead?'dead_letter':'failed','next_attempt_at'=>$next],'Issuance failure recorded.');
 }
 mg_fail('Invalid issuance job action.',422);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to process issuance job.',500);}
