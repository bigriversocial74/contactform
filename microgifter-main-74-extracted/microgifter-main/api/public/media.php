<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/feed/_feed.php';

mg_require_method('GET');
$assetId = strtolower(trim((string)($_GET['asset'] ?? '')));
if (strlen($assetId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $assetId)) {
    mg_fail('Invalid asset identifier.', 422);
}

$profileMediaUrl = '/api/public/media.php?asset=' . $assetId;
$stmt = mg_db()->prepare(
    "SELECT ca.storage_provider, ca.storage_key, ca.original_filename, ca.mime_type
     FROM catalog_assets ca
     WHERE ca.public_id = ? AND ca.status = 'ready' AND (
       EXISTS (
         SELECT 1 FROM public_profiles pp
         INNER JOIN users pu ON pu.id=pp.user_id
         WHERE pp.status='active' AND pp.visibility IN ('public','unlisted') AND pu.status='active'
           AND (pp.avatar_url=? OR pp.cover_url=?)
       ) OR EXISTS (
         SELECT 1 FROM merchant_storefronts ms
         WHERE ms.status = 'published' AND (ms.logo_asset_id = ca.id OR ms.cover_asset_id = ca.id)
       ) OR EXISTS (
         SELECT 1 FROM catalog_product_version_assets cpva
         INNER JOIN catalog_product_versions cpv ON cpv.id = cpva.product_version_id
         INNER JOIN catalog_products cp ON cp.id = cpv.product_id
         WHERE cpva.asset_id = ca.id AND cp.status = 'published' AND cpv.version_status = 'published'
       ) OR EXISTS (
         SELECT 1 FROM feed_post_elements fpe
         INNER JOIN feed_post_versions fpv ON fpv.id = fpe.feed_post_version_id
         INNER JOIN feed_posts fp ON fp.id = fpv.feed_post_id
         WHERE fpe.asset_id = ca.id AND fp.visibility IN ('public','unlisted')
           AND fp.status IN ('published','promoted') AND fpv.version_status = 'published'
       )
     ) LIMIT 1"
);
$stmt->execute([$assetId, $profileMediaUrl, $profileMediaUrl]);
$asset = $stmt->fetch();
if (!$asset || (string)$asset['storage_provider'] !== 'private_local') {
    mg_fail('Media not found.', 404);
}

$root = realpath(dirname(__DIR__, 2) . '/storage/private');
$path = realpath(dirname(__DIR__, 2) . '/storage/private/' . ltrim((string)$asset['storage_key'], '/'));
if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    mg_fail('Media unavailable.', 404);
}

$size = filesize($path);
if ($size === false) mg_fail('Media unavailable.', 404);
header('Content-Type: ' . ((string)$asset['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . $size);
header('Cache-Control: public, max-age=300');
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . rawurlencode((string)($asset['original_filename'] ?: 'media')) . '"');
readfile($path);
exit;
