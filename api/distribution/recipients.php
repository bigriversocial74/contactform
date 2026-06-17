<?php
declare(strict_types=1);
require_once __DIR__ . '/_distribution.php';

$method=strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user=mg_require_permission($method==='GET'?'distribution.analytics.view':'distribution.allocations.manage');
$pdo=mg_db();
if($method==='GET'){
 $programId=trim((string)($_GET['program_id']??''));
 $stmt=$pdo->prepare('SELECT dr.public_id,dr.user_id,dr.external_recipient_id,dr.display_name,dr.eligibility_status,dr.eligibility_reason,dr.entries_count,dr.metadata_json,dr.created_at,dr.updated_at FROM distribution_recipients dr INNER JOIN distribution_programs dp ON dp.id=dr.program_id WHERE dp.public_id=? AND dp.merchant_user_id=? ORDER BY dr.id DESC');
 $stmt->execute([$programId,(int)$user['id']]); mg_ok(['recipients'=>$stmt->fetchAll()]);
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input(); mg_require_csrf_for_write($input);
$programId=trim((string)($input['program_id']??''));
$program=mg_distribution_program_for_update($pdo,(int)$user['id'],$programId);
$recipients=is_array($input['recipients']??null)?$input['recipients']:[];
if(!$recipients||count($recipients)>5000)mg_fail('Provide between 1 and 5000 recipients.',422);
$inserted=[];
$pdo->beginTransaction();
try{
 $stmt=$pdo->prepare("INSERT INTO distribution_recipients (public_id,program_id,user_id,external_recipient_id,email_hash,phone_hash,display_name,eligibility_status,entries_count,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),entries_count=GREATEST(entries_count,VALUES(entries_count)),metadata_json=VALUES(metadata_json),updated_at=NOW()");
 foreach($recipients as $recipient){
  if(!is_array($recipient))continue;
  $userId=isset($recipient['user_id'])&&$recipient['user_id']!==''?(int)$recipient['user_id']:null;
  $external=trim((string)($recipient['external_recipient_id']??''))?:null;
  $email=strtolower(trim((string)($recipient['email']??'')));
  $phone=preg_replace('/\D+/','',(string)($recipient['phone']??''));
  if(!$userId&&!$external&&$email===''&&$phone==='')continue;
  if(!$external)$external=$email!==''?'email:'.mg_distribution_hash($email):($phone!==''?'phone:'.mg_distribution_hash($phone):null);
  $id=mg_distribution_uuid();
  $stmt->execute([$id,(int)$program['id'],$userId,$external,$email!==''?mg_distribution_hash($email):null,$phone!==''?mg_distribution_hash($phone):null,trim((string)($recipient['display_name']??''))?:null,'eligible',max(1,(int)($recipient['entries_count']??1)),mg_distribution_json($recipient['metadata']??null,65536)]);
  $inserted[]=$id;
 }
 $pdo->commit(); mg_ok(['accepted'=>count($inserted),'recipient_ids'=>$inserted],'Recipients accepted.',201);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to save recipients.',500);}
