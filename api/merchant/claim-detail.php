<?php
declare(strict_types=1);
require_once __DIR__ . '/_claims.php';
mg_require_method('GET');
$user=mg_require_permission('merchant.claims.view');
$identifier=trim((string)($_GET['id']??''));
if($identifier==='')mg_fail('Claim identifier required.',422);
$pdo=mg_db();
$workspace=mg_claim_workspace($pdo,$user);
$scope=mg_merchant_location_scope_context($workspace);
$workspaceId=(int)$scope['workspace_id'];
$ownerMerchantId=(int)$scope['owner_merchant_id'];
$claim=mg_claim_lookup($pdo,$ownerMerchantId,$identifier);
$eligibility=$pdo->prepare('SELECT e.location_id,ml.public_id location_id_public,ml.name location_name FROM gift_merchant_eligibility e LEFT JOIN merchant_locations ml ON ml.id=e.location_id WHERE e.gift_id=? AND e.merchant_user_id=? ORDER BY ml.name');
$eligibility->execute([(int)$claim['gift_db_id'],$ownerMerchantId]);
$attempts=[];$events=[];$exceptions=[];
if($claim['claim_db_id']){
 $q=$pdo->prepare('SELECT successful,actor_user_id,created_at FROM gift_claim_attempts WHERE claim_id=? ORDER BY created_at DESC,id DESC LIMIT 100');$q->execute([(int)$claim['claim_db_id']]);$attempts=$q->fetchAll();
 $q=$pdo->prepare('SELECT mce.public_id,mce.exception_type,mce.status,mce.priority,mce.summary,mce.resolution_notes,mce.created_at,mce.updated_at FROM merchant_claim_exceptions mce WHERE mce.claim_id=? AND mce.merchant_user_id=? ORDER BY mce.created_at DESC');$q->execute([(int)$claim['claim_db_id'],$ownerMerchantId]);$exceptions=$q->fetchAll();
}
$q=$pdo->prepare('SELECT event_type,metadata_json,created_at FROM gift_events WHERE gift_id=? ORDER BY created_at DESC,id DESC');$q->execute([(int)$claim['gift_db_id']]);$events=$q->fetchAll();
$location=null;
if($claim['location_id']){
 $row=mg_merchant_location_find_by_id($pdo,$workspaceId,$ownerMerchantId,(int)$claim['location_id']);
 if($row)$location=['public_id'=>(string)$row['public_id'],'name'=>(string)$row['name'],'location_code'=>(string)$row['location_code'],'status'=>(string)$row['status']];
}
mg_ok(['claim'=>$claim,'location'=>$location,'eligibility'=>$eligibility->fetchAll(),'attempts'=>$attempts,'events'=>$events,'exceptions'=>$exceptions]);
