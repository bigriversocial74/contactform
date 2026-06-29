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
    <p class="labs-copy">Stage 2 shows browser-only demo state. No real incentives are issued.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="/app/wallet.php">Open Wallet</a>
</section>
<section class="labs-kpis">
  <div class="labs-kpi"><span class="labs-muted">Reward status</span><strong data-demo-reward-status>Pending</strong><small>demo state</small></div>
  <div class="labs-kpi"><span class="labs-muted">Completed actions</span><strong data-demo-completed-actions>4</strong><small>of 5</small></div>
  <div class="labs-kpi"><span class="labs-muted">Proof status</span><strong data-demo-proof-status>Not submitted</strong><small>browser only</small></div>
  <div class="labs-kpi"><span class="labs-muted">Streak</span><strong data-demo-streak-days>4</strong><small>days</small></div>
</section>
<section class="labs-grid">
  <article class="labs-card labs-price-card"><span class="labs-mini-icon">M</span><h2>Movement Milestone</h2><p class="labs-muted">Unlocks after the reviewer approves the demo proof state.</p><div class="labs-progress-track"><div class="labs-progress-fill" data-demo-progress-fill></div></div><br><span class="labs-pill" data-demo-reward-status>Pending</span></article>
  <article class="labs-card labs-price-card"><span class="labs-mini-icon">C</span><h2>Consistency Badge</h2><p class="labs-muted">Unlocked after four verified demo actions.</p><div class="labs-progress-track"><div class="labs-progress-fill" style="width:100%"></div></div><br><span class="labs-pill">Unlocked</span></article>
  <article class="labs-card labs-price-card"><span class="labs-mini-icon">W</span><h2>Wallet Preview</h2><p class="labs-muted">Wallet state updates visually from localStorage only.</p><a class="labs-btn" href="/app/wallet.php">Open Wallet</a></article>
</section>
<?php labs_page_end(['section' => 'app']); ?>
