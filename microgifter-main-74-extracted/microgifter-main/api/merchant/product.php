<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

mg_require_method('GET');
$user = mg_require_permission('catalog.products.view');
$userId = (int)$user['id'];
$productId = trim((string)($_GET['id'] ?? ''));
if ($productId === '') mg_fail('Product not found.',404);
$pdo = mg_db();

$stmt = $pdo->prepare(
    'SELECT p.*,v.public_id version_id,v.version_number,v.version_status,v.title,v.description,
            v.unit_value_cents,v.currency,v.expiration_policy_json,v.terms_json,v.fulfillment_json,
            v.metadata_json,v.published_at version_published_at,v.created_at version_created_at,
            d.public_id draft_id,d.builder_type,d.payload_json,d.asset_map_json,d.lock_version,d.updated_at draft_updated_at
     FROM catalog_products p
     LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
     LEFT JOIN catalog_builder_drafts d ON d.product_id=p.id
     WHERE p.public_id=? AND p.merchant_user_id=? LIMIT 1'
);
$stmt->execute([$productId,$userId]);
$product = $stmt->fetch();
if (!$product) mg_fail('Product not found.',404);

$placementStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT rp.storefront_revision_id)
     FROM merchant_storefront_revision_products rp
     INNER JOIN merchant_storefront_revisions sr ON sr.id=rp.storefront_revision_id
     WHERE rp.catalog_product_id=? AND sr.revision_status IN ('draft','published') AND rp.visibility='visible'"
);
$placementStmt->execute([(int)$product['id']]);
$product['storefront_placement_count'] = (int)$placementStmt->fetchColumn();

$versions = $pdo->prepare(
    "SELECT v.public_id,v.version_number,v.version_status,v.title,v.description,v.unit_value_cents,v.currency,v.checksum,v.published_at,v.created_at,
            COUNT(pva.asset_id) asset_count,
            SUM(CASE WHEN a.asset_type='image' THEN 1 ELSE 0 END) image_count,
            SUM(CASE WHEN a.asset_type='audio' THEN 1 ELSE 0 END) audio_count,
            SUM(CASE WHEN a.asset_type='video' THEN 1 ELSE 0 END) video_count
     FROM catalog_product_versions v
     LEFT JOIN catalog_product_version_assets pva ON pva.product_version_id=v.id
     LEFT JOIN catalog_assets a ON a.id=pva.asset_id
     WHERE v.product_id=?
     GROUP BY v.id
     ORDER BY v.version_number DESC"
);
$versions->execute([(int)$product['id']]);

$assetsStmt = $pdo->prepare(
    'SELECT a.public_id,a.asset_type,a.original_filename,a.mime_type,a.byte_size,a.width_px,a.height_px,a.duration_ms,a.status,pva.role,pva.sort_order
     FROM catalog_product_version_assets pva
     INNER JOIN catalog_assets a ON a.id=pva.asset_id
     WHERE pva.product_version_id=?
     ORDER BY pva.sort_order,pva.id'
);
$assetsStmt->execute([(int)($product['current_version_id'] ?? 0)]);
$versionAssets = $assetsStmt->fetchAll();
foreach ($versionAssets as &$asset) {
    $asset['preview_url'] = '/api/catalog/asset-file.php?id=' . rawurlencode((string)$asset['public_id']);
}
unset($asset);

$draftAssetMap = $product['asset_map_json'] ? (json_decode((string)$product['asset_map_json'],true) ?: []) : [];
$draftAssets = [];
if ($draftAssetMap) {
    $placeholders = implode(',',array_fill(0,count($draftAssetMap),'?'));
    $assetLookup = $pdo->prepare(
        "SELECT public_id,asset_type,original_filename,mime_type,byte_size,width_px,height_px,duration_ms,status
         FROM catalog_assets WHERE owner_user_id=? AND public_id IN ({$placeholders})"
    );
    $assetLookup->execute(array_merge([$userId],array_values($draftAssetMap)));
    $byId = [];
    foreach ($assetLookup->fetchAll() as $asset) $byId[(string)$asset['public_id']] = $asset;
    foreach ($draftAssetMap as $role => $publicId) {
        if (!isset($byId[(string)$publicId])) continue;
        $asset = $byId[(string)$publicId];
        $asset['role'] = (string)$role;
        $asset['preview_url'] = '/api/catalog/asset-file.php?id=' . rawurlencode((string)$asset['public_id']);
        $draftAssets[] = $asset;
    }
}

$product['payload'] = $product['payload_json'] ? (json_decode((string)$product['payload_json'],true) ?: []) : [];
$product['asset_map'] = $draftAssetMap;
$product['expiration_policy'] = $product['expiration_policy_json'] ? (json_decode((string)$product['expiration_policy_json'],true) ?: []) : [];
$product['terms'] = $product['terms_json'] ? (json_decode((string)$product['terms_json'],true) ?: []) : [];
$product['fulfillment'] = $product['fulfillment_json'] ? (json_decode((string)$product['fulfillment_json'],true) ?: []) : [];
$product['metadata'] = $product['metadata_json'] ? (json_decode((string)$product['metadata_json'],true) ?: []) : [];
$product['public_url'] = (string)$product['status'] === 'published' ? '/product.php?p=' . rawurlencode((string)$product['slug']) : null;
$product['builder_url'] = '/build.php?id=' . rawurlencode((string)$product['public_id']);
$product['has_draft_changes'] = !empty($product['draft_id']) && (empty($product['version_id']) || strtotime((string)$product['draft_updated_at']) > strtotime((string)($product['version_published_at'] ?: $product['version_created_at'])));
foreach (['payload_json','asset_map_json','expiration_policy_json','terms_json','fulfillment_json','metadata_json'] as $raw) unset($product[$raw]);

$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
$isSuper = in_array('super_admin',$roles,true);

mg_ok([
    'product'=>$product,
    'versions'=>$versions->fetchAll(),
    'assets'=>$versionAssets,
    'draft_assets'=>$draftAssets,
    'access'=>[
        'manage'=>$isSuper || in_array('catalog.products.manage',$permissions,true),
        'publish'=>$isSuper || in_array('catalog.products.publish',$permissions,true),
        'assets'=>$isSuper || in_array('catalog.assets.manage',$permissions,true),
    ],
]);
