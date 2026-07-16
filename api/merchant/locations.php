<?php
declare(strict_types=1);

require_once __DIR__ . '/_claims.php';

function mg_merchant_location_slug(string $name): string
{
    $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$name)??''));
    $slug=trim($slug,'-');
    if($slug==='')$slug='location';
    return substr($slug,0,80);
}

function mg_merchant_location_json(mixed $value): array
{
    if(is_array($value))return $value;
    if(!is_string($value)||trim($value)==='')return [];
    $decoded=json_decode($value,true);
    return is_array($decoded)?$decoded:[];
}

function mg_merchant_location_float_or_null(mixed $value): ?float
{
    if($value===null||$value==='')return null;
    if(!is_numeric($value))return null;
    return (float)$value;
}

function mg_merchant_unique_location_code(
    PDO $pdo,
    int $workspaceId,
    int $ownerMerchantId,
    string $name,
    string $excludePublicId=''
): string {
    $base=mg_merchant_location_slug($name);
    $candidate=$base;
    $suffix=1;
    $stmt=$pdo->prepare(
        'SELECT COUNT(*)
         FROM merchant_locations ml '
         .mg_merchant_location_scope_join('ml','location_scope_mw').'
         WHERE '.mg_merchant_location_scope_condition('ml','location_scope_mw').'
           AND ml.location_code=?
           AND ml.public_id<>?'
    );
    while(true){
        $stmt->execute([$workspaceId,$ownerMerchantId,$candidate,$excludePublicId]);
        if((int)$stmt->fetchColumn()===0)return $candidate;
        $suffix++;
        $candidate=substr($base,0,max(1,80-strlen((string)$suffix)-1)).'-'.$suffix;
    }
}

function mg_merchant_location_record_claim_code(
    PDO $pdo,
    int $workspaceId,
    int $ownerMerchantId,
    int $actorUserId,
    int $locationDbId,
    string $locationName,
    string $claimCode,
    string $pepper
): array {
    $ownedLocation=mg_merchant_location_find_by_id(
        $pdo,$workspaceId,$ownerMerchantId,$locationDbId,true
    );
    if(!$ownedLocation)mg_fail('Merchant location not found.',404);

    $hash=hash_hmac('sha256',$claimCode,$pepper);
    $active=$pdo->prepare(
        "SELECT * FROM merchant_claim_codes
         WHERE location_id=? AND status='active'
         ORDER BY id DESC FOR UPDATE"
    );
    $active->execute([$locationDbId]);
    $current=$active->fetchAll();

    foreach($current as $code){
        if(hash_equals((string)$code['code_hash'],$hash)){
            return [
                'claim_code_id'=>(string)$code['public_id'],
                'code_last4'=>(string)$code['code_last4'],
                'rotated'=>false,
            ];
        }
    }

    $duplicate=$pdo->prepare(
        "SELECT 1
         FROM merchant_claim_codes mcc
         INNER JOIN merchant_locations ml ON ml.id=mcc.location_id
         ".mg_merchant_location_scope_join('ml','location_scope_mw')."
         WHERE ".mg_merchant_location_scope_condition('ml','location_scope_mw')."
           AND mcc.code_hash=?
           AND mcc.status='active'
           AND mcc.location_id<>?
         LIMIT 1"
    );
    $duplicate->execute([$workspaceId,$ownerMerchantId,$hash,$locationDbId]);
    if($duplicate->fetchColumn())mg_fail('Location claim code already exists.',409);

    $previousId=$current!==[]?(int)$current[0]['id']:null;
    if($current!==[]){
        $pdo->prepare(
            "UPDATE merchant_claim_codes
             SET status='revoked',updated_at=NOW()
             WHERE location_id=? AND status='active'"
        )->execute([$locationDbId]);
    }

    $publicId=mg_merchant_uuid();
    $last4=substr($claimCode,-4);
    $pdo->prepare(
        "INSERT INTO merchant_claim_codes
         (public_id,merchant_user_id,location_id,label,code_hash,code_last4,status,
          valid_from,valid_until,usage_limit,usage_count,created_by_user_id,created_at,updated_at)
         VALUES (?,?,?,?,?,?,'active',NULL,NULL,NULL,0,?,NOW(),NOW())"
    )->execute([
        $publicId,$ownerMerchantId,$locationDbId,$locationName.' claim code',$hash,$last4,$actorUserId,
    ]);
    $claimCodeDbId=(int)$pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO merchant_claim_code_events
         (public_id,merchant_user_id,claim_code_id,location_id,event_type,previous_claim_code_id,
          metadata_json,actor_user_id,created_at)
         VALUES (?,?,?,?,?,?,?,?,NOW())"
    )->execute([
        mg_merchant_uuid(),$ownerMerchantId,$claimCodeDbId,$locationDbId,
        $previousId===null?'created':'rotated',$previousId,
        json_encode(['code_last4'=>$last4],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
        $actorUserId,
    ]);

    return [
        'claim_code_id'=>$publicId,
        'code_last4'=>$last4,
        'rotated'=>$previousId!==null,
    ];
}

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=$method==='GET'?mg_require_api_user():mg_require_permission('merchant.locations.manage');
$actorUserId=(int)$user['id'];
$pdo=mg_db();
$workspace=mg_merchant_ensure_workspace($pdo,$user);
$scope=mg_merchant_location_scope_context($workspace);
$workspaceId=(int)$scope['workspace_id'];
$ownerMerchantId=(int)$scope['owner_merchant_id'];

