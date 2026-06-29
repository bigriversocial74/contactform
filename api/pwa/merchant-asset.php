<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/app.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-pwa.php';

$merchant = trim((string)($_GET['merchant'] ?? ''));
$role = trim((string)($_GET['role'] ?? ''));
if ($merchant === '' || $role === '') mg_fail('Merchant PWA asset not found.', 404);

try {
    $asset = mg_merchant_pwa_public_asset(mg_db(), $merchant, $role);
    header('Content-Type: ' . $asset['mime_type']);
    header('Content-Length: ' . (string)$asset['byte_size']);
    header('Cache-Control: public, max-age=86400, immutable');
    header('X-Content-Type-Options: nosniff');
    readfile($asset['path']);
    exit;
} catch (Throwable $e) {
    mg_fail('Merchant PWA asset not found.', 404);
}
