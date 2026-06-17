<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/entitlements/_entitlements.php';
mg_require_method('POST');
$user=mg_require_api_user();
$input=mg_input();
mg_require_csrf_for_write($input);
$assetId=trim((string)($input['asset_id']??''));
if($assetId==='')mg_fail('Asset is required.',422);
$pdo=mg_db();
$entitlement=mg_entitlement_authorize_asset($pdo,(int)$user['id'],$assetId,['endpoint'=>'library.access']);
$grant=mg_entitlement_create_delivery_grant($pdo,$entitlement,(int)$user['id']);
mg_ok(['asset'=>['asset_id'=>$entitlement['asset_public_id'],'filename'=>$entitlement['original_filename'],'mime_type'=>$entitlement['mime_type'],'byte_size'=>(int)$entitlement['byte_size']],'entitlement_id'=>$entitlement['public_id'],'delivery'=>['grant_id'=>$grant['grant_id'],'token'=>$grant['token'],'expires_at'=>$grant['expires_at'],'download_url'=>'/api/library/download.php?grant='.rawurlencode($grant['grant_id']).'&token='.rawurlencode($grant['token'])]],'Access authorized.');
