<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/storage.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
if(!in_array($method,['GET','HEAD'],true))mg_fail('Method not allowed.',405);
$campaignRef=strtolower(trim((string)($_GET['campaign']??'')));
$assetId=strtolower(trim((string)($_GET['asset']??'')));
if($campaignRef===''||mb_strlen($campaignRef)>160||preg_match('/^[a-z0-9_-]+$/',$campaignRef)!==1)mg_fail('Invalid Loyalty Quest.',422);
if(strlen($assetId)!==36||preg_match('/^[a-f0-9-]{36}$/',$assetId)!==1)mg_fail('Invalid quest image.',422);
$pdo=mg_db();
$stmt=$pdo->prepare("SELECT c.public_id,c.public_slug,c.merchant_user_id,c.status,c.rules_json,ca.storage_provider,ca.storage_key,ca.original_filename,ca.mime_type,ca.byte_size,ca.checksum_sha256
 FROM campaigns c
 INNER JOIN catalog_assets ca ON ca.public_id=? AND ca.owner_user_id=c.merchant_user_id AND ca.asset_type='image' AND ca.status='ready'
 WHERE c.campaign_type='loyalty_quest' AND c.status IN ('active','paused','ended') AND (c.public_id=? OR c.public_slug=?) LIMIT 1");
$stmt->execute([$assetId,$campaignRef,$campaignRef]);
$row=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$row)mg_fail('Quest image not found.',404);
$rules=json_decode((string)($row['rules_json']??''),true);if(!is_array($rules))$rules=[];
$creative=is_array($rules['creative']??null)?$rules['creative']:[];
$visibility=(string)($rules['visibility']??'public');
$attached=(string)($creative['cover_asset_id']??$rules['cover_image_asset_id']??'');
if($visibility!=='public'||!hash_equals($assetId,strtolower($attached)))mg_fail('Quest image not found.',404);
try{$path=mg_storage_resolve_asset_path((string)$row['storage_provider'],(string)$row['storage_key']);}catch(Throwable $error){mg_security_log('error','public.loyalty_quest_media_resolution_failed','Unable to resolve Loyalty Quest media.',['campaign_id'=>(string)$row['public_id'],'asset_id'=>$assetId,'exception_class'=>$error::class]);mg_fail('Quest image unavailable.',404);}
if(!is_file($path)||!is_readable($path))mg_fail('Quest image unavailable.',404);
$size=filesize($path);if($size===false||$size<1)mg_fail('Quest image unavailable.',404);$size=(int)$size;
$mime=strtolower(trim((string)($row['mime_type']??'')));if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))mg_fail('Quest image unavailable.',404);
$filename=preg_replace('/[^A-Za-z0-9._-]+/','_',basename((string)($row['original_filename']??'loyalty-quest-image')))?:'loyalty-quest-image';
$etag='"'.((string)($row['checksum_sha256']??'')?:hash('sha256',$assetId.'|'.$size)).'"';
if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){header('ETag: '.$etag);http_response_code(304);exit;}
header('Content-Type: '.$mime);header('Content-Length: '.$size);header('Content-Disposition: inline; filename="'.$filename.'"');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: cross-origin');header('Access-Control-Allow-Origin: *');header('Cache-Control: public, max-age=300, stale-while-revalidate=300');header('ETag: '.$etag);header('X-Robots-Tag: noindex, nofollow');
if($method==='HEAD')exit;if(session_status()===PHP_SESSION_ACTIVE)session_write_close();while(ob_get_level()>0)ob_end_clean();readfile($path);exit;
