<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/feed/_feed.php';
require_once dirname(__DIR__) . '/social/_publishing.php';
require_once dirname(__DIR__) . '/social/_media_assets.php';

$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
if(!in_array($method,['GET','HEAD'],true))mg_fail('Method not allowed.',405);

$assetId=strtolower(trim((string)($_GET['asset']??'')));
if(strlen($assetId)!==36||preg_match('/^[a-f0-9-]{36}$/',$assetId)!==1)mg_fail('Invalid asset identifier.',422);

$pdo=mg_db();
$viewer=mg_public_profile_session_viewer($pdo);
$viewerId=isset($viewer['id'])?(int)$viewer['id']:null;
$profileMediaUrl='/api/public/media.php?asset='.$assetId;
$stmt=$pdo->prepare(
    "SELECT ca.id,ca.public_id,ca.owner_user_id,ca.asset_type,ca.storage_provider,ca.storage_key,
            ca.original_filename,ca.mime_type,ca.byte_size,ca.checksum_sha256,ca.status,ca.metadata_json,
            ca.created_at,ca.updated_at,
            EXISTS (
              SELECT 1 FROM public_profiles pp
              INNER JOIN users pu ON pu.id=pp.user_id
              WHERE pp.status='active' AND pp.visibility IN ('public','unlisted') AND pu.status='active'
                AND (pp.avatar_url=? OR pp.cover_url=?)
            ) public_profile_reference,
            EXISTS (
              SELECT 1 FROM merchant_storefronts ms
              WHERE ms.status='published' AND (ms.logo_asset_id=ca.id OR ms.cover_asset_id=ca.id)
            ) public_storefront_reference,
            EXISTS (
              SELECT 1 FROM catalog_product_version_assets cpva
              INNER JOIN catalog_product_versions cpv ON cpv.id=cpva.product_version_id
              INNER JOIN catalog_products cp ON cp.id=cpv.product_id
              WHERE cpva.asset_id=ca.id AND cp.status='published' AND cpv.version_status='published'
            ) public_product_reference,
            EXISTS (
              SELECT 1 FROM feed_post_elements fpe
              INNER JOIN feed_post_versions fpv ON fpv.id=fpe.feed_post_version_id
              INNER JOIN feed_posts fp ON fp.id=fpv.feed_post_id
              WHERE fpe.asset_id=ca.id AND fp.visibility IN ('public','unlisted')
                AND fp.status IN ('published','promoted') AND fpv.version_status='published'
            ) public_legacy_post_reference
     FROM catalog_assets ca
     WHERE ca.public_id=? AND ca.status='ready'
     LIMIT 1"
);
$stmt->execute([$profileMediaUrl,$profileMediaUrl,$assetId]);
$asset=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$asset)mg_fail('Media not found.',404);

$allowed=$viewerId!==null&&$viewerId===(int)$asset['owner_user_id'];
$publiclyCacheable=!empty($asset['public_profile_reference'])
    ||!empty($asset['public_storefront_reference'])
    ||!empty($asset['public_product_reference'])
    ||!empty($asset['public_legacy_post_reference']);
if($publiclyCacheable)$allowed=true;

if(!$allowed||!$publiclyCacheable){
    $posts=$pdo->prepare(
        "SELECT fp.*,pp.status profile_status,pp.visibility profile_visibility,u.status user_status
         FROM feed_post_assets fpa
         INNER JOIN feed_posts fp ON fp.id=fpa.feed_post_id
         INNER JOIN public_profiles pp ON pp.user_id=fp.created_by_user_id
         INNER JOIN users u ON u.id=fp.created_by_user_id
         WHERE fpa.asset_id=?
         ORDER BY fp.updated_at DESC,fp.id DESC"
    );
    $posts->execute([(int)$asset['id']]);
    $contexts=[];
    foreach($posts->fetchAll(PDO::FETCH_ASSOC) as $post){
        if((string)$post['user_status']!=='active'||(string)$post['profile_status']!=='active'||!in_array((string)$post['profile_visibility'],['public','unlisted'],true))continue;
        $authorId=(int)$post['created_by_user_id'];
        if(!isset($contexts[$authorId]))$contexts[$authorId]=mg_social_view_context($pdo,$viewerId,$authorId);
        if(!mg_social_can_view($pdo,$post,$viewerId,$contexts[$authorId]))continue;
        $allowed=true;
        if(in_array((string)$post['visibility'],['public','unlisted'],true))$publiclyCacheable=true;
        break;
    }
}