if($method==='GET'){
    try{
        $stmt=$pdo->prepare(
            "SELECT ml.public_id,ml.name,ml.location_code,ml.address_line1,ml.address_line2,ml.city,ml.region,
                    ml.postal_code,ml.country_code,ml.timezone,ml.phone,ml.status,ml.is_primary,
                    ml.metadata_json,ml.created_at,ml.updated_at,ml.workspace_id,ml.merchant_user_id,
                    JSON_UNQUOTE(JSON_EXTRACT(ml.metadata_json,'$.latitude')) AS latitude,
                    JSON_UNQUOTE(JSON_EXTRACT(ml.metadata_json,'$.longitude')) AS longitude,
                    JSON_UNQUOTE(JSON_EXTRACT(ml.metadata_json,'$.check_in_radius_meters')) AS check_in_radius_meters,
                    EXISTS(
                        SELECT 1 FROM merchant_claim_codes mcc
                        WHERE mcc.location_id=ml.id
                          AND mcc.status='active'
                          AND (mcc.valid_from IS NULL OR mcc.valid_from<=NOW())
                          AND (mcc.valid_until IS NULL OR mcc.valid_until>=NOW())
                          AND (mcc.usage_limit IS NULL OR mcc.usage_count<mcc.usage_limit)
                    ) AS has_active_claim_code,
                    (
                        SELECT mcc.code_last4 FROM merchant_claim_codes mcc
                        WHERE mcc.location_id=ml.id
                          AND mcc.status='active'
                        ORDER BY mcc.id DESC LIMIT 1
                    ) AS claim_code_last4
             FROM merchant_locations ml
             ".mg_merchant_location_scope_join('ml','location_scope_mw')."
             WHERE ".mg_merchant_location_scope_condition('ml','location_scope_mw')."
             ORDER BY ml.is_primary DESC,ml.name,ml.id"
        );
        $stmt->execute([$workspaceId,$ownerMerchantId]);
        $rows=$stmt->fetchAll() ?: [];
        foreach($rows as &$row){
            $row['latitude']=$row['latitude']!==null&&$row['latitude']!==''?(float)$row['latitude']:null;
            $row['longitude']=$row['longitude']!==null&&$row['longitude']!==''?(float)$row['longitude']:null;
            $row['check_in_radius_meters']=$row['check_in_radius_meters']!==null&&$row['check_in_radius_meters']!==''?(int)$row['check_in_radius_meters']:150;
            $row['geo_enabled']=$row['latitude']!==null&&$row['longitude']!==null;
            $row['ownership_normalized']=(int)$row['workspace_id']===$workspaceId
                && (int)$row['merchant_user_id']===$ownerMerchantId;
            unset($row['workspace_id'],$row['merchant_user_id']);
        }
        unset($row);
        mg_ok(['locations'=>$rows,'schema_ready'=>true]);
    }catch(Throwable $error){
        mg_security_log('warning','merchant.locations.schema_unavailable','Merchant location schema is unavailable.',[
            'exception_class'=>$error::class,
        ],$actorUserId);
        mg_ok(['locations'=>[],'schema_ready'=>false],'Merchant locations unavailable until the locations schema is installed.');
    }
}

