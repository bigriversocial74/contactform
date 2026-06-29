<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/pwa-branding.php';
try { $payload = mg_pwa_branding_payload(mg_db()); } catch (Throwable $e) { $payload = ['settings'=>mg_pwa_branding_defaults(),'assets'=>[],'manifest_url'=>'/manifest.php']; }
$s = $payload['settings'];
$a = $payload['assets'] ?? [];
$logo = $a['splash_logo']['asset']['url'] ?? $a['app_icon_512']['asset']['url'] ?? '/images/logo_main_drk.png';
$background = $a['splash_background']['asset']['url'] ?? '';
$apple = $a['apple_touch_icon']['asset']['url'] ?? $a['app_icon_192']['asset']['url'] ?? '/images/logo_main_drk.png';
$theme = mg_pwa_branding_hex((string)($s['theme_color'] ?? '#2563eb'), '#2563eb');
$bg = mg_pwa_branding_hex((string)($s['background_color'] ?? '#f8fafc'), '#f8fafc');
$cta = mg_pwa_branding_path((string)($s['splash_cta_url'] ?? '/notifications.php'), '/notifications.php');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= mg_e((string)$s['app_name']) ?> | PWA</title>
<meta name="theme-color" content="<?= mg_e($theme) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= mg_e((string)$s['short_name']) ?>">
<link rel="manifest" href="<?= mg_e((string)($payload['manifest_url'] ?? '/manifest.php')) ?>">
<link rel="apple-touch-icon" href="<?= mg_e($apple) ?>">
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,Arial,sans-serif;background:<?= mg_e($bg) ?>;color:#0f172a}.mg-pwa-splash{min-height:100vh;display:grid;place-items:center;padding:28px;position:relative;overflow:hidden}.mg-pwa-splash:before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 20% 15%,rgba(37,99,235,.2),transparent 32%),linear-gradient(135deg,rgba(255,255,255,.85),rgba(241,245,249,.94));z-index:-2}.mg-pwa-splash-bg{position:absolute;inset:0;background:center/cover no-repeat;opacity:.2;z-index:-1}.mg-pwa-card{width:min(760px,100%);border:1px solid rgba(148,163,184,.34);border-radius:32px;background:rgba(255,255,255,.88);box-shadow:0 30px 90px rgba(15,23,42,.18);padding:clamp(28px,5vw,56px);text-align:center;backdrop-filter:blur(16px)}.mg-pwa-logo{width:128px;height:128px;object-fit:contain;border-radius:30px;background:#fff;padding:14px;box-shadow:0 18px 46px rgba(15,23,42,.16)}.mg-pwa-eyebrow{display:inline-flex;margin:24px 0 12px;padding:8px 12px;border-radius:999px;background:#fff;color:<?= mg_e($theme) ?>;font-size:12px;font-weight:950;letter-spacing:.14em;text-transform:uppercase}.mg-pwa-card h1{margin:0;font-size:clamp(38px,8vw,72px);line-height:.95;letter-spacing:-.075em}.mg-pwa-card p{max-width:600px;margin:18px auto 0;color:#64748b;font-size:clamp(16px,2.2vw,20px);line-height:1.55;font-weight:750}.mg-pwa-actions{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;margin-top:28px}.mg-pwa-btn{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 20px;border-radius:999px;border:1px solid rgba(148,163,184,.34);background:#fff;color:#0f172a;text-decoration:none;font-weight:950}.mg-pwa-btn-primary{background:<?= mg_e($theme) ?>;border-color:<?= mg_e($theme) ?>;color:#fff}.mg-pwa-meta{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-top:22px;color:#64748b;font-size:12px;font-weight:900}.mg-pwa-meta span{padding:7px 10px;border-radius:999px;background:rgba(255,255,255,.75);border:1px solid rgba(226,232,240,.9)}@media(max-width:640px){.mg-pwa-actions{display:grid}.mg-pwa-btn{width:100%}.mg-pwa-logo{width:106px;height:106px}}
</style>
</head>
<body>
<main class="mg-pwa-splash">
<?php if ($background !== ''): ?><div class="mg-pwa-splash-bg" style="background-image:url('<?= mg_e($background) ?>')" aria-hidden="true"></div><?php endif; ?>
<section class="mg-pwa-card" aria-label="Microgifter PWA launch">
<img class="mg-pwa-logo" src="<?= mg_e($logo) ?>" alt="<?= mg_e((string)$s['app_name']) ?>">
<span class="mg-pwa-eyebrow">Installable workspace</span>
<h1><?= mg_e((string)$s['splash_title']) ?></h1>
<p><?= mg_e((string)$s['splash_subtitle']) ?></p>
<div class="mg-pwa-actions"><a class="mg-pwa-btn mg-pwa-btn-primary" href="<?= mg_e($cta) ?>"><?= mg_e((string)$s['splash_cta_label']) ?></a><a class="mg-pwa-btn" href="/index.php">Microgifter home</a></div>
<div class="mg-pwa-meta"><span>Gifts</span><span>Rewards</span><span>Claims</span><span>Campaigns</span><span>Admin alerts</span></div>
</section>
</main>
</body>
</html>
