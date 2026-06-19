<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog.php';

function mg_catalog_operations_product(PDO $pdo,string $publicId,bool $lock=false): array
{
    $sql="SELECT p.*,v.public_id current_version_public_id,v.version_status,v.title,v.description,v.unit_value_cents,v.currency,v.published_at version_published_at,mw.public_id workspace_public_id,mw.status workspace_status,mw.eligibility_status,s.public_id storefront_public_id,s.status storefront_status,pp.status profile_status,pp.visibility profile_visibility FROM catalog_products p LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=p.merchant_user_id LEFT JOIN merchant_storefronts s ON s.merchant_user_id=p.merchant_user_id LEFT JOIN public_profiles pp ON pp.user_id=p.merchant_user_id WHERE p.public_id=? LIMIT 1".($lock?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute([$publicId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('Catalog product not found.');
    return $row;
}

function mg_catalog_operations_product_readiness(PDO $pdo,array $product): array
{
    $assetStmt=$pdo->prepare("SELECT COUNT(*) linked_assets,SUM(CASE WHEN a.status<>'ready' THEN 1 ELSE 0 END) unavailable_assets FROM catalog_product_version_assets pva INNER JOIN catalog_assets a ON a.id=pva.asset_id WHERE pva.product_version_id=?");
    $assetStmt->execute([(int)($product['current_version_id']??0)]);$assets=$assetStmt->fetch(PDO::FETCH_ASSOC)?:[];
    $locationStmt=$pdo->prepare("SELECT COUNT(*) FROM merchant_locations ml INNER JOIN merchant_workspaces mw ON mw.id=ml.workspace_id WHERE mw.merchant_user_id=? AND ml.status='active'");
    $locationStmt->execute([(int)$product['merchant_user_id']]);$activeLocations=(int)$locationStmt->fetchColumn();
    $checks=[
        ['key'=>'workspace','label'=>'Merchant workspace is active','complete'=>(string)($product['workspace_status']??'')==='active'],
        ['key'=>'eligibility','label'=>'Merchant is eligible or manually approved','complete'=>in_array((string)($product['eligibility_status']??''),['eligible','manual_review'],true)],
        ['key'=>'profile','label'=>'Merchant profile is publicly available','complete'=>(string)($product['profile_status']??'')==='active'&&in_array((string)($product['profile_visibility']??''),['public','unlisted'],true)],
        ['key'=>'version','label'=>'Published product version exists','complete'=>!empty($product['current_version_id'])&&(string)($product['version_status']??'')==='published'],
        ['key'=>'value','label'=>'Product has a positive value','complete'=>(int)($product['unit_value_cents']??0)>0],
        ['key'=>'assets','label'=>'All linked assets are ready','complete'=>(int)($assets['unavailable_assets']??0)===0],
        ['key'=>'locations','label'=>'At least one active merchant location exists','complete'=>$activeLocations>0],
        ['key'=>'storefront','label'=>'Storefront is not suspended or archived','complete'=>!in_array((string)($product['storefront_status']??''),['suspended','archived'],true)],
    ];
    return ['can_publish'=>count(array_filter($checks,static fn(array $check):bool=>!$check['complete']))===0,'checks'=>$checks,'linked_assets'=>(int)($assets['linked_assets']??0),'unavailable_assets'=>(int)($assets['unavailable_assets']??0),'active_locations'=>$activeLocations];
}

function mg_catalog_operations_transition_product(PDO $pdo,string $publicId,string $action): array
{
    $product=mg_catalog_operations_product($pdo,$publicId,true);$from=(string)$product['status'];$readiness=mg_catalog_operations_product_readiness($pdo,$product);
    $to=match($action){'publish_product'=>'published','review_product'=>'review','pause_product'=>'paused','restore_product'=>!empty($product['current_version_id'])?'published':'draft','archive_product'=>'archived',default=>throw new InvalidArgumentException('Invalid product lifecycle action.')};
    if(($action==='publish_product'||($action==='restore_product'&&$to==='published'))&&empty($readiness['can_publish'])) throw new RuntimeException('Product publishing requirements are not complete.');
    if($action==='pause_product'&&$from!=='published') throw new RuntimeException('Only published products can be paused.');
    if($action==='review_product'&&$from==='archived') throw new RuntimeException('Archived products must be restored before review.');
    if($from===$to) return ['product'=>$product,'from_status'=>$from,'to_status'=>$to,'duplicate'=>true,'readiness'=>$readiness];
    if($to==='published')$pdo->prepare("UPDATE catalog_products SET status='published',published_at=COALESCE(published_at,NOW()),archived_at=NULL,updated_at=NOW() WHERE id=?")->execute([(int)$product['id']]);
    elseif($to==='archived')$pdo->prepare("UPDATE catalog_products SET status='archived',archived_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$product['id']]);
    else $pdo->prepare('UPDATE catalog_products SET status=?,archived_at=NULL,updated_at=NOW() WHERE id=?')->execute([$to,(int)$product['id']]);
    $product['status']=$to;return ['product'=>$product,'from_status'=>$from,'to_status'=>$to,'duplicate'=>false,'readiness'=>$readiness];
}

function mg_catalog_operations_asset(PDO $pdo,string $publicId,bool $lock=false): array
{
    $stmt=$pdo->prepare('SELECT * FROM catalog_assets WHERE public_id=? LIMIT 1'.($lock?' FOR UPDATE':''));$stmt->execute([$publicId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) throw new RuntimeException('Catalog asset not found.');return $row;
}

function mg_catalog_operations_transition_asset(PDO $pdo,string $publicId,string $action): array
{
    $asset=mg_catalog_operations_asset($pdo,$publicId,true);$from=(string)$asset['status'];$to=match($action){'quarantine_asset'=>'quarantined','retry_asset'=>'pending','archive_asset'=>'archived',default=>throw new InvalidArgumentException('Invalid asset lifecycle action.')};
    if($from===$to)return ['asset'=>$asset,'from_status'=>$from,'to_status'=>$to,'duplicate'=>true];
    $pdo->prepare('UPDATE catalog_assets SET status=?,updated_at=NOW() WHERE id=?')->execute([$to,(int)$asset['id']]);$asset['status']=$to;
    return ['asset'=>$asset,'from_status'=>$from,'to_status'=>$to,'duplicate'=>false];
}
