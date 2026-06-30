<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';

function mg_action_center_media_ids(string $raw): array
{
    $ids=[];
    foreach(explode(',', $raw) as $id){
        $id=trim($id);
        if($id===''||strlen($id)>190)continue;
        $ids[$id]=$id;
        if(count($ids)>=80)break;
    }
    return array_values($ids);
}

function mg_action_center_asset_payload(array $asset): array
{
    $asset['role']=(string)($asset['role']??'other');
    $asset['asset_type']=(string)($asset['asset_type']??'other');
    $asset['mime_type']=(string)($asset['mime_type']??'');
    $asset['sort_order']=(int)($asset['sort_order']??0);
    $asset['url']='/api/public/media.php?asset='.rawurlencode((string)$asset['asset_id']);
    $asset['width_px']=isset($asset['width_px'])?(int)$asset['width_px']:null;
    $asset['height_px']=isset($asset['height_px'])?(int)$asset['height_px']:null;
    $asset['duration_ms']=isset($asset['duration_ms'])?(int)$asset['duration_ms']:null;
    return $asset;
}

function mg_action_center_cover_url(array $assets): string
{
    $byRole=[];
    foreach($assets as $asset){
        $role=(string)($asset['role']??'');
        if($role!==''&&!isset($byRole[$role]))$byRole[$role]=$asset;
    }
    foreach(['cover','thumbnail','inside_cover','gallery','carousel','back'] as $role){
        if(!empty($byRole[$role]['url'])){
            $type=(string)($byRole[$role]['asset_type']??'');
            $mime=(string)($byRole[$role]['mime_type']??'');
            if($type==='image'||str_starts_with($mime,'image/'))return (string)$byRole[$role]['url'];
        }
    }
    foreach($assets as $asset){
        $type=(string)($asset['asset_type']??'');
        $mime=(string)($asset['mime_type']??'');
        if($type==='image'||str_starts_with($mime,'image/'))return (string)$asset['url'];
    }
    return '';
}

mg_require_method('GET');
$user=mg_require_api_user();
$ids=mg_action_center_media_ids((string)($_GET['ids']??$_GET['id']??''));
if($ids===[])mg_ok(['items'=>[]]);

$pdo=mg_db();
$placeholders=implode(',',array_fill(0,count($ids),'?'));
$stmt=$pdo->prepare(
    "SELECT ac.public_id action_item_id,i.product_version_id,i.issuer_user_id,
            ms.display_name merchant_display_name,logo.public_id merchant_logo_asset_id
     FROM microgift_inbox_items ac
     INNER JOIN microgift_instances i ON i.id=ac.instance_id
     LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=i.issuer_user_id AND ms.status='published'
     LEFT JOIN catalog_assets logo ON logo.id=ms.logo_asset_id AND logo.status='ready'
     WHERE ac.user_id=? AND ac.public_id IN ({$placeholders}) AND ac.archived_at IS NULL"
);
$stmt->execute(array_merge([(int)$user['id']],$ids));
$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
if(!$rows)mg_ok(['items'=>[]]);

$versionIds=[];
$itemVersions=[];
$itemMerchants=[];
foreach($rows as $row){
    $actionId=(string)$row['action_item_id'];
    $versionId=(int)($row['product_version_id']??0);
    $itemVersions[$actionId]=$versionId;
    $logoId=(string)($row['merchant_logo_asset_id']??'');
    $itemMerchants[$actionId]=[
        'merchant_name'=>(string)($row['merchant_display_name']??''),
        'merchant_avatar_url'=>$logoId!==''?('/api/public/media.php?asset='.rawurlencode($logoId)):'',
    ];
    if($versionId>0)$versionIds[$versionId]=$versionId;
}

$assetsByVersion=[];
if($versionIds!==[]){
    $versionPlaceholders=implode(',',array_fill(0,count($versionIds),'?'));
    $assetsStmt=$pdo->prepare(
        "SELECT cpva.product_version_id,cpva.role,cpva.sort_order,
                ca.public_id asset_id,ca.asset_type,ca.mime_type,ca.original_filename,
                ca.width_px,ca.height_px,ca.duration_ms
         FROM catalog_product_version_assets cpva
         INNER JOIN catalog_assets ca ON ca.id=cpva.asset_id AND ca.status='ready'
         WHERE cpva.product_version_id IN ({$versionPlaceholders})
         ORDER BY cpva.product_version_id,
                  FIELD(cpva.role,'cover','thumbnail','inside_cover','gallery','carousel','audio','download','back','other'),
                  cpva.sort_order,cpva.id"
    );
    $assetsStmt->execute(array_values($versionIds));
    foreach($assetsStmt->fetchAll(PDO::FETCH_ASSOC) as $asset){
        $versionId=(int)$asset['product_version_id'];
        unset($asset['product_version_id']);
        $assetsByVersion[$versionId][] = mg_action_center_asset_payload($asset);
    }
}

$items=[];
foreach($itemVersions as $actionId=>$versionId){
    $assets=$assetsByVersion[$versionId]??[];
    $byRole=[];
    foreach($assets as $asset){
        $role=(string)($asset['role']??'');
        if($role!==''&&!isset($byRole[$role]))$byRole[$role]=$asset;
    }
    $items[$actionId]=[
        'media_assets'=>$assets,
        'media_by_role'=>$byRole,
        'cover_url'=>mg_action_center_cover_url($assets),
        'media_count'=>count($assets),
        'merchant_name'=>$itemMerchants[$actionId]['merchant_name']??'',
        'merchant_avatar_url'=>$itemMerchants[$actionId]['merchant_avatar_url']??'',
    ];
}

mg_ok(['items'=>$items]);
