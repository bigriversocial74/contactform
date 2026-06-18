<?php
declare(strict_types=1);

require_once dirname(__DIR__,2) . '/merchant/_storefront.php';

function mg_admin_mc_workspace(PDO $pdo,string $publicId,bool $lock=false): array
{
    $stmt=$pdo->prepare('SELECT mw.*,u.status user_status,u.email,u.display_name user_display_name,u.full_name FROM merchant_workspaces mw INNER JOIN users u ON u.id=mw.merchant_user_id WHERE mw.public_id=? LIMIT 1'.($lock?' FOR UPDATE':''));
    $stmt->execute([$publicId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('Merchant workspace not found.');
    return $row;
}

function mg_admin_mc_transition_workspace(PDO $pdo,string $publicId,string $action): array
{
    $workspace=mg_admin_mc_workspace($pdo,$publicId,true);$from=(string)$workspace['status'];
    $to=match($action){'activate_workspace'=>'active','review_workspace'=>'pending_review','suspend_workspace'=>'suspended','restore_workspace'=>'active','archive_workspace'=>'archived',default=>throw new InvalidArgumentException('Invalid workspace lifecycle action.')};
    if($to==='active'&&(string)$workspace['user_status']!=='active') throw new RuntimeException('The merchant account must be active before activating the workspace.');
    if($from===$to)return ['workspace'=>$workspace,'from_status'=>$from,'to_status'=>$to,'duplicate'=>true];
    if($to==='active')$pdo->prepare("UPDATE merchant_workspaces SET status='active',activated_at=COALESCE(activated_at,NOW()),updated_at=NOW() WHERE id=?")->execute([(int)$workspace['id']]);
    else $pdo->prepare('UPDATE merchant_workspaces SET status=?,updated_at=NOW() WHERE id=?')->execute([$to,(int)$workspace['id']]);
    $workspace['status']=$to;return ['workspace'=>$workspace,'from_status'=>$from,'to_status'=>$to,'duplicate'=>false];
}

function mg_admin_mc_storefront(PDO $pdo,string $publicId,bool $lock=false): array
{
    $stmt=$pdo->prepare('SELECT s.*,mw.public_id workspace_public_id,mw.status workspace_status FROM merchant_storefronts s LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=s.merchant_user_id WHERE s.public_id=? LIMIT 1'.($lock?' FOR UPDATE':''));
    $stmt->execute([$publicId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('Merchant storefront not found.');
    return $row;
}

function mg_admin_mc_publish_storefront(PDO $pdo,array $store,int $actorId): array
{
    if((string)($store['workspace_status']??'')!=='active') throw new RuntimeException('The merchant workspace must be active before publishing the storefront.');
    if((string)$store['status']==='suspended') throw new RuntimeException('Suspended storefronts cannot be published.');
    $draft=mg_storefront_revision($pdo,(int)$store['id'],'draft');
    if(!$draft) throw new RuntimeException('Save a storefront draft before publishing.');
    $management=mg_storefront_revision_management($pdo,$draft,(int)$store['merchant_user_id']);
    $products=mg_storefront_revision_products($pdo,(int)$draft['id']);
    $readiness=mg_storefront_readiness($store,$management,$products);
    if(empty($readiness['can_publish'])) throw new RuntimeException('Storefront publishing requirements are not complete.');
    $old=mg_storefront_revision($pdo,(int)$store['id'],'published');
    if($old)$pdo->prepare("UPDATE merchant_storefront_revisions SET revision_status='retired',updated_at=NOW() WHERE id=?")->execute([(int)$old['id']]);
    $pdo->prepare("UPDATE merchant_storefront_revisions SET revision_status='published',published_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$draft['id']]);
    $pdo->prepare("INSERT INTO merchant_storefront_states (storefront_id,draft_revision_id,published_revision_id,updated_at) VALUES (?,NULL,?,NOW()) ON DUPLICATE KEY UPDATE draft_revision_id=NULL,published_revision_id=VALUES(published_revision_id),updated_at=NOW()")
        ->execute([(int)$store['id'],(int)$draft['id']]);
    $pdo->prepare("UPDATE merchant_storefronts SET display_name=?,headline=?,description=?,logo_asset_id=?,cover_asset_id=?,status='published',published_at=COALESCE(published_at,NOW()),updated_at=NOW() WHERE id=?")
        ->execute([$draft['display_name'],$draft['headline'],$draft['description'],$draft['logo_asset_id'],$draft['cover_asset_id'],(int)$store['id']]);
    return ['storefront'=>$store,'from_status'=>(string)$store['status'],'to_status'=>'published','duplicate'=>false,'revision_id'=>(string)$draft['public_id'],'readiness'=>$readiness,'actor_id'=>$actorId];
}

function mg_admin_mc_transition_storefront(PDO $pdo,string $publicId,string $action,int $actorId): array
{
    $store=mg_admin_mc_storefront($pdo,$publicId,true);$from=(string)$store['status'];
    if($action==='publish_storefront'){
        if($from==='published'&&!mg_storefront_revision($pdo,(int)$store['id'],'draft'))return ['storefront'=>$store,'from_status'=>$from,'to_status'=>$from,'duplicate'=>true];
        return mg_admin_mc_publish_storefront($pdo,$store,$actorId);
    }
    $to=match($action){'unpublish_storefront'=>'draft','suspend_storefront'=>'suspended','restore_storefront'=>mg_storefront_revision($pdo,(int)$store['id'],'published')?'published':'draft','archive_storefront'=>'archived',default=>throw new InvalidArgumentException('Invalid storefront lifecycle action.')};
    if($from===$to)return ['storefront'=>$store,'from_status'=>$from,'to_status'=>$to,'duplicate'=>true];
    $pdo->prepare('UPDATE merchant_storefronts SET status=?,updated_at=NOW() WHERE id=?')->execute([$to,(int)$store['id']]);$store['status']=$to;
    return ['storefront'=>$store,'from_status'=>$from,'to_status'=>$to,'duplicate'=>false];
}
