<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/entitlements/_lifecycle.php';
mg_require_method('GET');
$user=mg_require_api_user();
$grantId=trim((string)($_GET['grant']??''));
$token=trim((string)($_GET['token']??''));
if($grantId===''||$token==='')mg_fail('Delivery grant and token are required.',422);
$pdo=mg_db();
$grant=null;
$denialReason=null;
$pdo->beginTransaction();
try{
    $stmt=$pdo->prepare("SELECT g.*,e.pppm_item_id,e.status entitlement_status,e.expires_at entitlement_expires_at,a.public_id asset_public_id,a.original_filename,a.mime_type,a.byte_size,a.storage_provider,a.storage_key,a.status asset_status FROM asset_delivery_grants g INNER JOIN entitlements e ON e.id=g.entitlement_id INNER JOIN catalog_assets a ON a.id=g.asset_id WHERE g.public_id=? AND g.user_id=? AND g.expires_at>NOW() AND g.consumed_at IS NULL LIMIT 1 FOR UPDATE");
    $stmt->execute([$grantId,(int)$user['id']]);
    $grant=$stmt->fetch();
    $valid=$grant&&hash_equals((string)$grant['token_hash'],hash('sha256',$token))&&(string)$grant['entitlement_status']==='active'&&(string)$grant['asset_status']==='ready'&&($grant['entitlement_expires_at']===null||strtotime((string)$grant['entitlement_expires_at'])>time());
    if(!$valid){
        $denialReason='invalid_or_inactive_delivery_grant';
        $pdo->rollBack();
    }else{
        $consume=$pdo->prepare('UPDATE asset_delivery_grants SET consumed_at=NOW() WHERE id=? AND consumed_at IS NULL');
        $consume->execute([(int)$grant['id']]);
        if($consume->rowCount()!==1){
            $denialReason='delivery_grant_already_consumed';
            $pdo->rollBack();
        }else{
            mg_entitlement_record_access($pdo,(int)$grant['entitlement_id'],(int)$grant['asset_id'],(int)$grant['pppm_item_id'],(int)$user['id'],'download_completed','delivery_grant_consumed',['grant_id'=>$grantId]);
            $pdo->commit();
        }
    }
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $error;
}
if($denialReason!==null){
    mg_entitlement_record_access($pdo,$grant?(int)$grant['entitlement_id']:null,$grant?(int)$grant['asset_id']:null,$grant?(int)$grant['pppm_item_id']:null,(int)$user['id'],'denied',$denialReason,['grant_id'=>$grantId]);
    mg_fail('Invalid or expired delivery grant.',403);
}
$delivery=mg_entitlement_delivery_response($grant);
mg_ok(['delivery'=>$delivery,'asset'=>['asset_id'=>$grant['asset_public_id'],'filename'=>$grant['original_filename'],'mime_type'=>$grant['mime_type'],'byte_size'=>(int)$grant['byte_size']]]);
