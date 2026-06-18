<?php
declare(strict_types=1);

function mg_social_media_asset_url(array $asset): ?string
{
    $publicId=strtolower(trim((string)($asset['public_id']??$asset['asset_public_id']??'')));
    $provider=strtolower(trim((string)($asset['storage_provider']??'')));
    $key=trim((string)($asset['storage_key']??''));
    if($publicId!==''&&preg_match('/^[a-f0-9-]{36}$/',$publicId)===1&&in_array($provider,['persistent_local','private_local','local'],true)){
        return mg_storage_asset_public_url($publicId);
    }
    if(preg_match('#^https://#i',$key)===1&&filter_var($key,FILTER_VALIDATE_URL))return $key;
    return null;
}

function mg_social_media_legacy_url(array $asset): ?string
{
    $provider=strtolower(trim((string)($asset['storage_provider']??'')));
    $key=trim((string)($asset['storage_key']??''));
    if($provider!=='local'||$key===''||str_contains($key,'..')||str_contains($key,"\\"))return null;
    return '/'.ltrim($key,'/');
}

function mg_social_media_source_is_feed(array $asset): bool
{
    $metadata=[];
    if(!empty($asset['metadata_json'])){
        $decoded=json_decode((string)$asset['metadata_json'],true);
        if(is_array($decoded))$metadata=$decoded;
    }
    return (string)($metadata['source']??'')==='social_feed';
}

function mg_social_media_prepare(PDO $pdo,int $userId,mixed $raw): array
{
    if(is_string($raw)){
        $decoded=json_decode($raw,true);
        $raw=is_array($decoded)?$decoded:preg_split('/\r?\n/',$raw);
    }
    if(!is_array($raw))return ['media'=>[],'bindings'=>[]];
    $items=array_is_list($raw)?$raw:[$raw];
    $prepared=[];$assetIds=[];$legacyKeys=[];
    foreach($items as $item){
        if(count($prepared)>=MG_SOCIAL_POST_MEDIA_MAX)break;
        if(is_string($item))$item=['url'=>$item];
        if(!is_array($item))continue;
        $url=mg_publishing_safe_url($item['url']??null,true);
        if($url===null)continue;
        $type=strtolower(trim((string)($item['type']??'link')));
        if(!in_array($type,['image','audio','video','link'],true))$type='link';
        $assetPublic=strtolower(trim((string)($item['asset_id']??'')));
        if($assetPublic!==''&&preg_match('/^[a-f0-9-]{36}$/',$assetPublic)!==1)throw new InvalidArgumentException('Invalid uploaded media reference.');
        if($assetPublic!=='')$assetIds[$assetPublic]=$assetPublic;
        elseif(str_starts_with($url,'/uploads/feed/'))$legacyKeys[ltrim($url,'/')]=ltrim($url,'/');
        $prepared[]=[
            'url'=>$url,
            'type'=>$type,
            'alt'=>isset($item['alt'])?mb_substr(trim((string)$item['alt']),0,240):null,
            'caption'=>isset($item['caption'])?mb_substr(trim((string)$item['caption']),0,500):null,
            'asset_id'=>$assetPublic!==''?$assetPublic:null,
        ];
    }

    $assets=[];$assetsByKey=[];
    if($assetIds!==[]){
        $values=array_values($assetIds);
        $stmt=$pdo->prepare(
            'SELECT id,public_id,owner_user_id,asset_type,storage_provider,storage_key,status,metadata_json
             FROM catalog_assets WHERE public_id IN ('.implode(',',array_fill(0,count($values),'?')).') FOR UPDATE'
        );
        $stmt->execute($values);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $asset)$assets[(string)$asset['public_id']]=$asset;
    }
    if($legacyKeys!==[]){
        $values=array_values($legacyKeys);
        $stmt=$pdo->prepare(
            'SELECT id,public_id,owner_user_id,asset_type,storage_provider,storage_key,status,metadata_json
             FROM catalog_assets
             WHERE owner_user_id=? AND storage_provider=\'local\' AND storage_key IN ('.implode(',',array_fill(0,count($values),'?')).')
             FOR UPDATE'
        );
        $stmt->execute(array_merge([$userId],$values));
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $asset){
            $assets[(string)$asset['public_id']]=$asset;
            $assetsByKey[(string)$asset['storage_key']]=$asset;
        }
    }

    $seen=[];$bindings=[];
    foreach($prepared as &$item){
        $assetPublic=(string)($item['asset_id']??'');
        if($assetPublic===''&&str_starts_with((string)$item['url'],'/uploads/feed/')){
            $legacy=$assetsByKey[ltrim((string)$item['url'],'/')]??null;
            if($legacy)$assetPublic=(string)$legacy['public_id'];
            $item['asset_id']=$assetPublic!==''?$assetPublic:null;
        }
        if($assetPublic==='')continue;
        if(isset($seen[$assetPublic]))throw new InvalidArgumentException('The same uploaded media cannot be attached twice.');
        $seen[$assetPublic]=true;
        $asset=$assets[$assetPublic]??null;
        if(!$asset||(int)$asset['owner_user_id']!==$userId||(string)$asset['status']!=='ready'||!mg_social_media_source_is_feed($asset)){
            throw new RuntimeException('Uploaded media is not available to this author.');
        }
        $authoritativeUrl=mg_social_media_asset_url($asset);
        if($authoritativeUrl===null)throw new RuntimeException('Uploaded media storage is unavailable.');
        $assetType=(string)$asset['asset_type'];
        if(!in_array($assetType,['image','audio','video'],true))throw new RuntimeException('Uploaded media type is not supported for feed posts.');
        $acceptedUrls=[$authoritativeUrl];
        $legacyUrl=mg_social_media_legacy_url($asset);
        if($legacyUrl!==null)$acceptedUrls[]=$legacyUrl;
        if(!in_array((string)$item['url'],$acceptedUrls,true))throw new RuntimeException('Uploaded media reference does not match its stored asset.');
        $item['url']=$authoritativeUrl;
        $item['type']=$assetType;
        $bindings[$assetPublic]=['id'=>(int)$asset['id'],'public_id'=>$assetPublic,'type'=>$assetType];
    }
    unset($item);
    foreach($prepared as $item){
        if(str_starts_with((string)$item['url'],'/uploads/feed/')&&empty($item['asset_id'])){
            throw new RuntimeException('Uploaded media is no longer available. Please upload it again.');
        }
    }
    return ['media'=>$prepared,'bindings'=>$bindings];
}

