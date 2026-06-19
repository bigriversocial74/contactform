<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once dirname(__DIR__,2) . '/catalog/_operations_lifecycle.php';

function mg_admin_mc_product_detail(PDO $pdo,string $reference): array
{
    $product=mg_catalog_operations_product($pdo,$reference,false);
    $merchant=mg_admin_mc_one($pdo,'SELECT id,email,display_name,full_name,status FROM users WHERE id=? LIMIT 1',[(int)$product['merchant_user_id']]);
    $versions=mg_admin_mc_all($pdo,'SELECT public_id,version_number,version_status,title,description,unit_value_cents,currency,published_at,created_by_user_id,created_at FROM catalog_product_versions WHERE product_id=? ORDER BY version_number DESC,id DESC LIMIT 100',[(int)$product['id']]);
    $assets=mg_admin_mc_all($pdo,'SELECT a.public_id,a.asset_type,a.status,a.original_filename,a.mime_type,a.byte_size,pva.role,pva.sort_order,a.created_at,a.updated_at FROM catalog_product_version_assets pva INNER JOIN catalog_assets a ON a.id=pva.asset_id WHERE pva.product_version_id=? ORDER BY pva.sort_order,pva.id LIMIT 100',[(int)($product['current_version_id']??0)]);
    $placements=mg_admin_mc_all($pdo,'SELECT s.public_id storefront_id,s.slug,s.status storefront_status,r.public_id revision_id,r.version_number,r.revision_status,rp.sort_order,rp.is_featured,rp.visibility FROM merchant_storefront_revision_products rp INNER JOIN merchant_storefront_revisions r ON r.id=rp.storefront_revision_id INNER JOIN merchant_storefronts s ON s.id=r.storefront_id WHERE rp.catalog_product_id=? ORDER BY r.version_number DESC,rp.sort_order LIMIT 100',[(int)$product['id']]);
    $draft=mg_admin_mc_one($pdo,'SELECT public_id,builder_type,lock_version,updated_by_user_id,created_at,updated_at FROM catalog_builder_drafts WHERE product_id=? LIMIT 1',[(int)$product['id']]);
    $feed=mg_admin_mc_all($pdo,'SELECT public_id,post_type,visibility,status,promoted_at,archived_at,created_at,updated_at FROM feed_posts WHERE catalog_product_id=? ORDER BY updated_at DESC,id DESC LIMIT 50',[(int)$product['id']]);
    $templates=mg_admin_mc_all($pdo,'SELECT public_id,item_type,default_funding_type,status,created_at,updated_at FROM catalog_pppm_templates WHERE product_version_id=? LIMIT 20',[(int)($product['current_version_id']??0)]);
    $readiness=mg_catalog_operations_product_readiness($pdo,$product);
    $issues=[];foreach($readiness['checks'] as $check){if(empty($check['complete']))$issues[]=['key'=>(string)$check['key'],'label'=>(string)$check['label'],'severity'=>in_array($check['key'],['version','workspace','assets'],true)?'high':'normal'];}
    if((string)$product['status']==='paused')$issues[]=['key'=>'paused','label'=>'Product is paused','severity'=>'high'];
    if((string)$product['status']==='archived')$issues[]=['key'=>'archived','label'=>'Product is archived','severity'=>'normal'];
    return [
        'entity'=>['type'=>'product','public_id'=>(string)$product['public_id'],'status'=>(string)$product['status'],'secondary_status'=>(string)$product['product_type'],'title'=>(string)($product['title']??$product['slug']),'merchant_user_id'=>(int)$product['merchant_user_id'],'merchant'=>$merchant,'created_at'=>(string)$product['created_at'],'updated_at'=>(string)$product['updated_at']],
        'facts'=>[mg_admin_mc_fact('Slug',(string)$product['slug']),mg_admin_mc_fact('Current version',$product['current_version_public_id']),mg_admin_mc_fact('Version status',$product['version_status'],'status'),mg_admin_mc_fact('Value',(int)($product['unit_value_cents']??0),'money'),mg_admin_mc_fact('Currency',$product['currency']),mg_admin_mc_fact('Published at',$product['published_at'],'date'),mg_admin_mc_fact('Workspace status',$product['workspace_status'],'status'),mg_admin_mc_fact('Storefront status',$product['storefront_status'],'status')],
        'issues'=>$issues,'readiness'=>$readiness,'related'=>compact('versions','assets','placements','draft','feed','templates'),
    ];
}

