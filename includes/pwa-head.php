<?php
declare(strict_types=1);

require_once __DIR__ . '/pwa-branding.php';

$pwaHeadSettings = mg_pwa_branding_defaults();
$pwaHeadAssets = [];
$pwaHeadManifestUrl = '/manifest.php';
$pwaHeadAppleIcon = '/images/logo_main_drk.png';

try {
    $pwaHeadPayload = mg_pwa_branding_payload(mg_db());
    $pwaHeadSettings = is_array($pwaHeadPayload['settings'] ?? null) ? $pwaHeadPayload['settings'] : $pwaHeadSettings;
    $pwaHeadAssets = is_array($pwaHeadPayload['assets'] ?? null) ? $pwaHeadPayload['assets'] : [];
    $pwaHeadManifestUrl = (string)($pwaHeadPayload['manifest_url'] ?? '/manifest.php');
    $pwaHeadAppleIcon = (string)(
        $pwaHeadAssets['apple_touch_icon']['asset']['url']
        ?? $pwaHeadAssets['app_icon_192']['asset']['url']
        ?? $pwaHeadAssets['app_icon_512']['asset']['url']
        ?? $pwaHeadAssets['splash_logo']['asset']['url']
        ?? '/images/logo_main_drk.png'
    );
} catch (Throwable) {
    $pwaHeadManifestUrl = '/manifest.php';
    $pwaHeadAppleIcon = '/images/logo_main_drk.png';
}

$pwaHeadTheme = mg_pwa_branding_hex((string)($pwaHeadSettings['theme_color'] ?? '#2563eb'), '#2563eb');
$pwaHeadBg = mg_pwa_branding_hex((string)($pwaHeadSettings['background_color'] ?? '#f8fafc'), '#f8fafc');
$pwaHeadAppName = trim((string)($pwaHeadSettings['app_name'] ?? 'Microgifter')) ?: 'Microgifter';
$pwaHeadShortName = trim((string)($pwaHeadSettings['short_name'] ?? $pwaHeadAppName)) ?: $pwaHeadAppName;
?>
<link rel="manifest" href="<?= mg_e($pwaHeadManifestUrl) ?>">
<meta name="theme-color" content="<?= mg_e($pwaHeadTheme) ?>">
<meta name="application-name" content="<?= mg_e($pwaHeadAppName) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= mg_e($pwaHeadShortName) ?>">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="msapplication-TileColor" content="<?= mg_e($pwaHeadBg) ?>">
<link rel="apple-touch-icon" href="<?= mg_e($pwaHeadAppleIcon) ?>">