function mg_social_media_sync(PDO $pdo,int $postId,string $postPublicId,int $userId,array $media,array $bindings): void
{
    $existingStmt=$pdo->prepare('SELECT asset_id FROM feed_post_assets WHERE feed_post_id=? FOR UPDATE');
    $existingStmt->execute([$postId]);
    $existing=array_map('intval',$existingStmt->fetchAll(PDO::FETCH_COLUMN));
    $newIds=array_values(array_map(static fn(array $binding): int=>(int)$binding['id'],$bindings));
    $removed=array_values(array_diff($existing,$newIds));

    $pdo->prepare('DELETE FROM feed_post_assets WHERE feed_post_id=?')->execute([$postId]);
    $insert=$pdo->prepare(
        'INSERT INTO feed_post_assets
         (feed_post_id,asset_id,role,sort_order,alt_text,caption,created_at,updated_at)
         VALUES (?,?,?,?,?,?,NOW(),NOW())'
    );
    foreach($media as $index=>$item){
        $assetPublic=(string)($item['asset_id']??'');
        if($assetPublic===''||!isset($bindings[$assetPublic]))continue;
        $type=(string)$bindings[$assetPublic]['type'];
        $role=$index===0?'primary':($type==='audio'?'audio':($type==='video'?'video':'gallery'));
        $insert->execute([$postId,(int)$bindings[$assetPublic]['id'],$role,$index,$item['alt']??null,$item['caption']??null]);
    }

    if($newIds!==[]){
        $params=array_merge([$postPublicId,$userId],$newIds);
        $pdo->prepare(
            "UPDATE catalog_assets
             SET metadata_json=JSON_SET(COALESCE(metadata_json,JSON_OBJECT()),
                 '$.source','social_feed','$.feed_state','attached','$.feed_post_id',?),updated_at=NOW()
             WHERE owner_user_id=? AND id IN (".implode(',',array_fill(0,count($newIds),'?')).')'
        )->execute($params);
    }
    if($removed!==[]){
        $params=array_merge([$userId],$removed);
        $pdo->prepare(
            "UPDATE catalog_assets
             SET metadata_json=JSON_REMOVE(JSON_SET(COALESCE(metadata_json,JSON_OBJECT()),
                 '$.source','social_feed','$.feed_state','detached'),'$.feed_post_id'),updated_at=NOW()
             WHERE owner_user_id=? AND id IN (".implode(',',array_fill(0,count($removed),'?')).')'
        )->execute($params);
    }
}

function mg_social_media_enrich_owner_posts(PDO $pdo,int $userId,array $collection): array
{
    $items=is_array($collection['items']??null)?$collection['items']:[];
    if($items===[])return $collection;
    $postIds=[];$storageKeys=[];
    foreach($items as $post){
        $postId=strtolower(trim((string)($post['id']??'')));
        if($postId!=='')$postIds[$postId]=$postId;
        foreach(($post['media']??[]) as $media){
            $url=(string)($media['url']??'');
            if(str_starts_with($url,'/uploads/feed/'))$storageKeys[ltrim($url,'/')]=ltrim($url,'/');
        }
    }
    $byPostUrl=[];$byStorageKey=[];
    if($postIds!==[]){
        $values=array_values($postIds);
        $stmt=$pdo->prepare(
            'SELECT fp.public_id post_public_id,a.public_id asset_public_id,a.storage_provider,a.storage_key
             FROM feed_posts fp
             INNER JOIN feed_post_assets fpa ON fpa.feed_post_id=fp.id
             INNER JOIN catalog_assets a ON a.id=fpa.asset_id
             WHERE fp.created_by_user_id=? AND fp.public_id IN ('.implode(',',array_fill(0,count($values),'?')).')'
        );
        $stmt->execute(array_merge([$userId],$values));
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $canonical=mg_social_media_asset_url($row);
            if($canonical===null)continue;
            $record=['asset_id'=>(string)$row['asset_public_id'],'url'=>$canonical];
            $byPostUrl[(string)$row['post_public_id']][$canonical]=$record;
            $legacy=mg_social_media_legacy_url($row);
            if($legacy!==null)$byPostUrl[(string)$row['post_public_id']][$legacy]=$record;
        }
    }
    if($storageKeys!==[]){
        $values=array_values($storageKeys);
        $stmt=$pdo->prepare(
            'SELECT public_id,storage_provider,storage_key FROM catalog_assets
             WHERE owner_user_id=? AND storage_provider=\'local\' AND status=\'ready\'
               AND storage_key IN ('.implode(',',array_fill(0,count($values),'?')).')'
        );
        $stmt->execute(array_merge([$userId],$values));
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $canonical=mg_social_media_asset_url($row);
            if($canonical!==null)$byStorageKey[(string)$row['storage_key']]=['asset_id'=>(string)$row['public_id'],'url'=>$canonical];
        }
    }
    foreach($items as &$post){
        $postId=(string)($post['id']??'');
        foreach($post['media'] as &$media){
            $url=(string)($media['url']??'');
            $record=$byPostUrl[$postId][$url]??null;
            if($record===null&&str_starts_with($url,'/uploads/feed/'))$record=$byStorageKey[ltrim($url,'/')]??null;
            if(is_array($record)){
                $media['asset_id']=$record['asset_id'];
                $media['url']=$record['url'];
            }
        }
        unset($media);
    }
    unset($post);
    $collection['items']=$items;
    return $collection;
}
