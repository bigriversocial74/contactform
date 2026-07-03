<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog.php';

mg_require_method('GET');
$assetId = strtolower(trim((string) ($_GET['id'] ?? '')));
if (strlen($assetId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $assetId)) {
    mg_fail('Invalid asset identifier.', 422);
}

function mg_catalog_asset_file_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $columnName)) return false;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$tableName, $columnName]);
    return (int) $stmt->fetchColumn() > 0;
}

$pdo = mg_db();
$user = mg_current_user();
$userId = (int) ($user['id'] ?? 0);
$deletedFilter = mg_catalog_asset_file_column_exists($pdo, 'catalog_assets', 'deleted_at') ? ' AND ca.deleted_at IS NULL' : '';
$stmt = $pdo->prepare(
    "SELECT ca.storage_provider, ca.storage_key, ca.original_filename, ca.mime_type, ca.byte_size, ca.checksum_sha256
     FROM catalog_assets ca
     WHERE ca.public_id = ? AND ca.status = 'ready'$deletedFilter
       AND (
         ca.owner_user_id = ?
         OR EXISTS (
           SELECT 1
           FROM catalog_product_version_assets pva
           INNER JOIN catalog_product_versions cpv ON cpv.id = pva.product_version_id
           INNER JOIN catalog_products cp ON cp.current_version_id = cpv.id
           WHERE pva.asset_id = ca.id
             AND cpv.version_status = 'published'
             AND cp.status = 'published'
         )
       )
     LIMIT 1"
);
$stmt->execute([$assetId, $userId]);
$asset = $stmt->fetch();
if (!$asset || (string) $asset['storage_provider'] !== 'private_local') {
    mg_fail('Asset not found.', 404);
}

$storageRoot = realpath(dirname(__DIR__, 2) . '/storage/private');
$path = realpath(dirname(__DIR__, 2) . '/storage/private/' . ltrim((string) $asset['storage_key'], '/'));
if ($storageRoot === false || $path === false || !str_starts_with($path, $storageRoot . DIRECTORY_SEPARATOR) || !is_file($path)) {
    mg_fail('Asset file is unavailable.', 404);
}

$size = filesize($path);
if ($size === false) {
    mg_fail('Asset file is unavailable.', 404);
}

header('Content-Type: ' . ((string) $asset['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . $size);
header($userId > 0 ? 'Cache-Control: private, max-age=300' : 'Cache-Control: public, max-age=300, stale-while-revalidate=300');
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . rawurlencode((string) ($asset['original_filename'] ?: 'asset')) . '"');
readfile($path);
exit;
