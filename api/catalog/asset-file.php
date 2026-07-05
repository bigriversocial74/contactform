<?php
declare(strict_types=1);

require_once __DIR__ . '/_catalog.php';

function mg_catalog_asset_file_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $columnName)) return false;
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->execute([$tableName, $columnName]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function mg_catalog_asset_file_safe_name(string $name, string $mime): string
{
    $name = trim(basename($name));
    $name = preg_replace('/[\x00-\x1F\x7F]+/', '', $name) ?? '';
    $name = preg_replace('/[^A-Za-z0-9._() -]+/', '-', $name) ?? '';
    $name = trim($name, " .-\t\n\r\0\x0B");
    if ($name === '') {
        $fallback = match ($mime) {
            'image/jpeg' => 'asset.jpg',
            'image/png' => 'asset.png',
            'image/webp' => 'asset.webp',
            'image/gif' => 'asset.gif',
            'audio/mpeg' => 'asset.mp3',
            'audio/mp4' => 'asset.m4a',
            'audio/wav', 'audio/x-wav' => 'asset.wav',
            'audio/ogg' => 'asset.ogg',
            'video/mp4' => 'asset.mp4',
            'video/webm' => 'asset.webm',
            'video/quicktime' => 'asset.mov',
            default => 'asset.bin',
        };
        $name = $fallback;
    }
    if (strlen($name) > 180) {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME) ?: 'asset';
        $name = substr($base, 0, 140) . ($extension !== '' ? '.' . substr($extension, 0, 20) : '');
    }
    return $name;
}

function mg_catalog_asset_file_disposition(string $filename): string
{
    $ascii = preg_replace('/[^A-Za-z0-9._() -]+/', '-', $filename) ?: 'asset.bin';
    $ascii = str_replace(['\\', '"'], ['-', '\\"'], $ascii);
    return 'inline; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
}

function mg_catalog_asset_file_send_common_headers(array $asset, int $size, bool $isOwner): void
{
    $mime = (string)($asset['mime_type'] ?: 'application/octet-stream');
    $filename = mg_catalog_asset_file_safe_name((string)($asset['original_filename'] ?: 'asset'), $mime);
    $checksum = strtolower((string)($asset['checksum_sha256'] ?? ''));
    $etagSource = preg_match('/^[a-f0-9]{64}$/', $checksum) ? $checksum : hash('sha256', (string)$asset['storage_key'] . '|' . $size);
    $etag = '"catalog-' . substr($etagSource, 0, 32) . '-' . $size . '"';

    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . mg_catalog_asset_file_disposition($filename));
    header('ETag: ' . $etag);
    header('Vary: Cookie, Authorization');
    header('Referrer-Policy: no-referrer');
    header('Cross-Origin-Resource-Policy: same-origin');
    header($isOwner ? 'Cache-Control: private, max-age=300, must-revalidate' : 'Cache-Control: public, max-age=300, stale-while-revalidate=300');

    $clientEtag = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($clientEtag !== '' && hash_equals($etag, $clientEtag)) {
        http_response_code(304);
        exit;
    }
}

function mg_catalog_asset_file_parse_range(string $rangeHeader, int $size): ?array
{
    if ($rangeHeader === '' || !preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $matches)) {
        return null;
    }
    $startRaw = $matches[1];
    $endRaw = $matches[2];
    if ($startRaw === '' && $endRaw === '') return null;

    if ($startRaw === '') {
        $suffix = (int)$endRaw;
        if ($suffix < 1) return null;
        $start = max(0, $size - $suffix);
        $end = $size - 1;
    } else {
        $start = (int)$startRaw;
        $end = $endRaw === '' ? ($size - 1) : (int)$endRaw;
    }

    if ($start < 0 || $end < $start || $start >= $size) return null;
    $end = min($end, $size - 1);
    return [$start, $end];
}

