<?php
declare(strict_types=1);

if (!is_file(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

require __DIR__ . '/app.php';
require __DIR__ . '/quest-controls.php';

$config = lqr_config();
$appName = trim((string)($config['app_name'] ?? 'Local Quest Rewards')) ?: 'Local Quest Rewards';
$appUrl = rtrim((string)($config['app_public_url'] ?? ''), '/');
$isAuthed = lqr_is_authenticated();
$quests = lqr_visible_quests(lqr_quests(), lqr_default_state());

uasort($quests, static function(array $left, array $right): int {
    $leftFeatured = !empty(lqr_quest_controls($left)['featured']);
    $rightFeatured = !empty(lqr_quest_controls($right)['featured']);
    if ($leftFeatured === $rightFeatured) return 0;
    return $leftFeatured ? -1 : 1;
});
$featuredQuests = array_slice($quests, 0, 3, true);

$canonical = $appUrl !== '' ? $appUrl . '/cover.php' : '';
$description = 'Discover local quests, complete verified actions, and earn merchant-approved Microgift rewards through a connected reward wallet.';
$structured = [
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => $appName,
    'applicationCategory' => 'LifestyleApplication',
    'description' => $description,
    'operatingSystem' => 'Web',
    'url' => $canonical ?: null,
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= lqr_h($appName) ?> | Complete local quests. Earn real rewards.</title>
<meta name="description" content="<?= lqr_h($description) ?>">
<meta name="theme-color" content="#f7f8f2">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= lqr_h($appName) ?>">
<meta property="og:description" content="<?= lqr_h($description) ?>">
<?php if ($canonical !== ''): ?><meta property="og:url" content="<?= lqr_h($canonical) ?>"><link rel="canonical" href="<?= lqr_h($canonical) ?>"><?php endif; ?>
<link rel="stylesheet" href="assets/public-site.css">
<script type="application/ld+json"><?= json_encode($structured, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
  <div class="site-width header-inner">
    <a class="brand" href="cover.php" aria-label="<?= lqr_h($appName) ?> home">
      <span class="brand-mark" aria-hidden="true">LQ</span>
      <span class="brand-copy"><strong><?= lqr_h($appName) ?></strong><small>Powered by Microgifter</small></span>
    </a>
    <nav class="desktop-nav" aria-label="Primary navigation">
      <a href="#quests">Quests</a>
      <a href="#how-it-works">How it works</a>
      <a href="#wallet">Reward wallet</a>
      <a href="#partners">For partners</a>
    </nav>
    <div class="header-actions">
      <?php if ($isAuthed): ?>
        <a class="button button-soft" href="wallet.php">My wallet</a>
        <a class="button button-primary" href="index.php">Open quest board</a>
      <?php else: ?>
        <a class="button button-soft" href="signin.php">Sign in</a>
        <a class="button button-primary" href="signin.php?mode=signup">Start exploring</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main id="main">
  <section class="hero site-width">
    <div class="hero-copy">
      <span class="eyebrow"><span></span> Local experiences with real rewards</span>
      <h1>Turn the places you visit into quests worth completing.</h1>
      <p class="hero-lead">Discover participating merchants, complete verified local actions, and unlock merchant-approved Microgift rewards delivered to your connected wallet.</p>
      <div class="hero-actions">
        <a class="button button-primary button-large" href="<?= $isAuthed ? 'index.php' : 'signin.php?mode=signup' ?>"><?= $isAuthed ? 'Open quest board' : 'Create a free account' ?></a>
        <a class="text-link" href="#how-it-works">See how rewards work <span aria-hidden="true">→</span></a>
      </div>
      <div class="trust-row" aria-label="Platform benefits">
        <span><b>✓</b> Verified quest actions</span>
        <span><b>✓</b> Merchant-approved offers</span>
        <span><b>✓</b> Connected reward wallet</span>
      </div>
    </div>

    <div class="hero-visual" aria-label="Local Quest experience preview">
      <div class="map-orbit orbit-one"></div>
      <div class="map-orbit orbit-two"></div>
      <div class="map-pin pin-one"><span></span><small>Coffee</small></div>
      <div class="map-pin pin-two"><span></span><small>Music</small></div>
      <div class="map-pin pin-three"><span></span><small>Dining</small></div>
      <article class="quest-preview-card">
        <div class="preview-top"><span class="mini-logo">LQ</span><span class="preview-status">Quest nearby</span></div>
        <div class="preview-image">
          <span class="preview-badge">Featured</span>
          <div class="preview-art">☕</div>
        </div>
        <div class="preview-body">
          <span class="preview-category">Downtown · Easy</span>
          <h2>Morning Coffee Check-in</h2>
          <p>Visit a participating café and scan the verified quest code.</p>
          <div class="reward-row"><span>$5 Coffee Microgift</span><strong>Earn reward</strong></div>
        </div>
      </article>
    </div>
  </section>

  <section class="proof-strip">
    <div class="site-width proof-grid">
      <div><strong>Discover</strong><span>Local quests from participating merchants.</span></div>
      <div><strong>Complete</strong><span>QR, location, milestone, and event actions.</span></div>
      <div><strong>Connect</strong><span>Your Microgifter identity with clear consent.</span></div>
      <div><strong>Collect</strong><span>Track and claim rewards from one wallet.</span></div>
    </div>
  </section>

  <section class="section site-width" id="quests">
    <div class="section-heading split-heading">
      <div><span class="eyebrow"><span></span> Explore what is nearby</span><h2>Featured local quests</h2></div>
      <p>Every quest defines the action, sponsor, schedule, limits, and reward before you participate.</p>
    </div>
    <div class="quest-grid">
      <?php if (!$featuredQuests): ?>
        <article class="empty-card"><span class="quest-icon">✦</span><h3>New quests are being prepared.</h3><p>Check back soon for merchant-sponsored local challenges and rewards.</p></article>
      <?php endif; ?>
      <?php foreach ($featuredQuests as $questId => $quest): $controls = lqr_quest_controls($quest); ?>
        <article class="quest-card">
          <div class="quest-card-top">
            <span class="quest-icon"><?= lqr_h(match ((string)($quest['difficulty'] ?? '')) { 'Easy' => '☕', 'Medium' => '♫', default => '⌁' }) ?></span>
            <?php if (!empty($controls['featured'])): ?><span class="tag">Featured</span><?php else: ?><span class="tag tag-soft">Open</span><?php endif; ?>
          </div>
          <span class="quest-location"><?= lqr_h((string)($quest['location'] ?? 'Local destination')) ?></span>
          <h3><?= lqr_h((string)($quest['title'] ?? $questId)) ?></h3>
          <p><?= lqr_h((string)($quest['description'] ?? 'Complete this local quest to unlock a merchant reward.')) ?></p>
          <div class="quest-meta"><span><?= lqr_h((string)($quest['difficulty'] ?? 'Quest')) ?></span><span><?= lqr_h((string)($controls['sponsor'] ?: ($quest['merchant'] ?? 'Local sponsor'))) ?></span></div>
          <div class="quest-reward"><small>Reward</small><strong><?= lqr_h((string)($quest['reward_label'] ?? 'Microgift reward')) ?></strong></div>
        </article>
      <?php endforeach; ?>
    </div>
    <div class="center-action"><a class="button button-dark" href="<?= $isAuthed ? 'index.php' : 'signin.php?mode=signup' ?>">Browse the quest board</a></div>
  </section>

  <section class="section section-soft" id="how-it-works">
    <div class="site-width">
      <div class="section-heading centered"><span class="eyebrow"><span></span> Simple participant flow</span><h2>From local action to connected reward.</h2><p>Your Quest account manages participation. Microgifter remains the system of record for reward delivery, status, claims, and lifecycle history.</p></div>
      <div class="steps-grid">
        <article><span class="step-number">01</span><h3>Create your Quest account</h3><p>Build a local participant profile and keep your quest progress in one place.</p></article>
        <article><span class="step-number">02</span><h3>Connect Microgifter</h3><p>Approve the connection so eligible merchant rewards can reach the correct wallet.</p></article>
        <article><span class="step-number">03</span><h3>Complete verified actions</h3><p>Use QR, geolocation, event, or milestone evidence defined by each quest.</p></article>
        <article><span class="step-number">04</span><h3>Receive and claim rewards</h3><p>Follow reward status, claim instructions, and merchant redemption activity.</p></article>
      </div>
    </div>
  </section>

  <section class="section site-width wallet-section" id="wallet">
    <div class="wallet-copy">
      <span class="eyebrow"><span></span> Your reward history</span>
      <h2>A wallet built for what happens after the quest.</h2>
      <p>See which quest created each reward, follow delivery and claim status, and keep the connected Microgift lifecycle organized.</p>
      <ul class="feature-list">
        <li><span>01</span><div><strong>Reward ownership</strong><small>Each reward stays tied to the participant and originating quest.</small></div></li>
        <li><span>02</span><div><strong>Status clarity</strong><small>Track issue, delivery, claim reporting, and webhook-confirmed updates.</small></div></li>
        <li><span>03</span><div><strong>Merchant instructions</strong><small>Keep the reward details needed for the participating location.</small></div></li>
      </ul>
      <a class="button button-primary" href="<?= $isAuthed ? 'wallet.php' : 'signin.php?mode=signup' ?>"><?= $isAuthed ? 'Open my reward wallet' : 'Create an account' ?></a>
    </div>
    <div class="wallet-visual">
      <div class="wallet-phone">
        <div class="wallet-head"><span>Reward wallet</span><b>3 active</b></div>
        <div class="wallet-item"><span class="wallet-art coffee">☕</span><div><small>Coffee Quest</small><strong>$5 Coffee Microgift</strong><em>Available</em></div></div>
        <div class="wallet-item"><span class="wallet-art music">♫</span><div><small>Live Music Night</small><strong>Free Appetizer</strong><em>Delivered</em></div></div>
        <div class="wallet-item"><span class="wallet-art dining">⌁</span><div><small>Food Crawl</small><strong>$10 Dining Microgift</strong><em>Claim reported</em></div></div>
      </div>
    </div>
  </section>

  <section class="section partner-section" id="partners">
    <div class="site-width partner-inner">
      <div><span class="eyebrow light"><span></span> Merchant and community programs</span><h2>Create repeatable local engagement—not another coupon page.</h2><p>Quest operators can organize sponsors, verified actions, reward limits, participant history, claim reporting, and Microgifter Distribution API activity from one application foundation.</p></div>
      <div class="partner-actions"><a class="button button-lime" href="how-it-works.php">View the platform lifecycle</a><a class="button button-outline" href="demo.php">Developer demo</a></div>
    </div>
  </section>

  <section class="section site-width final-cta">
    <span class="eyebrow"><span></span> Start your first quest</span>
    <h2>Explore locally. Complete something real. Earn a reward you can use.</h2>
    <div class="hero-actions"><a class="button button-primary button-large" href="<?= $isAuthed ? 'index.php' : 'signin.php?mode=signup' ?>"><?= $isAuthed ? 'Open quest board' : 'Create your Quest account' ?></a><a class="text-link" href="signin.php">Already have an account? Sign in</a></div>
  </section>
</main>

<footer class="site-footer">
  <div class="site-width footer-grid">
    <div class="brand footer-brand"><span class="brand-mark">LQ</span><span class="brand-copy"><strong><?= lqr_h($appName) ?></strong><small>Local actions. Connected rewards.</small></span></div>
    <div class="footer-links"><a href="cover.php">Home</a><a href="signin.php">Sign in</a><a href="how-it-works.php">How it works</a><a href="wallet.php">Wallet</a></div>
    <p>Reward delivery and lifecycle services powered by Microgifter.</p>
  </div>
</footer>
</body>
</html>