function mg_admin_mc_asset_detail(PDO $pdo,string $reference): array
{
    $asset=mg_catalog_operations_asset($pdo,$reference,false);
    $merchant=mg_admin_mc_one($pdo,'SELECT id,email,display_name,full_name,status FROM users WHERE id=? LIMIT 1',[(int)$asset['owner_user_id']]);
    $products=mg_admin_mc_all($pdo,'SELECT p.public_id product_id,p.slug,p.status product_status,v.public_id version_id,v.version_number,v.version_status,v.title,pva.role,pva.sort_order FROM catalog_product_version_assets pva INNER JOIN catalog_product_versions v ON v.id=pva.product_version_id INNER JOIN catalog_products p ON p.id=v.product_id WHERE pva.asset_id=? ORDER BY p.updated_at DESC,v.version_number DESC LIMIT 100',[(int)$asset['id']]);
    $stores=mg_admin_mc_all($pdo,'SELECT public_id,slug,display_name,status,CASE WHEN logo_asset_id=? THEN "logo" ELSE "cover" END asset_role FROM merchant_storefronts WHERE logo_asset_id=? OR cover_asset_id=? ORDER BY updated_at DESC,id DESC LIMIT 50',[(int)$asset['id'],(int)$asset['id'],(int)$asset['id']]);
    $revisions=mg_admin_mc_all($pdo,'SELECT r.public_id,r.version_number,r.revision_status,s.public_id storefront_id,s.slug,CASE WHEN r.logo_asset_id=? THEN "logo" ELSE "cover" END asset_role FROM merchant_storefront_revisions r INNER JOIN merchant_storefronts s ON s.id=r.storefront_id WHERE r.logo_asset_id=? OR r.cover_asset_id=? ORDER BY r.created_at DESC,r.id DESC LIMIT 50',[(int)$asset['id'],(int)$asset['id'],(int)$asset['id']]);
    $feedCount=(int)mg_admin_mc_scalar($pdo,'SELECT COUNT(*) FROM feed_post_elements WHERE asset_id=?',[(int)$asset['id']]);
    $linkCount=count($products)+count($stores)+count($revisions)+$feedCount;
    $issues=[];
    if((string)$asset['status']!=='ready')$issues[]=['key'=>'status','label'=>'Asset is not ready','severity'=>in_array((string)$asset['status'],['quarantined','failed'],true)?'high':'normal'];
    if($linkCount===0)$issues[]=['key'=>'orphaned','label'=>'Asset is not linked to a product, storefront, or feed element','severity'=>'normal'];
    if($asset['checksum_sha256']===null)$issues[]=['key'=>'checksum','label'=>'Asset checksum is missing','severity'=>'normal'];
    return [
        'entity'=>['type'=>'asset','public_id'=>(string)$asset['public_id'],'status'=>(string)$asset['status'],'secondary_status'=>(string)$asset['asset_type'],'title'=>(string)($asset['original_filename']??$asset['storage_key']),'merchant_user_id'=>(int)$asset['owner_user_id'],'merchant'=>$merchant,'created_at'=>(string)$asset['created_at'],'updated_at'=>(string)$asset['updated_at']],
        'facts'=>[mg_admin_mc_fact('Provider',(string)$asset['storage_provider']),mg_admin_mc_fact('Storage key',(string)$asset['storage_key']),mg_admin_mc_fact('MIME type',$asset['mime_type']),mg_admin_mc_fact('Bytes',$asset['byte_size'],'number'),mg_admin_mc_fact('Checksum',$asset['checksum_sha256']),mg_admin_mc_fact('Dimensions',($asset['width_px']&&$asset['height_px'])?$asset['width_px'].' × '.$asset['height_px']:null),mg_admin_mc_fact('Linked records',$linkCount,'number'),mg_admin_mc_fact('Feed elements',$feedCount,'number')],
        'issues'=>$issues,'related'=>compact('products','stores','revisions'),
    ];
}