function mg_catalog_asset_file_stream(string $path, int $start, int $length): void
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        mg_fail('Asset file is unavailable.', 404);
    }
    if ($start > 0) {
        fseek($handle, $start);
    }
    $remaining = $length;
    while ($remaining > 0 && !feof($handle)) {
        $chunkSize = min(8192, $remaining);
        $buffer = fread($handle, $chunkSize);
        if ($buffer === false || $buffer === '') break;
        echo $buffer;
        $remaining -= strlen($buffer);
        if (connection_aborted()) break;
    }
    fclose($handle);
}

mg_require_method('GET');
$assetId = strtolower(trim((string) ($_GET['id'] ?? '')));
if (strlen($assetId) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $assetId)) {
    mg_fail('Invalid asset identifier.', 422);
}

$pdo = mg_db();
$user = mg_current_user();
$userId = (int) ($user['id'] ?? 0);
$deletedFilter = mg_catalog_asset_file_column_exists($pdo, 'catalog_assets', 'deleted_at') ? ' AND ca.deleted_at IS NULL' : '';
$stmt = $pdo->prepare(
    "SELECT ca.storage_provider, ca.storage_key, ca.original_filename, ca.mime_type, ca.byte_size, ca.checksum_sha256,
            CASE WHEN ca.owner_user_id = ? THEN 1 ELSE 0 END is_owner,
            CASE WHEN EXISTS (
              SELECT 1
              FROM catalog_product_version_assets pva
              INNER JOIN catalog_product_versions cpv ON cpv.id = pva.product_version_id
              INNER JOIN catalog_products cp ON cp.current_version_id = cpv.id
              WHERE pva.asset_id = ca.id
                AND cpv.version_status = 'published'
                AND cp.status = 'published'
            ) THEN 1 ELSE 0 END is_published
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
$stmt->execute([$userId, $assetId, $userId]);
$asset = $stmt->fetch();
if (!$asset || (string) $asset['storage_provider'] !== 'private_local') {
    mg_fail('Asset not found.', 404);
}

$storageRoot = realpath(dirname(__DIR__, 2) . '/storage/private');
$storageKey = ltrim((string) $asset['storage_key'], '/');
if ($storageKey === '' || str_contains($storageKey, "\0") || str_contains($storageKey, '..')) {
    mg_security_log('warning', 'catalog.asset_file.invalid_storage_key', 'Catalog asset has an invalid storage key.', ['asset_id' => $assetId], $userId ?: null);
    mg_fail('Asset file is unavailable.', 404);
}
$path = realpath(dirname(__DIR__, 2) . '/storage/private/' . $storageKey);
if ($storageRoot === false || $path === false || !str_starts_with($path, $storageRoot . DIRECTORY_SEPARATOR) || !is_file($path) || !is_readable($path)) {
    mg_fail('Asset file is unavailable.', 404);
}

$size = filesize($path);
if ($size === false || $size < 0) {
    mg_fail('Asset file is unavailable.', 404);
}
$expectedSize = (int)($asset['byte_size'] ?? 0);
if ($expectedSize > 0 && $expectedSize !== (int)$size) {
    mg_security_log('warning', 'catalog.asset_file.byte_size_mismatch', 'Catalog asset byte size mismatch detected.', ['asset_id' => $assetId, 'expected' => $expectedSize, 'actual' => (int)$size], $userId ?: null);
    mg_fail('Asset file is unavailable.', 404);
}

$isOwner = ((int)($asset['is_owner'] ?? 0)) === 1;
mg_catalog_asset_file_send_common_headers($asset, (int)$size, $isOwner);

$rangeHeader = (string)($_SERVER['HTTP_RANGE'] ?? '');
if ($rangeHeader !== '') {
    $range = mg_catalog_asset_file_parse_range($rangeHeader, (int)$size);
    if ($range === null) {
        header('Content-Range: bytes */' . (int)$size);
        http_response_code(416);
        exit;
    }
    [$start, $end] = $range;
    $length = $end - $start + 1;
    http_response_code(206);
    header('Accept-Ranges: bytes');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . (int)$size);
    header('Content-Length: ' . $length);
    mg_catalog_asset_file_stream($path, $start, $length);
    exit;
}

header('Accept-Ranges: bytes');
header('Content-Length: ' . (int)$size);
mg_catalog_asset_file_stream($path, 0, (int)$size);
exit;
