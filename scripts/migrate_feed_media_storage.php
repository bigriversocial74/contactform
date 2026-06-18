<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__).'/api/bootstrap.php';
require_once dirname(__DIR__).'/api/social/_publishing.php';
require_once dirname(__DIR__).'/api/social/_media_assets.php';

$dryRun=false;
$deleteSource=false;
$limit=1000;
$onlyAsset='';
foreach(array_slice($argv,1) as $argument){
    if($argument==='--dry-run')$dryRun=true;
    elseif($argument==='--delete-source')$deleteSource=true;
    elseif(preg_match('/^--limit=(\d+)$/',$argument,$match)===1)$limit=max(1,min(10000,(int)$match[1]));
    elseif(preg_match('/^--asset=([a-f0-9-]{36})$/i',$argument,$match)===1)$onlyAsset=strtolower($match[1]);
}

try{
    mg_storage_assert_ready(false,true);
}catch(Throwable $error){
    fwrite(STDERR,"Persistent media storage is not ready: {$error->getMessage()}\n");
    fwrite(STDERR,"Run: php scripts/check_media_storage.php --initialize\n");
    exit(1);
}

$pdo=mg_db();
$where="a.storage_provider='local' AND a.status='ready'
        AND JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json,'$.source'))='social_feed'";
$params=[];
if($onlyAsset!==''){$where.=' AND a.public_id=?';$params[]=$onlyAsset;}
$stmt=$pdo->prepare(
    "SELECT a.id,a.public_id,a.owner_user_id,a.asset_type,a.storage_key,a.byte_size,a.checksum_sha256
     FROM catalog_assets a
     WHERE {$where}
     ORDER BY a.id
     LIMIT {$limit}"
);
$stmt->execute($params);
$assets=$stmt->fetchAll(PDO::FETCH_ASSOC);

$postLookup=$pdo->prepare(
    "SELECT id,public_id,media_json FROM feed_posts
     WHERE media_json LIKE ? OR media_json LIKE ?
     FOR UPDATE"
);
$postUpdate=$pdo->prepare('UPDATE feed_posts SET media_json=?,updated_at=NOW() WHERE id=?');
$linkAsset=$pdo->prepare(
    "INSERT INTO feed_post_assets
     (feed_post_id,asset_id,role,sort_order,alt_text,caption,created_at,updated_at)
     VALUES (?,?,?,?,?,?,NOW(),NOW())
     ON DUPLICATE KEY UPDATE role=VALUES(role),sort_order=VALUES(sort_order),alt_text=VALUES(alt_text),caption=VALUES(caption),updated_at=NOW()"
);
$assetUpdate=$pdo->prepare(
    "UPDATE catalog_assets
     SET storage_provider='persistent_local',storage_key=?,
         metadata_json=JSON_SET(COALESCE(metadata_json,JSON_OBJECT()),
           '$.source','social_feed','$.storage_class','persistent','$.storage_migrated_at',?,
           '$.legacy_storage_provider','local','$.legacy_storage_key',?),
         updated_at=NOW()
     WHERE id=? AND storage_provider='local'"
);
$markLegacyDeleted=$pdo->prepare(
    "UPDATE catalog_assets
     SET metadata_json=JSON_SET(COALESCE(metadata_json,JSON_OBJECT()),'$.legacy_source_deleted_at',?),updated_at=NOW()
     WHERE id=?"
);

