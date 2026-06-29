<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/pwa-branding.php';
mg_require_method('GET');
try {
    $payload = mg_pwa_branding_payload(mg_db());
    $assets = $payload['assets'] ?? [];
    $data = [
        'manifest_url' => $payload['manifest_url'] ?? '/manifest.php',
        'splash_url' => $payload['splash_url'] ?? '/pwa-splash.php',
        'icon' => $assets['notification_icon']['asset']['url'] ?? $assets['app_icon_192']['asset']['url'] ?? '/images/logo_main_drk.png',
        'badge' => $assets['notification_badge']['asset']['url'] ?? $assets['notification_icon']['asset']['url'] ?? '/images/logo_main_drk.png',
        'apple_touch_icon' => $assets['apple_touch_icon']['asset']['url'] ?? $assets['app_icon_192']['asset']['url'] ?? '/images/logo_main_drk.png',
        'asset_version' => $payload['settings']['asset_version'] ?? '1',
    ];
} catch (Throwable $e) {
    $data = ['manifest_url'=>'/manifest.php','splash_url'=>'/pwa-splash.php','icon'=>'/images/logo_main_drk.png','badge'=>'/images/logo_main_drk.png','apple_touch_icon'=>'/images/logo_main_drk.png','asset_version'=>'1'];
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300, stale-while-revalidate=3600');
header('X-Content-Type-Options: nosniff');
echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_SLASHES);
