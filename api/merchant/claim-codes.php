<?php
declare(strict_types=1);
require_once __DIR__ . '/_claims.php';

function mg_claim_code_normalize_input(mixed $value): string
{
    $code = strtoupper(trim((string)$value));
    if (mb_strlen($code) < 4 || mb_strlen($code) > 64 || !preg_match('/^[A-Z0-9_-]{4,64}$/', $code)) {
        mg_fail('Merchant claim code must be 4 to 64 letters, numbers, underscores, or dashes.', 422);
    }
    return $code;
}

function mg_claim_code_assert_unique(PDO $pdo, int $merchantId, string $hash, ?int $ignoreId = null): void
{
    $sql = "SELECT public_id FROM merchant_claim_codes WHERE merchant_user_id=? AND code_hash=? AND status='active'";
    $params = [$merchantId, $hash];
    if ($ignoreId !== null) { $sql .= ' AND id<>?'; $params[] = $ignoreId; }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn()) mg_fail('An active claim code with this value already exists for this merchant.', 409);
}

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=mg_require_permission('merchant.claim_codes.manage');
$pdo=mg_db();$workspace=mg_claim_workspace($pdo,$user);$pepper=mg_claim_code_pepper();
$merchantId=(int)$user['id'];
$workspaceId=(int)$workspace['id'];

if($method==='GET'){
 $locationId=strtolower(trim((string)($_GET['location_id']??'')));$params=[$merchantId,$workspaceId];$where='mcc.merchant_user_id=? AND ml.workspace_id=?';if($locationId!==''){$where.=' AND ml.public_id=?';$params[]=$locationId;}
 $stmt=$pdo->prepare('SELECT mcc.public_id,mcc.label,mcc.code_last4,mcc.status,mcc.valid_from,mcc.valid_until,mcc.usage_limit,mcc.usage_count,ml.public_id location_id,ml.name location_name,mcc.created_at,mcc.updated_at,(mcc.status=\'active\' AND (mcc.valid_from IS NULL OR mcc.valid_from<=NOW()) AND (mcc.valid_until IS NULL OR mcc.valid_until>=NOW()) AND (mcc.usage_limit IS NULL OR mcc.usage_count<mcc.usage_limit)) is_currently_valid FROM merchant_claim_codes mcc INNER JOIN merchant_locations ml ON ml.id=mcc.location_id WHERE '.$where.' ORDER BY ml.name,mcc.label,mcc.id');$stmt->execute($params);mg_ok(['claim_codes'=>$stmt->fetchAll(),'schema_ready'=>true]);
}

if($method==='POST'){
 $input=mg_input();mg_require_csrf_for_write($input);$locationPublicId=strtolower(trim((string)($input['location_id']??'')));$label=trim((string)($input['label']??''));$code=mg_claim_code_normalize_input($input['code']??'');if(strlen($locationPublicId)!==36||!preg_match('/^[a-f0-9-]{36}$/',$locationPublicId))mg_fail('Invalid merchant location.',422);if($label===''||mb_strlen($label)>120)mg_fail('Invalid claim-code label.',422);
 $location=mg_claim_location($pdo,$user,$locationPublicId,true);if($location['status']!=='active')mg_fail('Merchant location is not active.',409);$publicId=mg_merchant_uuid();$validFrom=trim((string)($input['valid_from']??''))?:null;$validUntil=trim((string)($input['valid_until']??''))?:null;$usageLimit=isset($input['usage_limit'])&&$input['usage_limit']!==''?max(1,(int)$input['usage_limit']):null;$hash=hash_hmac('sha256',$code,$pepper);
 $pdo->beginTransaction();try{mg_claim_code_assert_unique($pdo,$merchantId,$hash);$pdo->prepare("INSERT INTO merchant_claim_codes (public_id,merchant_user_id,location_id,label,code_hash,code_last4,status,valid_from,valid_until,usage_limit,usage_count,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,'active',?,?,?,0,?,NOW(),NOW())")->execute([$publicId,$merchantId,(int)$location['id'],$label,$hash,substr($code,-4),$validFrom,$validUntil,$usageLimit,$merchantId]);$codeId=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO merchant_claim_code_events (public_id,merchant_user_id,claim_code_id,location_id,event_type,metadata_json,actor_user_id,created_at) VALUES (?,?,?,?,'created',?,?,NOW())")->execute([mg_merchant_uuid(),$merchantId,$codeId,(int)$location['id'],json_encode(['code_last4'=>substr($code,-4),'source'=>'claim-codes-api'],JSON_UNESCAPED_SLASHES),$merchantId]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof RuntimeException)throw $e;mg_security_log('error','merchant.claim_code_create_failed','Unable to create merchant claim code.',['exception_class'=>$e::class],$merchantId);mg_fail('Unable to create merchant claim code.',500);}
 mg_audit('merchant.claim_code_created','merchant_claim_code',['claim_code_id'=>$publicId,'location_id'=>$locationPublicId,'code_last4'=>substr($code,-4)],$merchantId);mg_ok(['claim_code_id'=>$publicId,'location_id'=>$locationPublicId,'code_last4'=>substr($code,-4),'schema_ready'=>true],'Merchant claim code created.',201);
}

if($method==='PATCH'){
 $input=mg_input();mg_require_csrf_for_write($input);$publicId=strtolower(trim((string)($input['id']??'')));$status=trim((string)($input['status']??''));if(strlen($publicId)!==36||!preg_match('/^[a-f0-9-]{36}$/',$publicId))mg_fail('Invalid claim-code identifier.',422);if(!in_array($status,['active','inactive','revoked'],true))mg_fail('Invalid claim-code status.',422);
 $stmt=$pdo->prepare('UPDATE merchant_claim_codes mcc INNER JOIN merchant_locations ml ON ml.id=mcc.location_id SET mcc.status=?,mcc.updated_at=NOW() WHERE mcc.public_id=? AND mcc.merchant_user_id=? AND ml.workspace_id=?');$stmt->execute([$status,$publicId,$merchantId,$workspaceId]);if($stmt->rowCount()<1)mg_fail('Merchant claim code not found.',404);mg_audit('merchant.claim_code_status_updated','merchant_claim_code',['claim_code_id'=>$publicId,'status'=>$status],$merchantId);mg_ok(['claim_code_id'=>$publicId,'status'=>$status],'Merchant claim code updated.');
}
mg_fail('Method not allowed.',405);
