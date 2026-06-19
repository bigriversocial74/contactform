<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/feed/_feed.php';
require_once dirname(__DIR__) . '/catalog/_public_identity.php';

mg_require_method('GET');
$pdo = mg_db();
$productIdentity = mg_catalog_resolve_public_product_identity(
    $pdo,
    $_GET['id'] ?? null,
    $_GET['slug'] ?? null
);

$stmt = $pdo->prepare(
    "SELECT cp.public_id, cp.slug, cp.product_type, cp.published_at,
            cpv.public_id AS version_id, cpv.title, cpv.description, cpv.unit_value_cents,
            cpv.currency, cpv.expiration_policy_json, cpv.terms_json, cpv.fulfillment_json,
            cpv.metadata_json, fp.public_id AS post_id, fp.post_type, fp.visibility,
            fpv.public_id AS post_version_id, fpv.headline, fpv.caption, fpv.cta_label,
            fpv.cta_url, fpv.offer_snapshot_json, fpv.presentation_json,
            ms.slug AS storefront_slug, ms.display_name AS merchant_name
     FROM catalog_products cp
     INNER JOIN catalog_product_versions cpv ON cpv.id = cp.current_version_id
     LEFT JOIN feed_posts fp ON fp.catalog_product_id = cp.id
       AND fp.status IN ('published','promoted') AND fp.visibility IN ('public','unlisted')
     LEFT JOIN feed_post_versions fpv ON fpv.id = fp.current_version_id
     LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id = cp.merchant_user_id AND ms.status = 'published'
     WHERE cp.public_id = ? AND cp.status = 'published' AND cpv.version_status = 'published'
     ORDER BY fp.promoted_at DESC, fp.updated_at DESC LIMIT 1"
);
$stmt->execute([(string)$productIdentity['public_id']]);
$product = $stmt->fetch();
if (!$product) mg_fail('Product not found.', 404);

$assetsStmt = $pdo->prepare(
    'SELECT cpva.role, cpva.sort_order, ca.public_id AS asset_id, ca.asset_type, ca.mime_type
     FROM catalog_product_version_assets cpva
     INNER JOIN catalog_assets ca ON ca.id = cpva.asset_id AND ca.status = \'ready\'
     WHERE cpva.product_version_id = (SELECT id FROM catalog_product_versions WHERE public_id = ? LIMIT 1)
     ORDER BY cpva.sort_order ASC, cpva.id ASC'
);
$assetsStmt->execute([$product['version_id']]);
$assets = array_map(static function (array $asset): array {
    $asset['url'] = '/api/public/media.php?asset=' . rawurlencode((string) $asset['asset_id']);
    return $asset;
}, $assetsStmt->fetchAll());

$elements = [];
if (!empty($product['post_version_id'])) {
    $elementsStmt = $pdo->prepare(
        'SELECT fpe.public_id, fpe.element_type, fpe.sort_order, fpe.content_json,
                ca.public_id AS asset_id, ca.asset_type, ca.mime_type
         FROM feed_post_elements fpe
         LEFT JOIN catalog_assets ca ON ca.id = fpe.asset_id
         WHERE fpe.feed_post_version_id = (SELECT id FROM feed_post_versions WHERE public_id = ? LIMIT 1)
         ORDER BY fpe.sort_order ASC, fpe.id ASC'
    );
    $elementsStmt->execute([$product['post_version_id']]);
    foreach ($elementsStmt->fetchAll() as $element) {
        $element['content'] = $element['content_json'] ? (json_decode((string) $element['content_json'], true) ?: []) : [];
        $element['url'] = $element['asset_id'] ? '/api/public/media.php?asset=' . rawurlencode((string) $element['asset_id']) : null;
        unset($element['content_json']);
        $elements[] = $element;
    }
}

foreach (['expiration_policy_json' => 'expiration_policy','terms_json' => 'terms','fulfillment_json' => 'fulfillment','metadata_json' => 'metadata','offer_snapshot_json' => 'offer','presentation_json' => 'presentation'] as $column => $key) {
    $product[$key] = $product[$column] ? (json_decode((string) $product[$column], true) ?: []) : [];
    unset($product[$column]);
}

$builderType = (string)($product['fulfillment']['builder_type'] ?? $product['presentation']['builder_type'] ?? 'simple_product');
if (!in_array($builderType, ['simple_product','greeting_card','multimedia_greeting_card','simple_collab'], true)) {
    $builderType = 'simple_product';
}
$mediaByRole = [];
foreach ($assets as $asset) {
    $role = (string)($asset['role'] ?? '');
    if ($role !== '' && !isset($mediaByRole[$role])) $mediaByRole[$role] = $asset;
}

$product['builder_type'] = $builderType;
$product['media_by_role'] = $mediaByRole;
$product['public_url'] = mg_catalog_public_product_url((string)$product['public_id'], (string)$product['slug']);
$product['storefront_url'] = $product['storefront_slug'] ? '/store.php?s=' . rawurlencode((string) $product['storefront_slug']) : null;
$product['assets'] = $assets;
$product['elements'] = $elements;
mg_ok(['product' => $product]);
