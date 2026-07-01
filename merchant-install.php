<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/merchant-pwa.php';

$merchantSlug = trim((string)($_GET['merchant'] ?? ''));
try {
    $profile = mg_merchant_pwa_profile_by_slug(mg_db(), $merchantSlug);
    $payload = mg_merchant_pwa_public_payload(mg_db(), $profile);
} catch (Throwable $e) {
    http_response_code(404);
    $page_title = 'Merchant app not found | Microgifter';
    require __DIR__ . '/includes/header.php';
    echo '<main class="mg-public-empty"><h1>Merchant app not found</h1><p>This branded Microgifter app link is not available.</p><a class="mg-btn mg-btn-primary" href="/">Go to Microgifter</a></main>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$p = $payload['profile'];
$m = $payload['merchant'];
$theme = $p['theme_color'];
$bg = $p['background_color'];
$logo = $p['logo_url'];
$background = $p['background_url'];
$manifest = $p['manifest_url'];
$appUrl = $p['start_url'];
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= mg_e($p['app_name']) ?> | Powered by Microgifter</title>
  <meta name="theme-color" content="<?= mg_e($theme) ?>">
  <meta name="description" content="<?= mg_e($p['description']) ?>">
  <link rel="manifest" href="<?= mg_e($manifest) ?>">
  <link rel="stylesheet" href="/assets/css/merchant-pwa.css">
  <script src="/assets/js/merchant-pwa-install.js" defer></script>
  <style>:root{--merchant-pwa-theme:<?= mg_e($theme) ?>;--merchant-pwa-bg:<?= mg_e($bg) ?>}</style>
</head>
<body class="mg-merchant-pwa-public" data-merchant-pwa-install>
  <main class="mg-merchant-pwa-install">
    <?php if ($background): ?><img class="mg-merchant-pwa-bg" src="<?= mg_e($background) ?>" alt=""><?php endif; ?>
    <section class="mg-merchant-pwa-card">
      <div class="mg-merchant-pwa-powered">Powered by Microgifter</div>
      <div class="mg-merchant-pwa-logo-wrap"><img src="<?= mg_e($logo) ?>" alt="<?= mg_e($m['display_name']) ?> logo"></div>
      <span class="mg-eyebrow">Merchant rewards app</span>
      <h1><?= mg_e($p['install_headline']) ?></h1>
      <p><?= mg_e($p['install_subtitle']) ?></p>
      <div class="mg-merchant-pwa-actions">
        <button class="mg-btn mg-btn-primary" type="button" data-pwa-install-button>Install app</button>
        <a class="mg-btn mg-btn-soft" href="<?= mg_e($appUrl) ?>">Open app</a>
      </div>
      <div class="mg-merchant-pwa-feature-grid">
        <div><strong>Gift inbox</strong><span>Track available rewards.</span></div>
        <div><strong>Claim reminders</strong><span>Get timely alerts.</span></div>
        <div><strong>Send gifts</strong><span>Share this merchant.</span></div>
        <div><strong>Local offers</strong><span>Return when ready.</span></div>
      </div>
      <p class="mg-merchant-pwa-hint" data-pwa-install-hint>Use your browser menu to add this merchant app to your home screen if the install prompt does not appear.</p>
    </section>
    <aside class="mg-merchant-pwa-phone" aria-hidden="true">
      <div class="mg-merchant-pwa-phone-screen">
        <img src="<?= mg_e($logo) ?>" alt="">
        <strong><?= mg_e($p['short_name']) ?></strong>
        <span>Rewards • Claims • Gifts</span>
        <div class="mg-merchant-pwa-phone-pills"><i></i><i></i><i></i></div>
      </div>
    </aside>
  </main>
</body>
</html>
