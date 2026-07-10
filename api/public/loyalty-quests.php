<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

function mg_public_quest_json(mixed $value): array
{
    if (!is_string($value) || trim($value) === '') return [];
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function mg_public_quest_distance(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earth = 3958.7613;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $earth * 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
}

function mg_public_quest_image(array $rules): string
{
    foreach (['cover_image_url','quest_image_url','media_image_url','image_url'] as $key) {
        $value = trim((string)($rules[$key] ?? ''));
        if ($value !== '') return $value;
    }
    return '/assets/images/loyalty-quest-placeholder.svg';
}

mg_require_method('GET');
$pdo = mg_db();
$q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
$location = mb_strtolower(trim((string)($_GET['location'] ?? '')));
$action = trim((string)($_GET['action'] ?? 'all'));
$rewardFilter = trim((string)($_GET['reward'] ?? 'all'));
$sort = trim((string)($_GET['sort'] ?? 'featured'));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(24, max(6, (int)($_GET['limit'] ?? 12)));
$latitude = is_numeric($_GET['lat'] ?? null) ? (float)$_GET['lat'] : null;
$longitude = is_numeric($_GET['lng'] ?? null) ? (float)$_GET['lng'] : null;
$radiusMiles = min(250.0, max(1.0, is_numeric($_GET['radius'] ?? null) ? (float)$_GET['radius'] : 50.0));
if (($latitude === null) !== ($longitude === null) || ($latitude !== null && ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180))) {
    mg_fail('Invalid location coordinates.', 422);
}
$allowedActions=['all','location_visit','qr_scan','purchase','product_purchase','event_attendance','referral','social_action','milestone','multi_location','sequence'];
if(!in_array($action,$allowedActions,true))$action='all';
if(!in_array($rewardFilter,['all','available','limited'],true))$rewardFilter='all';
if(!in_array($sort,['featured','nearby','ending','newest'],true))$sort='featured';

