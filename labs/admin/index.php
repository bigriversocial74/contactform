<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'Backend Overview | Training Lab by Microgifter',
    'section' => 'admin',
    'active' => 'admin-overview',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Backend overview</span>
    <h1>Training Lab overview</h1>
    <p class="labs-copy">Static backend dashboard shell based on the approved operations pattern.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="/admin/review-queue.php">Review Queue</a>
</section>
<section class="labs-kpis">
  <?php labs_stat_card('Active campaigns', '12', 'demo campaigns'); ?>
  <?php labs_stat_card('Participants', '248', 'static count'); ?>
  <?php labs_stat_card('Pending reviews', '31', 'visual queue'); ?>
  <?php labs_stat_card('Visual rewards', '86', 'not issued'); ?>
</section>
<section class="labs-dashboard-grid">
  <article class="labs-card">
    <h2>Program activity</h2>
    <p class="labs-muted">Chart placeholder for Stage 1.</p>
    <div style="height:220px;border-radius:22px;background:var(--labs-surface-soft);display:grid;place-items:center;color:var(--labs-muted);font-weight:800">Activity chart placeholder</div>
  </article>
  <aside class="labs-card">
    <h2>Needs attention</h2>
    <div class="labs-mini-row"><strong>Proof reviews</strong><span class="labs-pill">31</span></div>
    <div class="labs-mini-row"><strong>Campaign drafts</strong><span class="labs-pill">2</span></div>
    <div class="labs-mini-row"><strong>Visual rewards</strong><span class="labs-pill">86</span></div>
  </aside>
</section>
<section class="labs-section-band">
  <span class="labs-eyebrow">Backend rule</span>
  <h2>Three backend pages define the admin style.</h2>
  <p class="labs-copy">Overview, campaigns, and review queue establish the backend UI pattern. Additional backend sections should inherit these components later.</p>
</section>
<?php labs_page_end(['section' => 'admin']); ?>
