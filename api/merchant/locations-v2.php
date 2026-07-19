<?php
declare(strict_types=1);

require_once __DIR__ . '/_claims.php';

function mg_locations_v2_float(mixed $value): ?float
{
    if ($value === null || $value === '') return null;
    if (!is_numeric($value)) return null;
    return (float)$value;
}

function mg_locations_v2_blockers(PDO $pdo, int $locationId): array
{
    $blockers = [];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM merchant_claim_codes WHERE location_id=? AND status='active' AND (valid_from IS NULL OR valid_from<=NOW()) AND (valid_until IS NULL OR valid_until>=NOW()) AND (usage_limit IS NULL OR usage_count<usage_limit)");
    $stmt->execute([$locationId]);
    $activeCodes = (int)$stmt->fetchColumn();
    if ($activeCodes > 0) $blockers[] = ['type'=>'active_claim_codes','count'=>$activeCodes,'message'=>'Deactivate or revoke active claim codes before archiving this location.'];

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM scanner_device_sessions WHERE location_id=? AND status='active'");
        $stmt->execute([$locationId]);
        $devices = (int)$stmt->fetchColumn();
        if ($devices > 0) $blockers[] = ['type'=>'active_scanner_devices','count'=>$devices,'message'=>'Disable or reassign active scanner devices before archiving this location.'];
    } catch (Throwable) {}

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM gift_claims WHERE location_id=? AND status NOT IN ('redeemed','cancelled','expired','revoked')");
        $stmt->execute([$locationId]);
        $claims = (int)$stmt->fetchColumn();
        if ($claims > 0) $blockers[] = ['type'=>'open_claims','count'=>$claims,'message'=>'Resolve open claims before archiving this location.'];
    } catch (Throwable) {}

    return $blockers;
}

function mg_locations_v2_slug(string $name): string
{
    $slug = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
    return substr($slug !== '' ? $slug : 'location', 0, 80);
}

