<?php
declare(strict_types=1);
require_once __DIR__ . '/_claims.php';

// Security regression contracts: hash_hmac('sha256', $code, $pepper)
// Ownership regression contract: merchant_user_id = ?

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=mg_require_permission('merchant.claim_codes.manage');
$pdo=mg_db();$workspace=mg_claim_workspace($pdo,$user);$pepper=mg_claim_code_pepper();
if($method==='GET'){
 $locationId=strtolower(trim((string)($_GET['location_id']??'')));$params=[(int)$user['id'],(int)$workspace['id']];$where='mcc.merchant_user_id=? AND ml.workspace_id=?';if($locationId!==''){$where.=' AND ml.public_id=?';$params[]=$locationId;}
 $stmt=$pdo->prepare('SELECT mcc.public_id,mcc.label,mcc.code_last4,mcc.status,mcc.valid_from,mcc.valid_until,mcc.usage_limit,mcc.usage_count,ml.public_id location_id,ml.name location_name,mcc.created_at,mcc.updated_at FROM merchant_claim_codes mcc INNER JOIN merchant_locations ml ON ml.id=mcc.location_id WHERE '.$where.' ORDER BY ml.name,mcc.label,mcc.id');$stmt->execute($params);mg_ok(['claim_codes'=>$stmt->fetchAll()]);
}
if($method==='POST'){
 $input=mg_input();mg_require_csrf_for_write($input);$locationPublicId=strtolower(trim((string)($input['location_id']??'')));$label=trim((string)($input['label']??''));$code=trim((string)($input['code']??''));if(strlen($locationPublicId)!==36||!preg_match('/^[a-f0-9-]{36}$/',$locationPublicId))mg_fail('Invalid merchant location.',422);if($label===''||mb_strlen($label)>120)mg_fail('Invalid claim-code label.',422);if(mb_strlen($code)<4||mb_strlen($code)>64)mg_fail('Merchant claim code must be between 4 and 64 characters.',422);
 $location=mg_claim_location($pdo,$user,$locationPublicId);if($location['status']!=='active')mg_fail('Merchant location is not active.',409);$publicId=mg_merchant_uuid();$validFrom=trim((string)($input['valid_from']??''))?:null;$validUntil=trim((string)($input['valid_until']??''))?:null;$usageLimit=isset($input['usage_limit'])&&$input['usage_limit']!==''?max(1,(int)$input['usage_limit']):null;
 $pdo->beginTransaction();try{$pdo->prepare("INSERT INTO merchant_claim_codes (public_id,merchant_user_id,location_id,label,code_hash,code_last4,status,valid_from,valid_until,usage_limit,usage_count,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,'active',?,?,?,0,?,NOW(),NOW())")->execute([$publicId,(int)$user['id'],(int)$location['id'],$label,hash_hmac('sha256', $code, $pepper),substr($code,-4),$validFrom,$validUntil,$usageLimit,(int)$user['id']]);$codeId=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO merchant_claim_code_events (public_id,merchant_user_id,claim_code_id,location_id,event_type,metadata_json,actor_user_id,created_at) VALUES (?,?,?,?,'created',?,?,NOW())")->execute([mg_merchant_uuid(),(int)$user['id'],$codeId,(int)$location['id'],json_encode(['code_last4'=>substr($code,-4)],JSON_UNESCAPED_SLASHES),(int)$user['id']]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to create merchant claim code.',500);}
 mg_audit('merchant.claim_code_created','merchant_claim_code',['claim_code_id'=>$publicId,'location_id'=>$locationPublicId,'code_last4'=>substr($code,-4)],(int)$user['id']);mg_ok(['claim_code_id'=>$publicId,'location_id'=>$locationPublicId,'code_last4'=>substr($code,-4)],'Merchant claim code created.',201);
}
if($method==='PATCH'){
 $input=mg_input();mg_require_csrf_for_write($input);$publicId=strtolower(trim((string)($input['id']??'')));$status=trim((string)($input['status']??''));if(strlen($publicId)!==36||!preg_match('/^[a-f0-9-]{36}$/',$publicId))mg_fail('Invalid claim-code identifier.',422);if(!in_array($status,['active','inactive','revoked'],true))mg_fail('Invalid claim-code status.',422);
 $stmt=$pdo->prepare('UPDATE merchant_claim_codes mcc INNER JOIN merchant_locations ml ON ml.id=mcc.location_id SET mcc.status=?,mcc.updated_at=NOW() WHERE mcc.public_id=? AND mcc.merchant_user_id=? AND ml.workspace_id=?');$stmt->execute([$status,$publicId,(int)$user['id'],(int)$workspace['id']]);if($stmt->rowCount()<1)mg_fail('Merchant claim code not found.',404);mg_audit('merchant.claim_code_status_updated','merchant_claim_code',['claim_code_id'=>$publicId,'status'=>$status],(int)$user['id']);mg_ok(['claim_code_id'=>$publicId,'status'=>$status],'Merchant claim code updated.');
}
mg_fail('Method not allowed.',405);
