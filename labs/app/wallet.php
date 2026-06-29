<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'Wallet | Training Lab by Microgifter',
    'section' => 'app',
    'active' => 'app-wallet',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Microgifter wallet relationship</span>
    <h1>Wallet preview</h1>
    <p class="labs-copy">This page previews how Training Lab progress states will relate back to the main Microgifter wallet later.</p>
  </div>
</section>
<section class="labs-kpis">
  <?php labs_stat_card('Available', '2', 'demo cards'); ?>
  <div class="labs-kpi"><span class="labs-muted">Milestone status</span><strong data-demo-reward-status>Pending</strong><small>localStorage</small></div>
  <div class="labs-kpi"><span class="labs-muted">Claim status</span><strong>0</strong><small>not wired</small></div>
  <?php labs_stat_card('Account link', 'Later', 'Microgifter'); ?>
</section>
<section class="labs-dashboard-grid">
  <article class="labs-card">
    <h2>Wallet items</h2>
    <div class="labs-mini-row"><span class="labs-mini-icon">C</span><strong>Consistency Badge</strong><span class="labs-pill">Available</span></div>
    <div class="labs-mini-row"><span class="labs-mini-icon">M</span><strong>Movement Milestone</strong><span class="labs-pill" data-demo-reward-status>Pending</span></div>
    <div class="labs-mini-row"><span class="labs-mini-icon">S</span><strong>Safety Readiness</strong><span class="labs-pill">Locked</span></div>
  </article>
  <aside class="labs-card">
    <h2>Stage 2 wallet rule</h2>
    <p class="labs-muted">No wallet balance changes, claim codes, or redemption actions exist in this phase.</p>
    <div class="labs-mini-row"><strong>Last update</strong><span class="labs-pill" data-demo-updated-at>Not updated yet</span></div>
    <button class="labs-btn" type="button" data-demo-action="reset-demo">Reset Demo</button>
  </aside>
</section>
<?php labs_page_end(['section' => 'app']); ?>
