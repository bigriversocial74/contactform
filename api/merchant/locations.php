<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';

function mg_merchant_location_slug(string $name): string
{
    $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$name)??''));
    $slug=trim($slug,'-');
    if($slug==='')$slug='location';
    return substr($slug,0,80);
}

function mg_merchant_locations_has_claim_code(PDO $pdo): bool
{
    static $hasClaimCode=null;
    if(is_bool($hasClaimCode))return $hasClaimCode;
    try{
        $pdo->query('SELECT claim_code FROM merchant_locations LIMIT 0');
        $hasClaimCode=true;
    }catch(Throwable $e){
        $hasClaimCode=false;
    }
    return $hasClaimCode;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = $method === 'GET' ? mg_require_api_user() : mg_require_permission('merchant.locations.manage');
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo,$user);
$hasClaimCode = mg_merchant_locations_has_claim_code($pdo);

if($method==='GET'){
    try{
        if($hasClaimCode){
            $stmt=$pdo->prepare('SELECT public_id,name,location_code,claim_code,address_line1,address_line2,city,region,postal_code,country_code,timezone,phone,status,is_primary,created_at,updated_at FROM merchant_locations WHERE workspace_id=? ORDER BY is_primary DESC,name');
        }else{
            $stmt=$pdo->prepare('SELECT public_id,name,location_code,location_code AS claim_code,address_line1,address_line2,city,region,postal_code,country_code,timezone,phone,status,is_primary,created_at,updated_at FROM merchant_locations WHERE workspace_id=? ORDER BY is_primary DESC,name');
        }
        $stmt->execute([(int)$workspace['id']]);
        mg_ok(['locations'=>$stmt->fetchAll(),'schema_ready'=>$hasClaimCode]);
    }catch(Throwable $e){
        mg_ok(['locations'=>[],'schema_ready'=>false],'Merchant locations unavailable until the locations schema is installed.');
    }
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);$locationId=trim((string)($input['location_id']??''));$name=trim((string)($input['name']??''));$claimCode=strtoupper(trim((string)($input['claim_code']??'')));$code=strtolower(trim((string)($input['location_code']??'')));if($code==='')$code=mg_merchant_location_slug($claimCode!==''?$claimCode:$name);$timezone=trim((string)($input['timezone']??$workspace['timezone']));$status=trim((string)($input['status']??'active'));$primary=!empty($input['is_primary'])?1:0;
if($name===''||mb_strlen($name)>180||!preg_match('/^[a-z0-9_-]{2,80}$/',$code)||$claimCode===''||mb_strlen($claimCode)>80||!preg_match('/^[A-Z0-9_-]{3,80}$/',$claimCode)||!in_array($status,['active','inactive','archived'],true)||!in_array($timezone,timezone_identifiers_list(),true))mg_fail('Invalid location.',422);
try{
    if($hasClaimCode){
        $duplicate=$pdo->prepare('SELECT COUNT(*) FROM merchant_locations WHERE workspace_id=? AND claim_code=? AND public_id<>?');
        $duplicate->execute([(int)$workspace['id'],$claimCode,$locationId]);
    }else{
        $duplicate=$pdo->prepare('SELECT COUNT(*) FROM merchant_locations WHERE workspace_id=? AND location_code=? AND public_id<>?');
        $duplicate->execute([(int)$workspace['id'],$code,$locationId]);
    }
    if((int)$duplicate->fetchColumn()>0)mg_fail('Location claim code already exists.',409);
}catch(Throwable $e){
    mg_fail('Merchant location storage is not available.',500);
}
$fields=[$name,$code,$claimCode,trim((string)($input['address_line1']??''))?:null,trim((string)($input['address_line2']??''))?:null,trim((string)($input['city']??''))?:null,trim((string)($input['region']??''))?:null,trim((string)($input['postal_code']??''))?:null,strtoupper(trim((string)($input['country_code']??'US'))),$timezone,trim((string)($input['phone']??''))?:null,$status,$primary];
$pdo->beginTransaction();try{if($primary)$pdo->prepare('UPDATE merchant_locations SET is_primary=0,updated_at=NOW() WHERE workspace_id=?')->execute([(int)$workspace['id']]);if($locationId===''){$locationId=mg_merchant_uuid();if($hasClaimCode){$pdo->prepare('INSERT INTO merchant_locations (public_id,workspace_id,name,location_code,claim_code,address_line1,address_line2,city,region,postal_code,country_code,timezone,phone,status,is_primary,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute(array_merge([$locationId,(int)$workspace['id']],$fields));}else{$legacyFields=[$name,$code,trim((string)($input['address_line1']??''))?:null,trim((string)($input['address_line2']??''))?:null,trim((string)($input['city']??''))?:null,trim((string)($input['region']??''))?:null,trim((string)($input['postal_code']??''))?:null,strtoupper(trim((string)($input['country_code']??'US'))),$timezone,trim((string)($input['phone']??''))?:null,$status,$primary];$pdo->prepare('INSERT INTO merchant_locations (public_id,workspace_id,name,location_code,address_line1,address_line2,city,region,postal_code,country_code,timezone,phone,status,is_primary,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute(array_merge([$locationId,(int)$workspace['id']],$legacyFields));}}else{if($hasClaimCode){$stmt=$pdo->prepare('UPDATE merchant_locations SET name=?,location_code=?,claim_code=?,address_line1=?,address_line2=?,city=?,region=?,postal_code=?,country_code=?,timezone=?,phone=?,status=?,is_primary=?,updated_at=NOW() WHERE public_id=? AND workspace_id=?');$stmt->execute(array_merge($fields,[$locationId,(int)$workspace['id']]));}else{$legacyFields=[$name,$code,trim((string)($input['address_line1']??''))?:null,trim((string)($input['address_line2']??''))?:null,trim((string)($input['city']??''))?:null,trim((string)($input['region']??''))?:null,trim((string)($input['postal_code']??''))?:null,strtoupper(trim((string)($input['country_code']??'US'))),$timezone,trim((string)($input['phone']??''))?:null,$status,$primary];$stmt=$pdo->prepare('UPDATE merchant_locations SET name=?,location_code=?,address_line1=?,address_line2=?,city=?,region=?,postal_code=?,country_code=?,timezone=?,phone=?,status=?,is_primary=?,updated_at=NOW() WHERE public_id=? AND workspace_id=?');$stmt->execute(array_merge($legacyFields,[$locationId,(int)$workspace['id']]));}if($stmt->rowCount()===0)mg_fail('Location not found.',404);}$pdo->prepare("UPDATE merchant_onboarding_steps SET status='completed',completed_at=NOW(),completed_by_user_id=?,updated_at=NOW() WHERE workspace_id=? AND step_key='first_location'")->execute([(int)$user['id'],(int)$workspace['id']]);$pdo->prepare("UPDATE merchant_onboarding_steps SET status='available',updated_at=NOW() WHERE workspace_id=? AND step_key='claim_configuration' AND status='locked'")->execute([(int)$workspace['id']]);$percent=mg_merchant_recalculate_onboarding($pdo,(int)$workspace['id']);$pdo->commit();mg_ok(['location_id'=>$locationId,'claim_code'=>$claimCode,'schema_ready'=>$hasClaimCode,'onboarding_percent'=>$percent],'Location saved.',201);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail('Unable to save location.',500);}
