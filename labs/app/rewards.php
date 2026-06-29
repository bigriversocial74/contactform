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
    <span class="labs-eyebrow">Progress milestones</span>
    <h1>Rewards and milestones</h1>
    <p class="labs-copy">Visual states only. No real incentives are issued in Stage 1.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="/app/wallet.php">Open Wallet</a>
</section>
<section class="labs-kpis">
  <?php labs_stat_card('Unlocked', '2', 'visual states'); ?>
  <?php labs_stat_card('Pending', '1', 'review state'); ?>
  <?php labs_stat_card('Locked', '3', 'future goals'); ?>
  <?php labs_stat_card('Streak', '4', 'days'); ?>
</section>
<section class="labs-grid">
  <article class="labs-card labs-price-card"><span class="labs-mini-icon">M</span><h2>Movement Milestone</h2><p class="labs-muted">Unlocks after the final visual review state changes.</p><div class="labs-progress-track"><div class="labs-progress-fill" style="width:80%"></div></div><br><span class="labs-pill">Pending</span></article>
  <article class="labs-card labs-price-card"><span class="labs-mini-icon">C</span><h2>Consistency Badge</h2><p class="labs-muted">Unlocked after four verified demo actions.</p><div class="labs-progress-track"><div class="labs-progress-fill" style="width:100%"></div></div><br><span class="labs-pill">Unlocked</span></article>
  <article class="labs-card labs-price-card"><span class="labs-mini-icon">W</span><h2>Wallet Preview</h2><p class="labs-muted">Wallet integration comes later through the main Microgifter account model.</p><a class="labs-btn" href="/app/wallet.php">Open Wallet</a></article>
</section>
<?php labs_page_end(['section' => 'app']); ?>
