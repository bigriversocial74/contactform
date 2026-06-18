<?php
declare(strict_types=1);

function mg_social_media_asset_url(array $asset): ?string
{
    $provider=strtolower(trim((string)($asset['storage_provider']??'')));
    $key=trim((string)($asset['storage_key']??''));
    if($key===''||str_contains($key,'..')||str_contains($key,"\\"))return null;
    if($provider==='local')return '/'.ltrim($key,'/');
    if(preg_match('#^https://#i',$key)===1&&filter_var($key,FILTER_VALIDATE_URL))return $key;
    return null;
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
    $prepared=[];$assetIds=[];
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
        $prepared[]=[
            'url'=>$url,
            'type'=>$type,
            'alt'=>isset($item['alt'])?mb_substr(trim((string)$item['alt']),0,240):null,
            'caption'=>isset($item['caption'])?mb_substr(trim((string)$item['caption']),0,500):null,
            'asset_id'=>$assetPublic!==''?$assetPublic:null,
        ];
    }

    $assets=[];
    if($assetIds!==[]){
        $values=array_values($assetIds);
        $stmt=$pdo->prepare(
            'SELECT id,public_id,owner_user_id,asset_type,storage_provider,storage_key,status,metadata_json
             FROM catalog_assets
             WHERE public_id IN ('.implode(',',array_fill(0,count($values),'?')).')
             FOR UPDATE'
        );
        $stmt->execute($values);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $asset)$assets[(string)$asset['public_id']]=$asset;
    }

    $seen=[];$bindings=[];
    foreach($prepared as &$item){
        $assetPublic=(string)($item['asset_id']??'');
        if($assetPublic===''){
            if(str_starts_with((string)$item['url'],'/uploads/feed/'))throw new RuntimeException('Uploaded media is no longer available. Please upload it again.');
            continue;
        }
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
        if((string)$item['url']!==$authoritativeUrl)throw new RuntimeException('Uploaded media reference does not match its stored asset.');
        $item['url']=$authoritativeUrl;
        $item['type']=$assetType;
        $bindings[$assetPublic]=[
            'id'=>(int)$asset['id'],
            'public_id'=>$assetPublic,
            'type'=>$assetType,
        ];
    }
    unset($item);
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
        $insert->execute([
            $postId,(int)$bindings[$assetPublic]['id'],$role,$index,
            $item['alt']??null,$item['caption']??null,
        ]);
    }

    if($newIds!==[]){
        $params=array_merge([$postPublicId],$newIds);
        $pdo->prepare(
            "UPDATE catalog_assets
             SET metadata_json=JSON_SET(COALESCE(metadata_json,JSON_OBJECT()),
                 '$.source','social_feed','$.feed_state','attached','$.feed_post_id',?),updated_at=NOW()
             WHERE owner_user_id={$userId} AND id IN (".implode(',',array_fill(0,count($newIds),'?')).')'
        )->execute($params);
    }
    if($removed!==[]){
        $pdo->prepare(
            "UPDATE catalog_assets
             SET metadata_json=JSON_REMOVE(JSON_SET(COALESCE(metadata_json,JSON_OBJECT()),
                 '$.source','social_feed','$.feed_state','detached'),'$.feed_post_id'),updated_at=NOW()
             WHERE owner_user_id={$userId} AND id IN (".implode(',',array_fill(0,count($removed),'?')).')'
        )->execute($removed);
    }
}
