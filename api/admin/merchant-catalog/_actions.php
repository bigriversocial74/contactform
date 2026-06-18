<?php
declare(strict_types=1);

require_once __DIR__ . '/_detail.php';
require_once __DIR__ . '/_merchant_lifecycle.php';
require_once dirname(__DIR__,2) . '/catalog/_operations_lifecycle.php';

const MG_ADMIN_MC_ACTIONS=[
    'activate_workspace','review_workspace','suspend_workspace','restore_workspace','archive_workspace',
    'publish_storefront','unpublish_storefront','suspend_storefront','restore_storefront','archive_storefront',
    'publish_product','review_product','pause_product','restore_product','archive_product',
    'quarantine_asset','retry_asset','archive_asset',
];

function mg_admin_mc_action(mixed $value): string
{
    $action=strtolower(trim((string)$value));
    if(!in_array($action,MG_ADMIN_MC_ACTIONS,true))throw new MgAdminMerchantCatalogException('Invalid merchant catalog action.',422);
    return $action;
}

function mg_admin_mc_action_subject(string $action): string
{
    if(str_ends_with($action,'_workspace'))return 'workspace';
    if(str_ends_with($action,'_storefront'))return 'storefront';
    if(str_ends_with($action,'_product'))return 'product';
    return 'asset';
}

function mg_admin_mc_action_permission(string $subject): string
{
    return in_array($subject,['workspace','storefront'],true)?'admin.merchants.manage':'admin.catalog.manage';
}

function mg_admin_mc_cascade_workspace(PDO $pdo,array $result,string $action,int $actorId,string $reason): array
{
    $workspace=$result['workspace'];$changes=[];
    if(!in_array($action,['suspend_workspace','archive_workspace'],true))return $changes;
    $store=mg_admin_mc_one($pdo,'SELECT public_id,status FROM merchant_storefronts WHERE merchant_user_id=? LIMIT 1 FOR UPDATE',[(int)$workspace['merchant_user_id']]);
    if($store){
        $target=$action==='suspend_workspace'?'suspended':'archived';
        if((string)$store['status']!==$target){$pdo->prepare('UPDATE merchant_storefronts SET status=?,updated_at=NOW() WHERE public_id=?')->execute([$target,(string)$store['public_id']]);mg_admin_mc_event($pdo,'storefront',(string)$store['public_id'],$action,(string)$store['status'],$target,$actorId,$reason,['cascade_from'=>(string)$workspace['public_id']]);$changes[]=['type'=>'storefront','reference'=>(string)$store['public_id'],'status'=>$target];}
    }
    $products=mg_admin_mc_all($pdo,"SELECT public_id,status FROM catalog_products WHERE merchant_user_id=? AND status<>'archived' FOR UPDATE",[(int)$workspace['merchant_user_id']]);
    foreach($products as $product){$target=$action==='suspend_workspace'&&$product['status']==='published'?'paused':($action==='archive_workspace'?'archived':(string)$product['status']);if($target===(string)$product['status'])continue;$pdo->prepare('UPDATE catalog_products SET status=?,archived_at=CASE WHEN ?="archived" THEN NOW() ELSE archived_at END,updated_at=NOW() WHERE public_id=?')->execute([$target,$target,(string)$product['public_id']]);mg_admin_mc_event($pdo,'product',(string)$product['public_id'],$action,(string)$product['status'],$target,$actorId,$reason,['cascade_from'=>(string)$workspace['public_id']]);$changes[]=['type'=>'product','reference'=>(string)$product['public_id'],'status'=>$target];}
    return $changes;
}

function mg_admin_mc_cascade_asset(PDO $pdo,array $result,string $action,int $actorId,string $reason): array
{
    if($action!=='quarantine_asset')return [];
    $asset=$result['asset'];$changes=[];
    $products=mg_admin_mc_all($pdo,"SELECT DISTINCT p.public_id,p.status FROM catalog_product_version_assets pva INNER JOIN catalog_product_versions v ON v.id=pva.product_version_id INNER JOIN catalog_products p ON p.id=v.product_id WHERE pva.asset_id=? AND p.status='published' FOR UPDATE",[(int)$asset['id']]);
    foreach($products as $product){$pdo->prepare("UPDATE catalog_products SET status='paused',updated_at=NOW() WHERE public_id=?")->execute([(string)$product['public_id']]);mg_admin_mc_event($pdo,'product',(string)$product['public_id'],$action,'published','paused',$actorId,$reason,['cascade_from'=>(string)$asset['public_id']]);$changes[]=['type'=>'product','reference'=>(string)$product['public_id'],'status'=>'paused'];}
    $stores=mg_admin_mc_all($pdo,"SELECT public_id,status FROM merchant_storefronts WHERE (logo_asset_id=? OR cover_asset_id=?) AND status='published' FOR UPDATE",[(int)$asset['id'],(int)$asset['id']]);
    foreach($stores as $store){$pdo->prepare("UPDATE merchant_storefronts SET status='suspended',updated_at=NOW() WHERE public_id=?")->execute([(string)$store['public_id']]);mg_admin_mc_event($pdo,'storefront',(string)$store['public_id'],$action,'published','suspended',$actorId,$reason,['cascade_from'=>(string)$asset['public_id']]);$changes[]=['type'=>'storefront','reference'=>(string)$store['public_id'],'status'=>'suspended'];}
    return $changes;
}

function mg_admin_mc_execute(PDO $pdo,array $actor,array $input): array
{
    $action=mg_admin_mc_action($input['action']??null);$subject=mg_admin_mc_action_subject($action);$permission=mg_admin_mc_action_permission($subject);
    if(!mg_admin_mc_has($actor,$permission))throw new MgAdminMerchantCatalogException('Permission denied.',403);
    $providedType=mg_admin_mc_subject_type($input['subject_type']??$subject);if($providedType!==$subject)throw new MgAdminMerchantCatalogException('Action does not match the selected subject.',422);
    $reference=mg_admin_mc_reference($input['subject_reference']??null);$reason=mg_admin_mc_reason($input['reason']??null);$actorId=(int)$actor['id'];
    try{
        $result=match($subject){
            'workspace'=>mg_admin_mc_transition_workspace($pdo,$reference,$action),
            'storefront'=>mg_admin_mc_transition_storefront($pdo,$reference,$action,$actorId),
            'product'=>mg_catalog_operations_transition_product($pdo,$reference,$action),
            'asset'=>mg_catalog_operations_transition_asset($pdo,$reference,$action),
        };
    }catch(InvalidArgumentException $error){throw new MgAdminMerchantCatalogException($error->getMessage(),422);}catch(RuntimeException $error){throw new MgAdminMerchantCatalogException($error->getMessage(),409);}
    $from=(string)($result['from_status']??'');$to=(string)($result['to_status']??'');
    mg_admin_mc_event($pdo,$subject,$reference,$action,$from,$to,$actorId,$reason,['duplicate'=>(bool)($result['duplicate']??false)]);
    $cascades=[];
    if($subject==='workspace')$cascades=mg_admin_mc_cascade_workspace($pdo,$result,$action,$actorId,$reason);
    if($subject==='asset')$cascades=mg_admin_mc_cascade_asset($pdo,$result,$action,$actorId,$reason);
    return ['action'=>$action,'subject_type'=>$subject,'subject_reference'=>$reference,'from_status'=>$from,'to_status'=>$to,'duplicate'=>(bool)($result['duplicate']??false),'cascades'=>$cascades];
}
