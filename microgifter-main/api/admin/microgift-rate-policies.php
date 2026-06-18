<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/microgifts/_stage10e_operations.php';
$user=mg_require_permission('microgift.rate_policies.manage');
$pdo=mg_db();
if(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))==='GET'){
    $stmt=$pdo->query("SELECT public_id,scope,limit_count,window_seconds,block_seconds,status,priority,merchant_user_id,location_id,created_at,updated_at FROM microgift_claim_rate_policies ORDER BY scope,priority,id DESC LIMIT 250");
    mg_ok(['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
mg_require_method('POST');
$input=mg_input();mg_require_csrf_for_write($input);
$scope=trim((string)($input['scope']??''));
if(!in_array($scope,['actor','merchant','location','network','gift'],true))mg_fail('Invalid policy scope.',422);
$status=trim((string)($input['status']??'active'));
if(!in_array($status,['active','inactive'],true))mg_fail('Invalid policy status.',422);
$publicId=trim((string)($input['policy_id']??''));
$params=[isset($input['merchant_user_id'])?(int)$input['merchant_user_id']:null,isset($input['location_id'])?(int)$input['location_id']:null,$scope,max(1,(int)($input['limit_count']??1)),max(1,(int)($input['window_seconds']??60)),max(1,(int)($input['block_seconds']??120)),$status,(int)($input['priority']??100)];
if($publicId===''){
    $publicId=mg_microgift_uuid();
    $pdo->prepare('INSERT INTO microgift_claim_rate_policies (public_id,merchant_user_id,location_id,scope,limit_count,window_seconds,block_seconds,status,priority,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute(array_merge([$publicId],$params,[(int)$user['id']]));
}else{
    $stmt=$pdo->prepare('UPDATE microgift_claim_rate_policies SET merchant_user_id=?,location_id=?,scope=?,limit_count=?,window_seconds=?,block_seconds=?,status=?,priority=?,updated_at=NOW() WHERE public_id=?');
    $stmt->execute(array_merge($params,[$publicId]));
    if($stmt->rowCount()===0)mg_fail('Rate policy not found or unchanged.',404);
}
mg_ok(['policy_id'=>$publicId,'status'=>$status]);
