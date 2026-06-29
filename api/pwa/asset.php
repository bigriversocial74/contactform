<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/pwa-branding.php';

mg_require_method('GET');
$id = strtolower(trim((string)($_GET['id'] ?? '')));
if (strlen($id) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) mg_fail('Invalid PWA asset identifier.',422);
$asset = mg_pwa_branding_public_asset(mg_db(), $id);
if (!$asset) mg_fail('PWA asset not found.',404);
$root = realpath(dirname(__DIR__, 2) . '/storage/public');
$path = realpath(dirname(__DIR__, 2) . '/storage/public/' . ltrim((string)$asset['storage_key'], '/'));
if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) mg_fail('PWA asset file is unavailable.',404);
$size = filesize($path);
if ($size === false) mg_fail('PWA asset file is unavailable.',404);
header('Content-Type: ' . ((string)$asset['mime_type'] ?: 'image/png'));
header('Content-Length: ' . $size);
header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . rawurlencode((string)($asset['original_filename'] ?: 'pwa-asset.png')) . '"');
readfile($path);
exit;
