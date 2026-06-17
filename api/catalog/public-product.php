<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog.php';
mg_require_method('GET');

$identifier=trim((string)($_GET['id']??$_GET['slug']??''));
if($identifier==='')mg_fail('Product not found.',404);
$pdo=mg_db();
$stmt=$pdo->prepare("SELECT p.public_id product_id,p.slug,p.product_type,p.status,p.merchant_user_id,p.published_at,
        v.public_id version_id,v.version_number,v.title,v.description,v.unit_value_cents,v.currency,
        v.expiration_policy_json,v.terms_json,v.fulfillment_json,v.metadata_json,v.published_at version_published_at,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(v.metadata_json,'$.merchant_name')),'Microgifter merchant') merchant_name,
        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(v.metadata_json,'$.headline')),v.description,'A local gift ready to send.') headline,
        JSON_UNQUOTE(JSON_EXTRACT(v.metadata_json,'$.message')) message
    FROM catalog_products p
    INNER JOIN catalog_product_versions v ON v.id=p.current_version_id AND v.version_status='published'
    WHERE p.status='published' AND (p.public_id=? OR p.slug=?)
    LIMIT 1");
$stmt->execute([$identifier,$identifier]);
$product=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$product)mg_fail('Product not found.',404);

$assets=$pdo->prepare('SELECT a.public_id,a.asset_type,a.original_filename,a.mime_type,a.width_px,a.height_px,a.duration_ms,pva.role,pva.sort_order FROM catalog_product_version_assets pva INNER JOIN catalog_assets a ON a.id=pva.asset_id WHERE pva.product_version_id=(SELECT id FROM catalog_product_versions WHERE public_id=? LIMIT 1) AND a.status=\'ready\' ORDER BY pva.sort_order,pva.id');
$assets->execute([(string)$product['version_id']]);

$product['metadata']=$product['metadata_json']?json_decode((string)$product['metadata_json'],true):[];
$product['terms']=$product['terms_json']?json_decode((string)$product['terms_json'],true):null;
$product['expiration_policy']=$product['expiration_policy_json']?json_decode((string)$product['expiration_policy_json'],true):null;
unset($product['metadata_json'],$product['terms_json'],$product['expiration_policy_json'],$product['fulfillment_json']);

mg_ok(['product'=>$product,'assets'=>$assets->fetchAll(PDO::FETCH_ASSOC)]);
