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
$root=dirname(__DIR__);
$referenced=[];
$postStmt=$pdo->query("SELECT media_json FROM feed_posts WHERE media_json IS NOT NULL AND media_json<>'[]'");
while($row=$postStmt->fetch(PDO::FETCH_ASSOC)){
    $media=json_decode((string)$row['media_json'],true);
    if(!is_array($media))continue;
    foreach($media as $item){
        if(!is_array($item))continue;
        $url=(string)($item['url']??'');
        if(str_starts_with($url,'/uploads/feed/'))$referenced[ltrim($url,'/')]=true;
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
    $key=(string)$asset['storage_key'];
    if(isset($referenced[$key])){$skipped++;continue;}
    if((string)$asset['storage_provider']!=='local'||preg_match('#^uploads/feed/[A-Za-z0-9/_-]+\.[A-Za-z0-9]+$#',$key)!==1){
        $skipped++;
        continue;
    }
    $path=$root.'/'.$key;
    if($dryRun){
        echo "Would archive {$asset['public_id']} {$key}\n";
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
        fwrite(STDERR,"Unable to archive {$asset['public_id']}: {$error->getMessage()}\n");
        $skipped++;
        continue;
    }
    if(is_file($path)){
        if(!@unlink($path))fwrite(STDERR,"Unable to remove {$key}\n");
    }else{
        $missing++;
    }
    $cleaned++;
    $bytes+=(int)($asset['byte_size']??0);
}

$mode=$dryRun?'Dry run':'Cleanup';
echo "{$mode} complete: {$cleaned} asset(s), {$bytes} byte(s), {$skipped} skipped, {$missing} file(s) already missing.\n";