function mg_locations_v2_code(PDO $pdo, int $workspaceId, int $ownerMerchantId, string $name, string $exclude=''): string
{
    $base = mg_locations_v2_slug($name);
    $candidate = $base;
    $n = 1;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM merchant_locations WHERE workspace_id=? AND merchant_user_id=? AND location_code=? AND public_id<>?");
    while (true) {
        $stmt->execute([$workspaceId,$ownerMerchantId,$candidate,$exclude]);
        if ((int)$stmt->fetchColumn() === 0) return $candidate;
        $n++;
        $candidate = substr($base,0,max(1,80-strlen((string)$n)-1)).'-'.$n;
    }
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = $method === 'GET' ? mg_require_api_user() : mg_require_permission('merchant.locations.manage');
$actorUserId = (int)$user['id'];
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$scope = mg_merchant_location_scope_context($workspace);
$workspaceId = (int)$scope['workspace_id'];
$ownerMerchantId = (int)$scope['owner_merchant_id'];

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT ml.*, 
        (SELECT COUNT(*) FROM merchant_claim_codes c WHERE c.location_id=ml.id) claim_code_count,
        (SELECT COUNT(*) FROM merchant_claim_codes c WHERE c.location_id=ml.id AND c.status='active' AND (c.valid_from IS NULL OR c.valid_from<=NOW()) AND (c.valid_until IS NULL OR c.valid_until>=NOW()) AND (c.usage_limit IS NULL OR c.usage_count<c.usage_limit)) active_claim_code_count
        FROM merchant_locations ml
        WHERE ml.workspace_id=? AND ml.merchant_user_id=?
        ORDER BY ml.is_primary DESC, ml.name, ml.id");
    $stmt->execute([$workspaceId,$ownerMerchantId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $metadata = json_decode((string)($row['metadata_json'] ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $row['latitude'] = isset($metadata['latitude']) ? (float)$metadata['latitude'] : null;
        $row['longitude'] = isset($metadata['longitude']) ? (float)$metadata['longitude'] : null;
        $row['check_in_radius_meters'] = isset($metadata['check_in_radius_meters']) ? (int)$metadata['check_in_radius_meters'] : 150;
        $row['archive_blockers'] = mg_locations_v2_blockers($pdo, (int)$row['id']);
        $row['archive_ready'] = $row['archive_blockers'] === [];
        unset($row['id'],$row['metadata_json']);
    }
    unset($row);
    mg_ok(['locations'=>$rows,'multi_claim_codes'=>true,'schema_ready'=>true]);
}

if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);

$publicId = strtolower(trim((string)($input['location_id'] ?? '')));
$isCreate = $publicId === '';
$name = trim((string)($input['name'] ?? ''));
$address1 = trim((string)($input['address_line1'] ?? ''));
$status = trim((string)($input['status'] ?? 'active'));
$timezone = trim((string)($input['timezone'] ?? ($workspace['timezone'] ?? 'America/Phoenix')));
$country = strtoupper(trim((string)($input['country_code'] ?? 'US')));
$primary = !empty($input['is_primary']) ? 1 : 0;
$latitude = mg_locations_v2_float($input['latitude'] ?? null);
$longitude = mg_locations_v2_float($input['longitude'] ?? null);
$radius = max(25,min(5000,(int)($input['check_in_radius_meters'] ?? 150)));
$archiveReason = trim((string)($input['archive_reason'] ?? ''));

if ($name === '' || mb_strlen($name)>180 || $address1 === '' || mb_strlen($address1)>190) mg_fail('Location name and address are required.',422);
if (!in_array($status,['active','inactive','archived'],true)) mg_fail('Invalid location status.',422);
if (!in_array($timezone,timezone_identifiers_list(),true)) mg_fail('Invalid timezone.',422);
if (!preg_match('/^[A-Z]{2}$/',$country)) mg_fail('Invalid country code.',422);
if (($latitude===null)!==($longitude===null)) mg_fail('Latitude and longitude must be supplied together.',422);
if ($latitude!==null && ($latitude < -90 || $latitude > 90)) mg_fail('Invalid latitude.',422);
if ($longitude!==null && ($longitude < -180 || $longitude > 180)) mg_fail('Invalid longitude.',422);
if ($primary && $status !== 'active') mg_fail('Only an active location can be primary.',422);

$pdo->beginTransaction();
try {
    $location = null;
    if ($isCreate) {
        mg_package_require_limit_available($pdo,$user,'max_locations',mg_merchant_location_count($pdo,$workspaceId,$ownerMerchantId),'Location limit reached.');
        $publicId = mg_merchant_uuid();
        $locationCode = mg_locations_v2_code($pdo,$workspaceId,$ownerMerchantId,$name);
    } else {
        $location = mg_merchant_location_find_by_public_id($pdo,$workspaceId,$ownerMerchantId,$publicId,true);
        if (!$location) mg_fail('Location not found.',404);
        $locationCode = (string)($location['location_code'] ?: mg_locations_v2_code($pdo,$workspaceId,$ownerMerchantId,$name,$publicId));
        if ($status === 'archived') {
            $blockers = mg_locations_v2_blockers($pdo,(int)$location['id']);
            if ($blockers !== []) mg_fail($blockers[0]['message'],409,['archive_blockers'=>$blockers]);
            if ($archiveReason === '') mg_fail('Provide an archive reason.',422);
        }
    }

    if ($primary) {
        $pdo->prepare('UPDATE merchant_locations SET is_primary=0,updated_at=NOW() WHERE workspace_id=? AND merchant_user_id=?')->execute([$workspaceId,$ownerMerchantId]);
    }

    $metadata = [];
    if ($location && !empty($location['metadata_json'])) {
        $decoded = json_decode((string)$location['metadata_json'],true);
        if (is_array($decoded)) $metadata = $decoded;
    }
    if ($latitude!==null) {
        $metadata['latitude']=$latitude;
        $metadata['longitude']=$longitude;
        $metadata['check_in_radius_meters']=$radius;
        $metadata['geo_source']='merchant_registered_location';
    } else {
        unset($metadata['latitude'],$metadata['longitude'],$metadata['check_in_radius_meters'],$metadata['geo_source']);
    }
    $metadataJson = $metadata ? json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) : null;

    $values = [
        $workspaceId,$ownerMerchantId,$name,$locationCode,$address1,
        trim((string)($input['address_line2']??'')) ?: null,
        trim((string)($input['city']??'')) ?: null,
        trim((string)($input['region']??'')) ?: null,
        trim((string)($input['postal_code']??'')) ?: null,
        $country,$timezone,trim((string)($input['phone']??'')) ?: null,$status,$primary,$metadataJson
    ];

    if ($isCreate) {
        $pdo->prepare("INSERT INTO merchant_locations (public_id,workspace_id,merchant_user_id,name,location_code,address_line1,address_line2,city,region,postal_code,country_code,timezone,phone,status,is_primary,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
            ->execute(array_merge([$publicId],$values));
    } else {
        $archivedAt = $status === 'archived' ? date('Y-m-d H:i:s') : null;
        $archivedBy = $status === 'archived' ? $actorUserId : null;
        $reason = $status === 'archived' ? $archiveReason : null;
        $pdo->prepare("UPDATE merchant_locations SET workspace_id=?,merchant_user_id=?,name=?,location_code=?,address_line1=?,address_line2=?,city=?,region=?,postal_code=?,country_code=?,timezone=?,phone=?,status=?,is_primary=?,metadata_json=?,archived_at=?,archived_by_user_id=?,archive_reason=?,updated_at=NOW() WHERE id=?")
            ->execute(array_merge($values,[$archivedAt,$archivedBy,$reason,(int)$location['id']]));
    }

    $pdo->prepare("UPDATE merchant_onboarding_steps SET status='completed',completed_at=NOW(),completed_by_user_id=?,updated_at=NOW() WHERE workspace_id=? AND step_key='first_location'")->execute([$actorUserId,$workspaceId]);
    $percent = mg_merchant_recalculate_onboarding($pdo,$workspaceId);
    $pdo->commit();

    mg_audit('merchant.location_saved','merchant_location',['location_id'=>$publicId,'status'=>$status,'workspace_id'=>$workspaceId,'multi_claim_codes'=>true],$actorUserId);
    mg_ok(['location_id'=>$publicId,'location_code'=>$locationCode,'onboarding_percent'=>$percent,'multi_claim_codes'=>true],'Location saved.',201);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (function_exists('mg_security_log')) mg_security_log('error','merchant.locations_v2.save_failed','Unable to save merchant location.',['exception_class'=>$error::class],$actorUserId);
    throw $error;
}
