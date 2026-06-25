<?php
declare(strict_types=1);
require_once __DIR__ . '/_claims.php';

function mg_claim_code_action_normalize_code(mixed $value): string
{
    $code = strtoupper(trim((string)$value));
    if (mb_strlen($code) < 4 || mb_strlen($code) > 64 || !preg_match('/^[A-Z0-9_-]{4,64}$/', $code)) {
        mg_fail('Replacement claim code must be 4 to 64 letters, numbers, underscores, or dashes.', 422);
    }
    return $code;
}

function mg_claim_code_action_assert_unique(PDO $pdo, int $merchantId, string $hash, ?int $ignoreId = null): void
{
    $sql = "SELECT public_id FROM merchant_claim_codes WHERE merchant_user_id=? AND code_hash=? AND status='active'";
    $params = [$merchantId, $hash];
    if ($ignoreId !== null) { $sql .= ' AND id<>?'; $params[] = $ignoreId; }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn()) mg_fail('An active claim code with this value already exists for this merchant.', 409);
}

mg_require_method('POST');
$user=mg_require_permission('merchant.claim_codes.manage');
$input=mg_input();mg_require_csrf_for_write($input);
$action=trim((string)($input['action']??''));
$id=trim((string)($input['claim_code_id']??''));
$pdo=mg_db();$workspace=mg_claim_workspace($pdo,$user);$merchantId=(int)$user['id'];$workspaceId=(int)$workspace['id'];

$stmt=$pdo->prepare('SELECT mcc.*,ml.public_id location_public_id FROM merchant_claim_codes mcc INNER JOIN merchant_locations ml ON ml.id=mcc.location_id WHERE mcc.public_id=? AND mcc.merchant_user_id=? AND ml.workspace_id=? LIMIT 1 FOR UPDATE');
$pdo->beginTransaction();
try{
 $stmt->execute([$id,$merchantId,$workspaceId]);$current=$stmt->fetch();if(!$current)mg_fail('Claim code not found.',404);
 if($action==='status'){$status=trim((string)($input['status']??''));if(!in_array($status,['active','inactive','revoked'],true))mg_fail('Invalid claim-code status.',422);$pdo->prepare('UPDATE merchant_claim_codes SET status=?,updated_at=NOW() WHERE id=?')->execute([$status,(int)$current['id']]);$event=$status==='active'?'activated':($status==='inactive'?'deactivated':'revoked');$newId=(int)$current['id'];$public=(string)$current['public_id'];$last4=(string)$current['code_last4'];}
 elseif($action==='limit'){$limit=($input['usage_limit']??'')===''?null:max(1,(int)$input['usage_limit']);$pdo->prepare('UPDATE merchant_claim_codes SET usage_limit=?,updated_at=NOW() WHERE id=?')->execute([$limit,(int)$current['id']]);$event='limit_changed';$newId=(int)$current['id'];$public=(string)$current['public_id'];$last4=(string)$current['code_last4'];}
 elseif($action==='rotate'){$code=mg_claim_code_action_normalize_code($input['code']??'');$label=trim((string)($input['label']??$current['label']));if($label===''||mb_strlen($label)>120)mg_fail('Invalid replacement claim-code label.',422);$pepper=mg_claim_code_pepper();$hash=hash_hmac('sha256',$code,$pepper);mg_claim_code_action_assert_unique($pdo,$merchantId,$hash,(int)$current['id']);$public=mg_merchant_uuid();$validUntil=trim((string)($input['valid_until']??''))?:null;$usageLimit=($input['usage_limit']??'')===''?null:max(1,(int)$input['usage_limit']);$pdo->prepare("INSERT INTO merchant_claim_codes (public_id,merchant_user_id,location_id,label,code_hash,code_last4,status,valid_from,valid_until,usage_limit,usage_count,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,'active',NOW(),?,?,0,?,NOW(),NOW())")->execute([$public,$merchantId,(int)$current['location_id'],$label,$hash,substr($code,-4),$validUntil,$usageLimit,$merchantId]);$newId=(int)$pdo->lastInsertId();$pdo->prepare("UPDATE merchant_claim_codes SET status='revoked',updated_at=NOW() WHERE id=?")->execute([(int)$current['id']]);$event='rotated';$last4=substr($code,-4);}
 else mg_fail('Invalid claim-code action.',422);
 $pdo->prepare('INSERT INTO merchant_claim_code_events (public_id,merchant_user_id,claim_code_id,location_id,event_type,previous_claim_code_id,metadata_json,actor_user_id,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')->execute([mg_merchant_uuid(),$merchantId,$newId,(int)$current['location_id'],$event,$event==='rotated'?(int)$current['id']:null,json_encode(['code_last4'=>$last4,'source'=>'claim-code-action'],JSON_UNESCAPED_SLASHES),$merchantId]);
 $pdo->commit();mg_audit('merchant.claim_code_'.$event,'merchant_claim_code',['claim_code_id'=>$public,'location_id'=>$current['location_public_id'],'code_last4'=>$last4],$merchantId);mg_ok(['claim_code_id'=>$public,'event'=>$event,'code_last4'=>$last4],'Claim code updated.');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_security_log('error','merchant.claim_code_action_failed','Claim-code action failed.',['exception_type'=>get_class($e),'message'=>$e->getMessage()],$merchantId);mg_fail('Unable to update claim code.',500);}