try {
    $sql = "SELECT c.public_id,c.public_slug,c.title,c.description,c.starts_at,c.ends_at,c.quantity_limit,c.issued_count,c.rules_json,c.created_at,c.updated_at,
        rt.title reward_title,rt.value_type,rt.value_amount_cents,rt.value_percent,rt.currency,rt.quantity_limit reward_quantity_limit,rt.issued_count reward_issued_count,
        COALESCE(pp.display_name,mw.display_name,u.display_name,u.full_name,'Microgifter Merchant') merchant_name,
        pp.slug merchant_slug,pp.avatar_url merchant_avatar,pp.headline merchant_headline,
        ml.public_id location_public_id,ml.name location_name,ml.city,ml.region,ml.postal_code,ml.address_line1,ml.metadata_json location_metadata
        FROM campaigns c
        INNER JOIN users u ON u.id=c.merchant_user_id AND u.status='active'
        LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id AND rt.status='active'
        LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id AND pp.status='active' AND pp.visibility='public'
        LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=c.merchant_user_id
        LEFT JOIN merchant_locations ml ON ml.merchant_user_id=c.merchant_user_id AND ml.status='active' AND ml.public_id=JSON_UNQUOTE(JSON_EXTRACT(c.rules_json,'$.location_id'))
        WHERE c.campaign_type='loyalty_quest' AND c.status='active'
          AND (c.starts_at IS NULL OR c.starts_at<=NOW())
          AND (c.ends_at IS NULL OR c.ends_at>NOW())
          AND (JSON_EXTRACT(c.rules_json,'$.visibility') IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(c.rules_json,'$.visibility'))='public')
        ORDER BY c.updated_at DESC LIMIT 250";
    $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $quests=[];
    foreach($rows as $row){
        $rules=mg_public_quest_json($row['rules_json'] ?? null);
        $rowAction=(string)($rules['action_type'] ?? '');
        if($action!=='all' && $rowAction!==$action)continue;
        $haystack=mb_strtolower(implode(' ',[(string)$row['title'],(string)($row['description']??''),(string)$row['merchant_name'],(string)($row['location_name']??''),(string)($row['city']??''),(string)($row['region']??'')]));
        if($q!=='' && !str_contains($haystack,$q))continue;
        $locationHaystack=mb_strtolower(implode(' ',[(string)($row['location_name']??''),(string)($row['city']??''),(string)($row['region']??''),(string)($row['postal_code']??''),(string)($row['address_line1']??'')]));
        if($location!=='' && !str_contains($locationHaystack,$location))continue;
        $remaining=$row['quantity_limit']===null?null:max(0,(int)$row['quantity_limit']-(int)$row['issued_count']);
        if($rewardFilter==='available' && $remaining===0)continue;
        if($rewardFilter==='limited' && ($remaining===null || $remaining>25))continue;
        $locationMeta=mg_public_quest_json($row['location_metadata'] ?? null);
        $questLat=is_numeric($locationMeta['latitude']??null)?(float)$locationMeta['latitude']:null;
        $questLng=is_numeric($locationMeta['longitude']??null)?(float)$locationMeta['longitude']:null;
        $distance=null;
        if($latitude!==null && $questLat!==null && $questLng!==null){
            $distance=mg_public_quest_distance($latitude,$longitude,$questLat,$questLng);
            if($distance>$radiusMiles)continue;
        }
        $ref=(string)($row['public_slug'] ?: $row['public_id']);
        $value='';
        if(($row['value_type']??'')==='percent' && $row['value_percent']!==null)$value=rtrim(rtrim(number_format((float)$row['value_percent'],2),'0'),'.').'%';
        elseif($row['value_amount_cents']!==null)$value=strtoupper((string)($row['currency']??'USD')).' '.number_format(((int)$row['value_amount_cents'])/100,2);
        $quests[]=[
            'id'=>(string)$row['public_id'],'slug'=>$row['public_slug']??null,'title'=>(string)$row['title'],'description'=>(string)($row['description']??''),
            'action_type'=>$rowAction,'verification_type'=>(string)($rules['verification_type']??''),'image_url'=>mg_public_quest_image($rules),
            'starts_at'=>$row['starts_at']??null,'ends_at'=>$row['ends_at']??null,'remaining'=>$remaining,'featured'=>!empty($rules['featured']),
            'reward'=>['title'=>$row['reward_title']??null,'value'=>$value],
            'merchant'=>['name'=>(string)$row['merchant_name'],'slug'=>$row['merchant_slug']??null,'avatar_url'=>$row['merchant_avatar']??null,'headline'=>$row['merchant_headline']??null],
            'location'=>['id'=>$row['location_public_id']??null,'name'=>$row['location_name']??null,'city'=>$row['city']??null,'region'=>$row['region']??null,'postal_code'=>$row['postal_code']??null,'latitude'=>$questLat,'longitude'=>$questLng,'distance_miles'=>$distance===null?null:round($distance,1)],
            'public_url'=>'/loyalty-quest.php?campaign='.rawurlencode($ref),
            'merchant_url'=>!empty($row['merchant_slug'])?'/quest-merchant.php?merchant='.rawurlencode((string)$row['merchant_slug']):null,
            'created_at'=>$row['created_at']??null,'updated_at'=>$row['updated_at']??null,
        ];
    }
    usort($quests,static function(array $a,array $b)use($sort):int{
        if($sort==='nearby')return ($a['location']['distance_miles']??PHP_FLOAT_MAX)<=>($b['location']['distance_miles']??PHP_FLOAT_MAX);
        if($sort==='ending')return strtotime((string)($a['ends_at']??'2999-12-31'))<=>strtotime((string)($b['ends_at']??'2999-12-31'));
        if($sort==='newest')return strtotime((string)$b['created_at'])<=>strtotime((string)$a['created_at']);
        return ((int)$b['featured']<=>(int)$a['featured']) ?: (strtotime((string)$b['updated_at'])<=>strtotime((string)$a['updated_at']));
    });
    $total=count($quests);$offset=($page-1)*$limit;$items=array_slice($quests,$offset,$limit);
    mg_ok(['quests'=>$items,'pagination'=>['page'=>$page,'limit'=>$limit,'total'=>$total,'has_more'=>$offset+$limit<$total],'filters'=>['q'=>$q,'location'=>$location,'action'=>$action,'reward'=>$rewardFilter,'sort'=>$sort,'radius_miles'=>$radiusMiles],'schema_ready'=>true]);
} catch(Throwable $error){
    mg_security_log('warning','public.loyalty_quests.unavailable','Loyalty Quest marketplace is unavailable.',['exception_class'=>$error::class]);
    mg_ok(['quests'=>[],'pagination'=>['page'=>1,'limit'=>$limit,'total'=>0,'has_more'=>false],'schema_ready'=>false],'Loyalty Quest marketplace is temporarily unavailable.');
}
