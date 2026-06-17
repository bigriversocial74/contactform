<?php
declare(strict_types=1);
require_once __DIR__ . '/_distribution.php';

mg_require_method('POST');
$user=mg_require_permission('distribution.allocations.manage');
$input=mg_input(); mg_require_csrf_for_write($input);
$programId=trim((string)($input['program_id']??''));
$recipientId=trim((string)($input['recipient_id']??''));
$templateId=trim((string)($input['template_id']??''));
$sourceEventId=trim((string)($input['source_event_id']??''))?:null;
$method=trim((string)($input['allocation_method']??'direct'));
$quantity=max(1,min(10000,(int)($input['quantity']??1)));
$allowed=['purchase_line','direct','random','weighted_random','ranked','batch','api'];
if(!in_array($method,$allowed,true))mg_fail('Invalid allocation method.',422);
$pdo=mg_db(); $pdo->beginTransaction();
try{
 $program=mg_distribution_program_for_update($pdo,(int)$user['id'],$programId);
 if(!mg_distribution_program_is_open($program))mg_fail('Distribution program is not active.',409);
 $recipientStmt=$pdo->prepare("SELECT * FROM distribution_recipients WHERE public_id=? AND program_id=? AND eligibility_status IN ('eligible','selected') LIMIT 1 FOR UPDATE");
 $recipientStmt->execute([$recipientId,(int)$program['id']]); $recipient=$recipientStmt->fetch(); if(!$recipient)mg_fail('Eligible recipient not found.',404);
 if($program['per_recipient_limit']!==null){$countStmt=$pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM distribution_allocations WHERE program_id=? AND recipient_id=? AND status NOT IN ('cancelled','expired','failed')");$countStmt->execute([(int)$program['id'],(int)$recipient['id']]);if((int)$countStmt->fetchColumn()+$quantity>(int)$program['per_recipient_limit'])mg_fail('Recipient allocation limit reached.',409);}
 $productStmt=$pdo->prepare("SELECT dpp.*,cpv.unit_value_cents,cpt.public_id AS template_public_id FROM distribution_program_products dpp INNER JOIN catalog_pppm_templates cpt ON cpt.id=dpp.pppm_template_id INNER JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id WHERE dpp.program_id=? AND cpt.public_id=? AND dpp.status='active' LIMIT 1 FOR UPDATE");
 $productStmt->execute([(int)$program['id'],$templateId]);$product=$productStmt->fetch();if(!$product)mg_fail('Program product not found.',404);
 if($product['quantity_limit']!==null&&(int)$product['quantity_issued']+$quantity>(int)$product['quantity_limit'])mg_fail('Program product inventory is insufficient.',409);
 mg_distribution_check_capacity($program,$quantity,(int)$product['unit_value_cents']);
 $eventDbId=null;if($sourceEventId){$eventStmt=$pdo->prepare('SELECT id FROM distribution_source_events WHERE public_id=? AND merchant_user_id=? LIMIT 1');$eventStmt->execute([$sourceEventId,(int)$user['id']]);$eventDbId=$eventStmt->fetchColumn();if(!$eventDbId)mg_fail('Source event not found.',404);}
 $idempotency=hash('sha256',$programId.'|'.$recipientId.'|'.$templateId.'|'.($sourceEventId?:'manual').'|'.$quantity);
 $existing=$pdo->prepare('SELECT public_id,status FROM distribution_allocations WHERE program_id=? AND idempotency_key=? LIMIT 1');$existing->execute([(int)$program['id'],$idempotency]);$duplicate=$existing->fetch();if($duplicate){$pdo->commit();mg_ok(['allocation_id'=>$duplicate['public_id'],'status'=>$duplicate['status'],'duplicate'=>true],'Allocation already exists.');}
 $allocationId=mg_distribution_uuid();
 $pdo->prepare("INSERT INTO distribution_allocations (public_id,program_id,source_event_id,recipient_id,program_product_id,quantity,unit_value_cents,status,allocation_method,selection_proof_json,idempotency_key,reserved_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'queued',?,?,?,NOW(),NOW(),NOW())")
  ->execute([$allocationId,(int)$program['id'],$eventDbId?:null,(int)$recipient['id'],(int)$product['id'],$quantity,(int)$product['unit_value_cents'],$method,mg_distribution_json($input['selection_proof']??null),$idempotency]);
 $allocationDbId=(int)$pdo->lastInsertId();
 $jobStmt=$pdo->prepare("INSERT INTO distribution_issuance_jobs (public_id,allocation_id,item_sequence,status,attempts,max_attempts,next_attempt_at,request_json,created_at,updated_at) VALUES (?,?,?,'queued',0,5,NOW(),?,NOW(),NOW())");
 for($i=1;$i<=$quantity;$i++){$jobStmt->execute([mg_distribution_uuid(),$allocationDbId,$i,json_encode(['program_id'=>$programId,'template_id'=>$templateId,'recipient_id'=>$recipientId,'source_event_id'=>$sourceEventId,'sequence'=>$i],JSON_UNESCAPED_SLASHES)]);}
 $cost=$quantity*(int)$product['unit_value_cents'];
 $pdo->prepare('UPDATE distribution_programs SET reserved_cents=reserved_cents+?,updated_at=NOW() WHERE id=?')->execute([$cost,(int)$program['id']]);
 $pdo->prepare("UPDATE distribution_recipients SET eligibility_status='allocated',updated_at=NOW() WHERE id=?")->execute([(int)$recipient['id']]);
 $pdo->commit();mg_audit('distribution.allocation_created','distribution_allocation',['allocation_id'=>$allocationId,'quantity'=>$quantity],(int)$user['id']);mg_ok(['allocation_id'=>$allocationId,'quantity'=>$quantity,'jobs_queued'=>$quantity,'duplicate'=>false],'Allocation queued.',201);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to allocate distribution items.',500);}
