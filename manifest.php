<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/pwa-branding.php';

try {
    $manifest = mg_pwa_branding_manifest(mg_db());
} catch (Throwable $e) {
    $manifest = [
        'name'=>'Microgifter','short_name'=>'Microgifter','description'=>'Microgifter PWA workspace for gifts, claims, campaigns, merchant alerts, and admin operations.',
        'start_url'=>'/notifications.php','scope'=>'/','display'=>'standalone','background_color'=>'#f8fafc','theme_color'=>'#2563eb',
        'icons'=>[['src'=>'/images/logo_main_drk.png','sizes'=>'192x192','type'=>'image/png','purpose'=>'any maskable']],
    ];
}
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, max-age=0, must-revalidate');
header('X-Content-Type-Options: nosniff');
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
