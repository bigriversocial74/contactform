<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/microgifts/_lifecycle.php';
mg_require_method('POST');
$user=mg_require_permission('microgift.lifecycle.manage');
$input=mg_input();
mg_require_csrf_for_write($input);
$instanceId=trim((string)($input['instance_id']??''));
$key=trim((string)($input['idempotency_key']??''));
$source=trim((string)($input['source_reference']??''));
if($instanceId===''||$key===''||$source==='')mg_fail('Required replacement fields are missing.',422);
$pdo=mg_db();
try{
 $pdo->beginTransaction();
 $old=mg_microgift_load_instance($pdo,$instanceId);
 if(in_array((string)$old['status'],['redeemed','cancelled','revoked','expired','replaced'],true))throw new RuntimeException('Replacement not allowed.');
 $existing=$pdo->prepare('SELECT public_id FROM microgift_lifecycle_actions WHERE idempotency_key=? LIMIT 1');
 $existing->execute([$key]);
 if($actionId=$existing->fetchColumn()){$pdo->commit();mg_ok(['action_id'=>$actionId,'duplicate'=>true]);}
 $newPublic=mg_microgift_uuid();
 $pdo->prepare("INSERT INTO microgift_instances (public_id,template_id,template_version_id,status,source_type,source_reference,idempotency_key,issuer_user_id,owner_user_id,recipient_user_id,title_snapshot,description_snapshot,currency,face_value_cents,recipient_policy,claim_policy_json,redemption_policy_json,location_policy_json,expiration_policy_json,terms_snapshot_json,metadata_json,issued_at,expires_at,created_at,updated_at) VALUES (?,?,?,'issued','replacement',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW(),NOW())")
  ->execute([$newPublic,(int)$old['template_id'],(int)$old['template_version_id'],$source,'replacement:'.$key,(int)$user['id'],$old['owner_user_id'],$old['recipient_user_id'],$old['title_snapshot'],$old['description_snapshot'],$old['currency'],$old['face_value_cents'],$old['recipient_policy'],$old['claim_policy_json'],$old['redemption_policy_json'],$old['location_policy_json'],$old['expiration_policy_json'],$old['terms_snapshot_json'],$old['metadata_json'],$old['expires_at']]);
 $newId=(int)$pdo->lastInsertId();
 mg_microgift_create_credential($pdo,$newId,'claim',(int)$user['id'],$old['expires_at']);
 $actionPublic=mg_microgift_uuid();
 $pdo->prepare("UPDATE microgift_instances SET status='replaced',replaced_by_instance_id=?,updated_at=NOW() WHERE id=?")->execute([$newId,(int)$old['id']]);
 $pdo->prepare("UPDATE microgift_credentials SET status='revoked',updated_at=NOW() WHERE instance_id=? AND status IN ('active','verified','locked')")->execute([(int)$old['id']]);
 $pdo->prepare("INSERT INTO microgift_lifecycle_actions (public_id,instance_id,action_type,from_status,to_status,source_type,source_reference,idempotency_key,actor_user_id,replacement_instance_id,payload_json,created_at) VALUES (?,?,'replace',?,'replaced','admin',?,?,?,?, '{}',NOW())")
  ->execute([$actionPublic,(int)$old['id'],$old['status'],$source,$key,(int)$user['id'],$newId]);
 $pdo->commit();
 mg_ok(['action_id'=>$actionPublic,'replacement_instance_id'=>$newPublic,'duplicate'=>false],'Microgift replaced.',201);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to replace this Microgift.',409);}
