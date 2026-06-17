<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
mg_require_method('POST');
$user=mg_require_permission('merchant.distribution.eligibility.manage');
$input=mg_input();
mg_require_csrf_for_write($input);
$programId=trim((string)($input['program_id']??''));
$recipientIds=is_array($input['recipient_ids']??null)?array_values(array_unique(array_filter(array_map('strval',$input['recipient_ids'])))):[];
$status=trim((string)($input['status']??''));
$reason=trim((string)($input['reason']??''))?:null;
$allowed=['pending','eligible','ineligible','selected','allocated','fulfilled','disqualified'];
if($programId===''||!$recipientIds||count($recipientIds)>500||!in_array($status,$allowed,true))mg_fail('Invalid eligibility update.',422);
$pdo=mg_db();
$pdo->beginTransaction();
try{
 $programStmt=$pdo->prepare('SELECT id,rules_json FROM distribution_programs WHERE public_id=? AND merchant_user_id=? LIMIT 1 FOR UPDATE');
 $programStmt->execute([$programId,(int)$user['id']]);
 $program=$programStmt->fetch();
 if(!$program)mg_fail('Distribution program not found.',404);
 $placeholders=implode(',',array_fill(0,count($recipientIds),'?'));
 $params=array_merge([(int)$program['id']],$recipientIds);
 $recipientStmt=$pdo->prepare("SELECT id,public_id,eligibility_status FROM distribution_recipients WHERE program_id=? AND public_id IN ({$placeholders}) FOR UPDATE");
 $recipientStmt->execute($params);
 $recipients=$recipientStmt->fetchAll();
 if(count($recipients)!==count($recipientIds))mg_fail('One or more recipients were not found.',404);
 $update=$pdo->prepare('UPDATE distribution_recipients SET eligibility_status=?,eligibility_reason=?,updated_at=NOW() WHERE id=?');
 $decision=$pdo->prepare('INSERT INTO distribution_eligibility_decisions (public_id,merchant_user_id,program_id,recipient_id,from_status,to_status,reason,rule_snapshot_json,decided_by_user_id,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
 foreach($recipients as $recipient){
  $update->execute([$status,$reason,(int)$recipient['id']]);
  $decision->execute([mg_merchant_uuid(),(int)$user['id'],(int)$program['id'],(int)$recipient['id'],$recipient['eligibility_status'],$status,$reason,$program['rules_json'],(int)$user['id']]);
 }
 $pdo->commit();
 mg_audit('distribution.eligibility_updated','distribution_program',['program_id'=>$programId,'recipient_count'=>count($recipients),'status'=>$status],(int)$user['id']);
 mg_ok(['updated'=>count($recipients),'status'=>$status],'Eligibility updated.');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','distribution.eligibility_failed','Eligibility update failed.',['exception_type'=>get_class($e)],(int)$user['id']);mg_fail('Unable to update eligibility.',500);}
