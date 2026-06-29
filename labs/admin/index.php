<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'Admin Overview | Training Lab by Microgifter',
    'section' => 'admin',
    'active' => 'admin-overview',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Admin backend</span>
    <h1>Training Lab overview</h1>
    <p class="labs-copy">Static admin dashboard shell based on the approved backend pattern.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="/admin/review-queue.php">Review Queue</a>
</section>
<section class="labs-kpis">
  <div class="labs-kpi"><span class="labs-muted">Active campaigns</span><strong>12</strong></div>
  <div class="labs-kpi"><span class="labs-muted">Participants</span><strong>248</strong></div>
  <div class="labs-kpi"><span class="labs-muted">Pending reviews</span><strong>31</strong></div>
  <div class="labs-kpi"><span class="labs-muted">Visual rewards</span><strong>86</strong></div>
</section>
<section class="labs-grid">
  <article class="labs-card" style="grid-column:span 2"><h2>Program activity</h2><p class="labs-muted">Chart placeholder for Stage 1.</p><div style="height:220px;border-radius:22px;background:var(--labs-surface-soft);display:grid;place-items:center;color:var(--labs-muted);font-weight:800">Activity chart placeholder</div></article>
  <aside class="labs-card"><h2>Needs attention</h2><div class="labs-mini-row"><strong>Proof reviews</strong><span class="labs-pill">31</span></div><div class="labs-mini-row"><strong>Flagged items</strong><span class="labs-pill">4</span></div></aside>
</section>
<?php labs_page_end(['section' => 'admin']); ?>
