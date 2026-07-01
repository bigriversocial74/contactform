<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/merchant-pwa.php';

$merchant = trim((string)($_GET['merchant'] ?? ''));
try {
    $manifest = mg_merchant_pwa_manifest(mg_db(), $merchant);
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(404);
    header('Content-Type: application/manifest+json; charset=utf-8');
    echo json_encode([
        'name' => 'Microgifter Merchant App',
        'short_name' => 'Microgifter',
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'theme_color' => '#2563eb',
        'background_color' => '#f8fafc',
        'icons' => [['src' => '/images/logo_main_drk.png', 'sizes' => '192x192', 'type' => 'image/png']],
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
