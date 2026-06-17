<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog.php';

mg_require_method('GET');
$user = mg_require_permission('catalog.assets.manage');
$assetId = strtolower(trim((string) ($_GET['id'] ?? '')));
if (strlen($assetId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $assetId)) {
    mg_fail('Invalid asset identifier.', 422);
}

$stmt = mg_db()->prepare(
    "SELECT storage_provider, storage_key, original_filename, mime_type, byte_size, checksum_sha256
     FROM catalog_assets
     WHERE public_id = ? AND owner_user_id = ? AND status = 'ready'
     LIMIT 1"
);
$stmt->execute([$assetId, (int) $user['id']]);
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
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . rawurlencode((string) ($asset['original_filename'] ?: 'asset')) . '"');
readfile($path);
exit;
