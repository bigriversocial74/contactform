<?php
declare(strict_types=1);

require_once __DIR__ . '/_feed.php';

mg_require_method('GET');
$assetId = strtolower(trim((string) ($_GET['asset'] ?? '')));
$itemId = trim((string) ($_GET['item'] ?? '')) ?: null;
$postVersionId = strtolower(trim((string) ($_GET['post'] ?? ''))) ?: null;
if (strlen($assetId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $assetId)) mg_fail('Invalid asset identifier.', 422);

$user = mg_current_user();
$userId = (int) ($user['id'] ?? 0);
$params = [$assetId];
$accessSql = '';
if ($itemId) {
    if ($userId < 1) mg_fail('Authentication required.', 401);
    $accessSql = ' AND EXISTS (
        SELECT 1 FROM feed_post_elements fpe
        INNER JOIN pppm_feed_bindings pfb ON pfb.feed_post_version_id = fpe.feed_post_version_id
        INNER JOIN pppm_items p ON p.id = pfb.pppm_item_id
        WHERE fpe.asset_id = ca.id AND p.public_id = ?
          AND (p.recipient_user_id = ? OR p.owner_user_id = ? OR p.issuer_user_id = ? OR p.merchant_user_id = ?)
    )';
    array_push($params, $itemId, $userId, $userId, $userId, $userId);
} elseif ($postVersionId) {
    $accessSql = " AND EXISTS (
        SELECT 1 FROM feed_post_elements fpe
        INNER JOIN feed_post_versions fpv ON fpv.id = fpe.feed_post_version_id
        INNER JOIN feed_posts fp ON fp.id = fpv.feed_post_id
        WHERE fpe.asset_id = ca.id AND fpv.public_id = ?
          AND fp.visibility IN ('public','unlisted') AND fp.status IN ('published','promoted')
    )";
    $params[] = $postVersionId;
} else {
    mg_fail('Media access context is required.', 422);
}

$stmt = mg_db()->prepare(
    "SELECT ca.storage_provider, ca.storage_key, ca.original_filename, ca.mime_type
     FROM catalog_assets ca WHERE ca.public_id = ? AND ca.status = 'ready'" . $accessSql . ' LIMIT 1'
);
$stmt->execute($params);
$asset = $stmt->fetch();
if (!$asset || (string) $asset['storage_provider'] !== 'private_local') mg_fail('Media not found.', 404);

$root = realpath(dirname(__DIR__, 2) . '/storage/private');
$path = realpath(dirname(__DIR__, 2) . '/storage/private/' . ltrim((string) $asset['storage_key'], '/'));
if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) mg_fail('Media unavailable.', 404);

header('Content-Type: ' . ((string) $asset['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . rawurlencode((string) ($asset['original_filename'] ?: 'media')) . '"');
readfile($path);
exit;