if(!$allowed)mg_fail('Media not found.',404);

try{
    $path=mg_storage_resolve_asset_path((string)$asset['storage_provider'],(string)$asset['storage_key']);
}catch(Throwable $error){
    mg_security_log('error','media.storage_resolution_failed','Media storage resolution failed.',[
        'asset_id'=>$assetId,
        'provider'=>(string)$asset['storage_provider'],
        'exception_class'=>$error::class,
    ],$viewerId);
    mg_fail('Media unavailable.',404);
}
if(!is_file($path)||!is_readable($path)){
    mg_security_log('error','media.file_missing','Media metadata exists but the file is unavailable.',[
        'asset_id'=>$assetId,
        'provider'=>(string)$asset['storage_provider'],
    ],$viewerId);
    mg_fail('Media unavailable.',404);
}

$size=filesize($path);
$modified=filemtime($path);
if($size===false||$size<0)mg_fail('Media unavailable.',404);
$size=(int)$size;
$modified=$modified!==false?(int)$modified:time();
$etag='"'.((string)($asset['checksum_sha256']??'')?:hash('sha256',$assetId.':'.$size.':'.$modified)).'"';
if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag&&!isset($_SERVER['HTTP_RANGE'])){
    header('ETag: '.$etag);
    http_response_code(304);
    exit;
}

$start=0;
$end=max(0,$size-1);
$status=200;
$range=trim((string)($_SERVER['HTTP_RANGE']??''));
if($range!==''&&preg_match('/^bytes=(\d*)-(\d*)$/',$range,$match)===1){
    $left=$match[1];
    $right=$match[2];
    if($left===''&&$right!==''){
        $suffix=(int)$right;
        if($suffix<1){header('Content-Range: bytes */'.$size);http_response_code(416);exit;}
        $start=max(0,$size-$suffix);
    }elseif($left!==''){
        $start=(int)$left;
        if($right!=='')$end=min($end,(int)$right);
    }
    if($start<0||$start>=$size||$end<$start){
        header('Content-Range: bytes */'.$size);
        http_response_code(416);
        exit;
    }
    $status=206;
}

$length=$size===0?0:($end-$start+1);
$mime=trim((string)($asset['mime_type']??''))?:'application/octet-stream';
$filename=preg_replace('/[^A-Za-z0-9._-]+/','_',basename((string)($asset['original_filename']??'media')))?:'media';
http_response_code($status);
header('Content-Type: '.$mime);
header('Content-Length: '.$length);
header('Accept-Ranges: bytes');
header('ETag: '.$etag);
header('Last-Modified: '.gmdate('D, d M Y H:i:s',$modified).' GMT');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Vary: Cookie, Authorization');
header('Content-Disposition: inline; filename="'.$filename.'"; filename*=UTF-8\'\''.rawurlencode((string)($asset['original_filename']??$filename)));
header('Cache-Control: '.($publiclyCacheable&&$viewerId===null?'public, max-age=300, stale-while-revalidate=60':'private, max-age=120'));
if($status===206)header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
if($method==='HEAD'||$length===0)exit;

if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
while(ob_get_level()>0)ob_end_clean();
$handle=fopen($path,'rb');
if($handle===false)mg_fail('Media unavailable.',404);
if($start>0)fseek($handle,$start);
$remaining=$length;
while($remaining>0&&!feof($handle)){
    $chunk=fread($handle,min(1048576,$remaining));
    if($chunk===false||$chunk==='')break;
    echo $chunk;
    $remaining-=strlen($chunk);
    flush();
}
fclose($handle);
exit;