$migrated=0;$failed=0;$missing=0;$postsUpdated=0;$bytes=0;$sourcesDeleted=0;
foreach($assets as $asset){
    $publicId=(string)$asset['public_id'];
    $oldKey=(string)$asset['storage_key'];
    if(preg_match('#^uploads/feed/[A-Za-z0-9/_-]+\.[A-Za-z0-9]+$#',$oldKey)!==1){
        fwrite(STDERR,"Skipping {$publicId}: unexpected legacy key {$oldKey}\n");
        $failed++;
        continue;
    }
    $newKey=mg_storage_normalize_key(substr($oldKey,strlen('uploads/')));
    $oldUrl='/'.ltrim($oldKey,'/');
    $newUrl=mg_storage_asset_public_url($publicId);
    try{
        $source=mg_storage_resolve_asset_path('local',$oldKey);
    }catch(Throwable $error){
        fwrite(STDERR,"Skipping {$publicId}: {$error->getMessage()}\n");
        $failed++;
        continue;
    }
    if(!is_file($source)||!is_readable($source)){
        fwrite(STDERR,"Missing source for {$publicId}: {$oldKey}\n");
        $missing++;
        continue;
    }

    if($dryRun){
        echo "Would migrate {$publicId}: {$oldKey} -> {$newKey}\n";
        $migrated++;
        $bytes+=(int)($asset['byte_size']??0);
        continue;
    }

    try{
        $target=mg_storage_copy_file($source,$newKey);
        $targetHash=hash_file('sha256',$target);
        $sourceHash=hash_file('sha256',$source);
        if(!is_string($targetHash)||!is_string($sourceHash)||!hash_equals($sourceHash,$targetHash)){
            throw new RuntimeException('Persistent copy checksum verification failed.');
        }
        $recordedHash=trim((string)($asset['checksum_sha256']??''));
        if($recordedHash!==''&&!hash_equals(strtolower($recordedHash),strtolower($targetHash))){
            throw new RuntimeException('Persistent copy does not match the recorded checksum.');
        }

        $pdo->beginTransaction();
        $postLookup->execute(['%'.$oldUrl.'%','%'.$publicId.'%']);
        foreach($postLookup->fetchAll(PDO::FETCH_ASSOC) as $post){
            $media=json_decode((string)$post['media_json'],true);
            if(!is_array($media))continue;
            $changed=false;
            foreach($media as $index=>&$item){
                if(!is_array($item))continue;
                $itemUrl=(string)($item['url']??'');
                $itemAsset=strtolower(trim((string)($item['asset_id']??'')));
                if($itemUrl!==$oldUrl&&$itemAsset!==$publicId)continue;
                $item['url']=$newUrl;
                $item['asset_id']=$publicId;
                $type=strtolower(trim((string)($item['type']??$asset['asset_type'])));
                if(!in_array($type,['image','audio','video'],true))$type=(string)$asset['asset_type'];
                $item['type']=$type;
                $role=$index===0?'primary':($type==='audio'?'audio':($type==='video'?'video':'gallery'));
                $linkAsset->execute([
                    (int)$post['id'],(int)$asset['id'],$role,$index,
                    isset($item['alt'])?mb_substr(trim((string)$item['alt']),0,240):null,
                    isset($item['caption'])?mb_substr(trim((string)$item['caption']),0,500):null,
                ]);
                $changed=true;
            }
            unset($item);
            if($changed){
                $postUpdate->execute([json_encode($media,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),(int)$post['id']]);
                $postsUpdated++;
            }
        }
        $assetUpdate->execute([$newKey,gmdate('c'),$oldKey,(int)$asset['id']]);
        if($assetUpdate->rowCount()!==1)throw new RuntimeException('Asset storage metadata was not updated.');
        $pdo->commit();

        if($deleteSource&&is_file($source)){
            if(@unlink($source)){
                $sourcesDeleted++;
                $markLegacyDeleted->execute([gmdate('c'),(int)$asset['id']]);
            }else fwrite(STDERR,"Migrated {$publicId}, but could not remove legacy source {$oldKey}\n");
        }
        echo "Migrated {$publicId}: {$oldKey} -> {$newKey}\n";
        $migrated++;
        $bytes+=(int)($asset['byte_size']??0);
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        fwrite(STDERR,"Failed {$publicId}: {$error->getMessage()}\n");
        $failed++;
    }
}

if($deleteSource&&!$dryRun){
    $cleanupWhere="a.storage_provider='persistent_local' AND a.status='ready'
        AND JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json,'$.source'))='social_feed'
        AND JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json,'$.legacy_storage_provider'))='local'
        AND JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json,'$.legacy_storage_key')) IS NOT NULL
        AND JSON_EXTRACT(a.metadata_json,'$.legacy_source_deleted_at') IS NULL";
    $cleanupParams=[];
    if($onlyAsset!==''){$cleanupWhere.=' AND a.public_id=?';$cleanupParams[]=$onlyAsset;}
    $cleanup=$pdo->prepare(
        "SELECT a.id,a.public_id,JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json,'$.legacy_storage_key')) legacy_key
         FROM catalog_assets a WHERE {$cleanupWhere} ORDER BY a.id LIMIT {$limit}"
    );
    $cleanup->execute($cleanupParams);
    foreach($cleanup->fetchAll(PDO::FETCH_ASSOC) as $asset){
        $oldKey=(string)$asset['legacy_key'];
        try{$source=mg_storage_resolve_asset_path('local',$oldKey);}
        catch(Throwable $error){$failed++;continue;}
        if(is_file($source)&&!@unlink($source)){
            fwrite(STDERR,"Unable to remove legacy source for {$asset['public_id']}: {$oldKey}\n");
            $failed++;
            continue;
        }
        $markLegacyDeleted->execute([gmdate('c'),(int)$asset['id']]);
        $sourcesDeleted++;
    }
}

$mode=$dryRun?'Dry run':'Migration';
echo "{$mode} complete: {$migrated} asset(s), {$postsUpdated} post(s) updated, {$bytes} byte(s), {$sourcesDeleted} source file(s) removed, {$missing} missing, {$failed} failed.\n";
exit($failed>0?1:0);