if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();
mg_require_csrf_for_write($input);

$locationId=strtolower(trim((string)($input['location_id']??'')));
$name=trim((string)($input['name']??''));
$claimCode=strtoupper(trim((string)($input['claim_code']??'')));
$address1=trim((string)($input['address_line1']??''));
$address2=trim((string)($input['address_line2']??''))?:null;
$city=trim((string)($input['city']??''))?:null;
$region=trim((string)($input['region']??''))?:null;
$postalCode=trim((string)($input['postal_code']??''))?:null;
$phone=trim((string)($input['phone']??''))?:null;
$timezone=trim((string)($input['timezone']??$workspace['timezone']));
$status=trim((string)($input['status']??'active'));
$primary=!empty($input['is_primary'])?1:0;
$countryCode=strtoupper(trim((string)($input['country_code']??'US')));
$latitude=mg_merchant_location_float_or_null($input['latitude']??null);
$longitude=mg_merchant_location_float_or_null($input['longitude']??null);
$radius=(int)($input['check_in_radius_meters']??150);
$radius=max(25,min(5000,$radius));
$isCreate=$locationId==='';

if($address1===''||mb_strlen($address1)>190){
    mg_fail('Location address is required and must be 190 characters or fewer.',422);
}

if(
    (!$isCreate&&(strlen($locationId)!==36||!preg_match('/^[a-f0-9-]{36}$/',$locationId)))
    ||$name===''||mb_strlen($name)>180
    ||($isCreate&&$claimCode==='')
    ||($claimCode!==''&&(mb_strlen($claimCode)<4||mb_strlen($claimCode)>64||!preg_match('/^[A-Z0-9_-]{4,64}$/',$claimCode)))
    ||!in_array($status,['active','inactive','archived'],true)
    ||!in_array($timezone,timezone_identifiers_list(),true)
    ||!preg_match('/^[A-Z]{2}$/',$countryCode)
    ||($latitude!==null&&($latitude<-90||$latitude>90))
    ||($longitude!==null&&($longitude<-180||$longitude>180))
    ||(($latitude===null)!==($longitude===null))
    ||($primary&&$status!=='active')
){
    mg_fail('Invalid location.',422);
}

if($isCreate){
    mg_package_require_limit_available(
        $pdo,$user,'max_locations',
        mg_merchant_location_count($pdo,$workspaceId,$ownerMerchantId),
        'Location limit reached.'
    );
}

$pepper=$claimCode!==''?mg_claim_code_pepper():'';

