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
$manifest = $p['manifest_url'];
$user = function_exists('mg_current_user') ? mg_current_user() : null;
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= mg_e($p['app_name']) ?></title>
  <meta name="theme-color" content="<?= mg_e($theme) ?>">
  <meta name="description" content="<?= mg_e($p['description']) ?>">
  <link rel="manifest" href="<?= mg_e($manifest) ?>">
  <link rel="stylesheet" href="/assets/css/merchant-pwa.css">
  <style>:root{--merchant-pwa-theme:<?= mg_e($theme) ?>;--merchant-pwa-bg:<?= mg_e($bg) ?>}</style>
</head>
<body class="mg-merchant-pwa-public mg-merchant-pwa-app-body">
  <main class="mg-merchant-pwa-app">
    <header class="mg-merchant-pwa-app-hero">
      <div class="mg-merchant-pwa-logo-wrap"><img src="<?= mg_e($logo) ?>" alt="<?= mg_e($m['display_name']) ?> logo"></div>
      <div>
        <span class="mg-eyebrow">Powered by Microgifter</span>
        <h1><?= mg_e($p['splash_title']) ?></h1>
        <p><?= mg_e($p['splash_subtitle']) ?></p>
      </div>
    </header>
    <section class="mg-merchant-pwa-app-grid">
      <a id="rewards" class="mg-merchant-pwa-action-card" href="/inbox.php"><strong>My Rewards</strong><span>Open available gifts and rewards.</span></a>
      <a id="claim" class="mg-merchant-pwa-action-card" href="/claim.php"><strong>Claim Gift</strong><span>Open claim tools and QR flow.</span></a>
      <a id="send" class="mg-merchant-pwa-action-card" href="/catalog.php"><strong>Send Gift</strong><span>Send this merchant to someone.</span></a>
      <a class="mg-merchant-pwa-action-card" href="/notifications.php"><strong>Notifications</strong><span>Enable browser alerts and reminders.</span></a>
      <a class="mg-merchant-pwa-action-card" href="/merchant-storefront.php"><strong>Merchant Storefront</strong><span>View products and offers.</span></a>
      <a class="mg-merchant-pwa-action-card" href="/feed.php"><strong>Microgifter Feed</strong><span>Discover new local activity.</span></a>
    </section>
    <footer class="mg-merchant-pwa-app-footer">
      <?php if ($user): ?>
        <span>Signed in to Microgifter</span>
      <?php else: ?>
        <a href="/signin.php">Sign in</a><span>to sync rewards across devices.</span>
      <?php endif; ?>
    </footer>
  </main>
</body>
</html>
