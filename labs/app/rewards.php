<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'Rewards | Training Lab by Microgifter',
    'section' => 'app',
    'active' => 'app-rewards',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Reward progress</span>
    <h1>Rewards and milestones</h1>
    <p class="labs-copy">Visual reward states only. No real rewards are issued in Stage 1.</p>
  </div>
</section>
<section class="labs-grid">
  <article class="labs-card"><span class="labs-mini-icon">★</span><h2>Movement Reward</h2><p class="labs-muted">Unlocks after review approval.</p><span class="labs-pill">Pending</span></article>
  <article class="labs-card"><span class="labs-mini-icon">✓</span><h2>Consistency Badge</h2><p class="labs-muted">Unlocked after four verified actions.</p><span class="labs-pill">Unlocked</span></article>
  <article class="labs-card"><span class="labs-mini-icon">→</span><h2>Wallet Preview</h2><p class="labs-muted">Wallet integration comes later.</p><a class="labs-btn" href="/app/wallet.php">Open Wallet</a></article>
</section>
<?php labs_page_end(['section' => 'app']); ?>
