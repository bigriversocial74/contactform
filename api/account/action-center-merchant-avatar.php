<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function mg_action_center_avatar_ids(string $raw): array
{
    $ids=[];
    foreach(explode(',',$raw) as $id){
        $id=trim($id);
        if($id===''||strlen($id)>190)continue;
        $ids[$id]=$id;
        if(count($ids)>=80)break;
    }
    return array_values($ids);
}

mg_require_method('GET');
$user=mg_require_api_user();
$ids=mg_action_center_avatar_ids((string)($_GET['ids']??$_GET['id']??''));
if($ids===[])mg_ok(['items'=>[]]);

$pdo=mg_db();
$placeholders=implode(',',array_fill(0,count($ids),'?'));
$stmt=$pdo->prepare(
    "SELECT ac.public_id action_item_id,
            COALESCE(ms.display_name, merchant.display_name, merchant.full_name, sender.display_name, sender.full_name, 'Merchant') merchant_name,
            logo.public_id merchant_logo_asset_id
     FROM microgift_inbox_items ac
     INNER JOIN microgift_instances i ON i.id=ac.instance_id
     LEFT JOIN users merchant ON merchant.id=i.issuer_user_id
     LEFT JOIN users sender ON sender.id=ac.sender_user_id
     LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=i.issuer_user_id AND ms.status IN ('published','draft')
     LEFT JOIN catalog_assets logo ON logo.id=ms.logo_asset_id AND logo.status='ready'
     WHERE ac.user_id=? AND ac.public_id IN ({$placeholders}) AND ac.archived_at IS NULL"
);
$stmt->execute(array_merge([(int)$user['id']],$ids));
$items=[];
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
    $logoId=(string)($row['merchant_logo_asset_id']??'');
    $items[(string)$row['action_item_id']]=[
        'merchant_name'=>(string)($row['merchant_name']??'Merchant'),
        'merchant_avatar_url'=>$logoId!==''?('/api/public/media.php?asset='.rawurlencode($logoId)):'',
    ];
}
mg_ok(['items'=>$items]);
