<?php
declare(strict_types=1);
require_once __DIR__ . '/_claims.php';
mg_require_method('POST');$user=mg_require_permission('merchant.claims.exceptions.manage');$input=mg_input();mg_require_csrf_for_write($input);$action=trim((string)($input['action']??'open'));$pdo=mg_db();
if($action==='open'){
 $identifier=trim((string)($input['claim_identifier']??''));$type=trim((string)($input['exception_type']??'other'));$priority=trim((string)($input['priority']??'normal'));$summary=trim((string)($input['summary']??''));
 if($identifier===''||$summary===''||mb_strlen($summary)>240||!in_array($type,['locked','expired','eligibility','code_failure','duplicate','dispute','cancellation','data_issue','other'],true)||!in_array($priority,['low','normal','high','urgent'],true))mg_fail('Invalid claim exception.',422);
 $claim=mg_claim_lookup($pdo,(int)$user['id'],$identifier);if(!$claim['claim_db_id'])mg_fail('A claim record must exist before opening an exception.',409);$public=mg_merchant_uuid();
 $pdo->prepare("INSERT INTO merchant_claim_exceptions (public_id,merchant_user_id,claim_id,pppm_item_id,location_id,exception_type,status,priority,summary,opened_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,'open',?,?,?,NOW(),NOW())")->execute([$public,(int)$user['id'],(int)$claim['claim_db_id'],$claim['pppm_item_id']?(int)$claim['pppm_item_id']:null,$claim['location_id']?(int)$claim['location_id']:null,$type,$priority,$summary,(int)$user['id']]);
 mg_audit('merchant.claim_exception_opened','gift_claim',['exception_id'=>$public,'gift_id'=>$claim['gift_id'],'type'=>$type],(int)$user['id']);mg_ok(['exception_id'=>$public],'Claim exception opened.',201);
}
if(in_array($action,['investigate','wait','resolve','close'],true)){
 $id=trim((string)($input['exception_id']??''));$notes=trim((string)($input['resolution_notes']??''))?:null;$map=['investigate'=>'investigating','wait'=>'waiting','resolve'=>'resolved','close'=>'closed'];$status=$map[$action];
 $stmt=$pdo->prepare("UPDATE merchant_claim_exceptions SET status=?,resolution_notes=COALESCE(?,resolution_notes),resolved_by_user_id=CASE WHEN ?='resolved' THEN ? ELSE resolved_by_user_id END,resolved_at=CASE WHEN ?='resolved' THEN NOW() ELSE resolved_at END,closed_at=CASE WHEN ?='closed' THEN NOW() ELSE closed_at END,updated_at=NOW() WHERE public_id=? AND merchant_user_id=?");$stmt->execute([$status,$notes,$status,(int)$user['id'],$status,$status,$id,(int)$user['id']]);if($stmt->rowCount()<1)mg_fail('Claim exception not found.',404);
 mg_audit('merchant.claim_exception_updated','merchant_claim_exception',['exception_id'=>$id,'status'=>$status],(int)$user['id']);mg_ok(['exception_id'=>$id,'status'=>$status],'Claim exception updated.');
}
mg_fail('Invalid exception action.',422);
