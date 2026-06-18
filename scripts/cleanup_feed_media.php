<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__).'/api/bootstrap.php';

$hours=24;
$dryRun=false;
foreach(array_slice($argv,1) as $argument){
    if($argument==='--dry-run')$dryRun=true;
    elseif(preg_match('/^--hours=(\d+)$/',$argument,$match)===1)$hours=max(1,min(720,(int)$match[1]));
}

$pdo=mg_db();
$referencedKeys=[];
$referencedAssets=[];
$postStmt=$pdo->query("SELECT media_json FROM feed_posts WHERE media_json IS NOT NULL AND media_json<>'[]'");
while($row=$postStmt->fetch(PDO::FETCH_ASSOC)){
    $media=json_decode((string)$row['media_json'],true);
    if(!is_array($media))continue;
    foreach($media as $item){
        if(!is_array($item))continue;
        $assetId=strtolower(trim((string)($item['asset_id']??'')));
        if(preg_match('/^[a-f0-9-]{36}$/',$assetId)===1)$referencedAssets[$assetId]=true;
        $url=(string)($item['url']??'');
        if(str_starts_with($url,'/uploads/feed/'))$referencedKeys[ltrim($url,'/')]=true;
        $query=parse_url($url,PHP_URL_QUERY);
        if(is_string($query)){
            parse_str($query,$params);
            $urlAsset=strtolower(trim((string)($params['asset']??'')));
            if(preg_match('/^[a-f0-9-]{36}$/',$urlAsset)===1)$referencedAssets[$urlAsset]=true;
        }
    }
}

$sql="SELECT a.id,a.public_id,a.storage_provider,a.storage_key,a.byte_size,a.updated_at
      FROM catalog_assets a
      LEFT JOIN feed_post_assets fpa ON fpa.asset_id=a.id
      WHERE a.status='ready' AND fpa.id IS NULL
        AND JSON_UNQUOTE(JSON_EXTRACT(a.metadata_json,'$.source'))='social_feed'
        AND a.updated_at<DATE_SUB(NOW(),INTERVAL {$hours} HOUR)
      ORDER BY a.updated_at,a.id
      LIMIT 1000";
$candidates=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$cleaned=0;$skipped=0;$bytes=0;$missing=0;

$update=$pdo->prepare(
    "UPDATE catalog_assets
     SET status='archived',
         metadata_json=JSON_SET(COALESCE(metadata_json,JSON_OBJECT()),
           '$.source','social_feed','$.feed_state','cleaned','$.cleaned_at',?),
         updated_at=NOW()
     WHERE id=? AND status='ready'"
);

foreach($candidates as $asset){
    $provider=(string)$asset['storage_provider'];
    $key=(string)$asset['storage_key'];
    $publicId=(string)$asset['public_id'];
    if(isset($referencedAssets[$publicId])||isset($referencedKeys[$key])){$skipped++;continue;}
    $validKey=($provider==='persistent_local'&&preg_match('#^feed/[A-Za-z0-9/_-]+\.[A-Za-z0-9]+$#',$key)===1)
        ||($provider==='local'&&preg_match('#^uploads/feed/[A-Za-z0-9/_-]+\.[A-Za-z0-9]+$#',$key)===1);
    if(!$validKey){$skipped++;continue;}
    $exists=false;
    try{$exists=is_file(mg_storage_resolve_asset_path($provider,$key));}
    catch(Throwable $error){$skipped++;continue;}
    if($dryRun){
        echo "Would archive {$publicId} {$provider}:{$key}\n";
        $cleaned++;
        $bytes+=(int)($asset['byte_size']??0);
        continue;
    }
    $pdo->beginTransaction();
    try{
        $update->execute([gmdate('c'),(int)$asset['id']]);
        if($update->rowCount()!==1){$pdo->rollBack();$skipped++;continue;}
        $pdo->commit();
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        fwrite(STDERR,"Unable to archive {$publicId}: {$error->getMessage()}\n");
        $skipped++;
        continue;
    }
    if($exists&&!mg_storage_delete_asset_file($provider,$key))fwrite(STDERR,"Unable to remove {$provider}:{$key}\n");
    elseif(!$exists)$missing++;
    $cleaned++;
    $bytes+=(int)($asset['byte_size']??0);
}

$mode=$dryRun?'Dry run':'Cleanup';
echo "{$mode} complete: {$cleaned} asset(s), {$bytes} byte(s), {$skipped} skipped, {$missing} file(s) already missing.\n";
