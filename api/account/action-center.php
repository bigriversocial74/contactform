<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once __DIR__ . '/_action_center_wallet.php';
require_once __DIR__ . '/_action_center_product_media.php';

function mg_action_center_counts_plus_wallet(PDO $pdo,int $userId,string $email): array
{
    return mg_ac_wallet_counts_merge(
        mg_action_center_counts($pdo,$userId),
        mg_ac_wallet_counts($pdo,$userId,$email)
    );
}

function mg_action_center_page_plus_wallet(PDO $pdo,int $userId,string $email,string $folder,int $limit=50,string $search='',?array $cursor=null): array
{
    return mg_ac_wallet_page_merge(
        $pdo,
        $userId,
        $email,
        $folder,
        mg_action_center_page($pdo,$userId,$folder,$limit,$search,$cursor),
        $limit,
        $search,
        $cursor
    );
}

function mg_action_center_apply_business_names(PDO $pdo,array $items): array
{
    $merchantIds=[];
    foreach($items as $item){
        if(!is_array($item))continue;
        $merchantId=(int)($item['merchant_user_id']??0);
        if($merchantId<1&&($item['source_system']??'')==='campaigns')$merchantId=(int)($item['sender_id']??0);
        if($merchantId>0)$merchantIds[$merchantId]=true;
    }

    $businessNames=[];
    if($merchantIds!==[]){
        try{
            $ids=array_keys($merchantIds);
            $placeholders=implode(',',array_fill(0,count($ids),'?'));
            $stmt=$pdo->prepare("SELECT merchant_user_id,display_name,status,id FROM merchant_storefronts WHERE merchant_user_id IN ({$placeholders}) AND status IN ('published','draft') ORDER BY CASE status WHEN 'published' THEN 0 ELSE 1 END,id ASC");
            $stmt->execute($ids);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
                $merchantId=(int)($row['merchant_user_id']??0);
                $name=trim((string)($row['display_name']??''));
                if($merchantId>0&&$name!==''&&!isset($businessNames[$merchantId]))$businessNames[$merchantId]=$name;
            }
        }catch(Throwable){
            $businessNames=[];
        }
    }

    foreach($items as &$item){
        if(!is_array($item))continue;
        $merchantId=(int)($item['merchant_user_id']??0);
        if($merchantId<1&&($item['source_system']??'')==='campaigns')$merchantId=(int)($item['sender_id']??0);
        $business=trim((string)($businessNames[$merchantId]??$item['business_name']??$item['merchant_name']??''));
        if($business==='')$business='Microgifter';
        $item['business_name']=$business;
        $item['merchant_name']=$business;
    }
    unset($item);

    return $items;
}

mg_require_method('GET');
$user=mg_require_api_user();
$userId=(int)$user['id'];
$userEmail=mg_ac_wallet_user_email($user);
$folder=mg_action_center_folder(trim((string)($_GET['folder']??'inbox')));
$limit=mg_action_center_limit($_GET['limit']??50);
$search=mg_action_center_search($_GET['q']??'');
try{
    $cursor=mg_action_center_decode_cursor(isset($_GET['cursor'])?(string)$_GET['cursor']:null);
}catch(InvalidArgumentException $e){
    mg_fail($e->getMessage(),422);
}
$pdo=mg_db();
$page=mg_action_center_page_plus_wallet($pdo,$userId,$userEmail,$folder,$limit,$search,$cursor);
$page['items']=mg_action_center_apply_business_names($pdo,is_array($page['items']??null)?$page['items']:[]);
$page['items']=mg_action_center_attach_product_media($pdo,$userId,$page['items']);

mg_ok([
    'folder'=>$folder,
    'query'=>$search,
    'counts'=>mg_action_center_counts_plus_wallet($pdo,$userId,$userEmail),
    'items'=>$page['items'],
    'page'=>$page['page'],
]);
