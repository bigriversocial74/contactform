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
    }catch(Throwable){
        $hasClaimCode=false;
    }
    return $hasClaimCode;
}

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=$method==='GET'?mg_require_api_user():mg_require_permission('merchant.locations.manage');
$merchantId=(int)$user['id'];
$pdo=mg_db();
$workspace=mg_merchant_ensure_workspace($pdo,$user);
$workspaceId=(int)$workspace['id'];
$hasClaimCode=mg_merchant_locations_has_claim_code($pdo);

if($method==='GET'){
    try{
        $claimProjection=$hasClaimCode?'claim_code':'location_code AS claim_code';
        $stmt=$pdo->prepare(
            'SELECT public_id,name,location_code,'.$claimProjection.',address_line1,address_line2,city,region,
                    postal_code,country_code,timezone,phone,status,is_primary,created_at,updated_at
             FROM merchant_locations
             WHERE workspace_id=? AND merchant_user_id=?
             ORDER BY is_primary DESC,name,id'
        );
        $stmt->execute([$workspaceId,$merchantId]);
        mg_ok(['locations'=>$stmt->fetchAll(),'schema_ready'=>true]);
    }catch(Throwable $error){
        mg_security_log('warning','merchant.locations.schema_unavailable','Merchant location schema is unavailable.',[
            'exception_class'=>$error::class,
        ],$merchantId);
        mg_ok(['locations'=>[],'schema_ready'=>false],'Merchant locations unavailable until the locations schema is installed.');
    }
}

if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();
mg_require_csrf_for_write($input);

$locationId=trim((string)($input['location_id']??''));
$name=trim((string)($input['name']??''));
$claimCode=strtoupper(trim((string)($input['claim_code']??'')));
$code=strtolower(trim((string)($input['location_code']??'')));
if($code==='')$code=mg_merchant_location_slug($claimCode!==''?$claimCode:$name);
$timezone=trim((string)($input['timezone']??$workspace['timezone']));
$status=trim((string)($input['status']??'active'));
$primary=!empty($input['is_primary'])?1:0;
$countryCode=strtoupper(trim((string)($input['country_code']??'US')));

if(
    $name===''||mb_strlen($name)>180
    ||!preg_match('/^[a-z0-9_-]{2,80}$/',$code)
    ||$claimCode===''||mb_strlen($claimCode)>80||!preg_match('/^[A-Z0-9_-]{3,80}$/',$claimCode)
    ||!in_array($status,['active','inactive','archived'],true)
    ||!in_array($timezone,timezone_identifiers_list(),true)
    ||!preg_match('/^[A-Z]{2}$/',$countryCode)
){
    mg_fail('Invalid location.',422);
}

try{
    $duplicateSql=$hasClaimCode
        ? 'SELECT COUNT(*) FROM merchant_locations WHERE workspace_id=? AND merchant_user_id=? AND claim_code=? AND public_id<>?'
        : 'SELECT COUNT(*) FROM merchant_locations WHERE workspace_id=? AND merchant_user_id=? AND location_code=? AND public_id<>?';
    $duplicate=$pdo->prepare($duplicateSql);
    $duplicate->execute([$workspaceId,$merchantId,$hasClaimCode?$claimCode:$code,$locationId]);
    if((int)$duplicate->fetchColumn()>0)mg_fail('Location claim code already exists.',409);
}catch(Throwable $error){
    mg_security_log('error','merchant.locations.storage_unavailable','Merchant location storage is unavailable.',[
        'exception_class'=>$error::class,
    ],$merchantId);
    mg_fail('Merchant location storage is not available.',500);
}

$address1=trim((string)($input['address_line1']??''))?:null;
$address2=trim((string)($input['address_line2']??''))?:null;
$city=trim((string)($input['city']??''))?:null;
$region=trim((string)($input['region']??''))?:null;
$postalCode=trim((string)($input['postal_code']??''))?:null;
$phone=trim((string)($input['phone']??''))?:null;

$pdo->beginTransaction();
try{
    if($primary){
        $pdo->prepare('UPDATE merchant_locations SET is_primary=0,updated_at=NOW() WHERE workspace_id=? AND merchant_user_id=?')
            ->execute([$workspaceId,$merchantId]);
    }

    if($locationId===''){
        $locationId=mg_merchant_uuid();
        $pdo->prepare(
            'INSERT INTO merchant_locations
             (public_id,workspace_id,merchant_user_id,name,location_code,claim_code,address_line1,address_line2,
              city,region,postal_code,country_code,timezone,phone,status,is_primary,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
        )->execute([
            $locationId,$workspaceId,$merchantId,$name,$code,$claimCode,$address1,$address2,
            $city,$region,$postalCode,$countryCode,$timezone,$phone,$status,$primary,
        ]);
    }else{
        $stmt=$pdo->prepare(
            'UPDATE merchant_locations
             SET name=?,location_code=?,claim_code=?,address_line1=?,address_line2=?,city=?,region=?,postal_code=?,
                 country_code=?,timezone=?,phone=?,status=?,is_primary=?,updated_at=NOW()
             WHERE public_id=? AND workspace_id=? AND merchant_user_id=?'
        );
        $stmt->execute([
            $name,$code,$claimCode,$address1,$address2,$city,$region,$postalCode,
            $countryCode,$timezone,$phone,$status,$primary,$locationId,$workspaceId,$merchantId,
        ]);
        if($stmt->rowCount()===0)mg_fail('Location not found.',404);
    }

    $pdo->prepare(
        "UPDATE merchant_onboarding_steps SET status='completed',completed_at=NOW(),completed_by_user_id=?,updated_at=NOW()
         WHERE workspace_id=? AND step_key='first_location'"
    )->execute([$merchantId,$workspaceId]);
    $pdo->prepare(
        "UPDATE merchant_onboarding_steps SET status='available',updated_at=NOW()
         WHERE workspace_id=? AND step_key='claim_configuration' AND status='locked'"
    )->execute([$workspaceId]);
    $percent=mg_merchant_recalculate_onboarding($pdo,$workspaceId);
    $pdo->commit();
    mg_ok([
        'location_id'=>$locationId,
        'claim_code'=>$claimCode,
        'schema_ready'=>true,
        'onboarding_percent'=>$percent,
    ],'Location saved.',201);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','merchant.locations.save_failed','Unable to save merchant location.',[
        'exception_class'=>$error::class,
    ],$merchantId);
    mg_fail('Unable to save location.',500);
}