$pdo->beginTransaction();
try{
    $locationDbId=0;
    $locationCode='';
    $existingMetadata=[];
    if($isCreate){
        $locationId=mg_merchant_uuid();
        $locationCode=mg_merchant_unique_location_code($pdo,$workspaceId,$ownerMerchantId,$name);
    }else{
        $row=mg_merchant_location_find_by_public_id(
            $pdo,$workspaceId,$ownerMerchantId,$locationId,true
        );
        if(!$row)mg_fail('Location not found.',404);
        $locationDbId=(int)$row['id'];
        $locationCode=(string)$row['location_code'];
        $existingMetadata=mg_merchant_location_json($row['metadata_json']??null);
        if($locationCode===''){
            $locationCode=mg_merchant_unique_location_code(
                $pdo,$workspaceId,$ownerMerchantId,$name,$locationId
            );
        }
    }

    if($primary){
        $primaryStmt=$pdo->prepare(
            'UPDATE merchant_locations ml '
            .mg_merchant_location_scope_join('ml','location_scope_mw').'
             SET ml.is_primary=0,ml.updated_at=NOW()
             WHERE '.mg_merchant_location_scope_condition('ml','location_scope_mw')
        );
        $primaryStmt->execute([$workspaceId,$ownerMerchantId]);
    }

    $metadata=$existingMetadata;
    if($latitude!==null&&$longitude!==null){
        $metadata['latitude']=$latitude;
        $metadata['longitude']=$longitude;
        $metadata['check_in_radius_meters']=$radius;
        $metadata['geo_source']='merchant_registered_location';
    }else{
        unset($metadata['latitude'],$metadata['longitude'],$metadata['check_in_radius_meters'],$metadata['geo_source']);
    }
    $metadataJson=$metadata!==[]?json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null;

    if($isCreate){
        $pdo->prepare(
            'INSERT INTO merchant_locations
             (public_id,workspace_id,merchant_user_id,name,location_code,address_line1,address_line2,
              city,region,postal_code,country_code,timezone,phone,status,is_primary,metadata_json,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())'
        )->execute([
            $locationId,$workspaceId,$ownerMerchantId,$name,$locationCode,$address1,$address2,
            $city,$region,$postalCode,$countryCode,$timezone,$phone,$status,$primary,$metadataJson,
        ]);
        $locationDbId=(int)$pdo->lastInsertId();
    }else{
        $stmt=$pdo->prepare(
            'UPDATE merchant_locations
             SET workspace_id=?,merchant_user_id=?,name=?,location_code=?,address_line1=?,address_line2=?,city=?,region=?,postal_code=?,
                 country_code=?,timezone=?,phone=?,status=?,is_primary=?,metadata_json=?,updated_at=NOW()
             WHERE id=? AND public_id=?'
        );
        $stmt->execute([
            $workspaceId,$ownerMerchantId,$name,$locationCode,$address1,$address2,$city,$region,$postalCode,
            $countryCode,$timezone,$phone,$status,$primary,$metadataJson,
            $locationDbId,$locationId,
        ]);
    }

    $claimResult=null;
    if($claimCode!==''){
        $claimResult=mg_merchant_location_record_claim_code(
            $pdo,$workspaceId,$ownerMerchantId,$actorUserId,$locationDbId,$name,$claimCode,$pepper
        );
    }

    $pdo->prepare(
        "UPDATE merchant_onboarding_steps SET status='completed',completed_at=NOW(),completed_by_user_id=?,updated_at=NOW()
         WHERE workspace_id=? AND step_key='first_location'"
    )->execute([$actorUserId,$workspaceId]);
    $pdo->prepare(
        "UPDATE merchant_onboarding_steps SET status='available',updated_at=NOW()
         WHERE workspace_id=? AND step_key='claim_configuration' AND status='locked'"
    )->execute([$workspaceId]);
    $percent=mg_merchant_recalculate_onboarding($pdo,$workspaceId);
    $pdo->commit();

    mg_audit('merchant.location_saved','merchant_location',[
        'location_id'=>$locationId,
        'claim_code_changed'=>$claimResult!==null,
        'claim_code_last4'=>$claimResult['code_last4']??null,
        'geo_enabled'=>$latitude!==null&&$longitude!==null,
        'workspace_id'=>$workspaceId,
        'owner_merchant_id'=>$ownerMerchantId,
        'ownership_normalized'=>true,
    ],$actorUserId);
    mg_ok([
        'location_id'=>$locationId,
        'location_code'=>$locationCode,
        'has_active_claim_code'=>$claimResult!==null,
        'claim_code_last4'=>$claimResult['code_last4']??null,
        'claim_code_rotated'=>$claimResult['rotated']??false,
        'latitude'=>$latitude,
        'longitude'=>$longitude,
        'check_in_radius_meters'=>$latitude!==null?$radius:null,
        'ownership_normalized'=>true,
        'schema_ready'=>true,
        'onboarding_percent'=>$percent,
    ],'Location saved.',201);
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    mg_security_log('error','merchant.locations.save_failed','Unable to save merchant location.',[
        'exception_class'=>$error::class,
    ],$actorUserId);
    mg_fail('Unable to save location.',500);
}
