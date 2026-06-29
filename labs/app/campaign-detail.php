<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'Campaign Detail | Training Lab by Microgifter',
    'section' => 'app',
    'active' => 'app-campaigns',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">5-Day Movement Challenge</span>
    <h1>Campaign detail</h1>
    <p class="labs-copy">Follow the sequence, submit demo proof, and watch progress update across the app.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="/app/sequence-tasks.php">Continue Sequence</a>
</section>
<section class="labs-kpis">
  <div class="labs-kpi"><span class="labs-muted">Completed actions</span><strong data-demo-completed-actions>4</strong><small>of 5</small></div>
  <div class="labs-kpi"><span class="labs-muted">Proof status</span><strong data-demo-proof-status>Not submitted</strong><small>demo state</small></div>
  <div class="labs-kpi"><span class="labs-muted">Review status</span><strong data-demo-review-status>Not submitted</strong><small>browser only</small></div>
  <div class="labs-kpi"><span class="labs-muted">Reward status</span><strong data-demo-reward-status>Pending</strong><small>visual only</small></div>
</section>
<section class="labs-grid">
  <article class="labs-card" style="grid-column:span 2">
    <h2>Sequence status</h2>
    <div class="labs-mini-row"><span class="labs-mini-icon">1</span><strong>Day 1 Walk</strong><span class="labs-pill">Approved</span></div>
    <div class="labs-mini-row"><span class="labs-mini-icon">2</span><strong>Day 2 Stretch</strong><span class="labs-pill">Approved</span></div>
    <div class="labs-mini-row"><span class="labs-mini-icon">5</span><strong>Day 5 Proof</strong><span class="labs-pill" data-demo-proof-status>Not submitted</span></div>
  </article>
  <aside class="labs-card">
    <h2>Progress</h2>
    <p class="labs-muted">Progress is calculated from browser-only demo state.</p>
    <div class="labs-progress-track"><div class="labs-progress-fill" data-demo-progress-fill></div></div><br>
    <span class="labs-pill" data-demo-progress-label>80% complete</span>
  </aside>
</section>
<?php labs_page_end(['section' => 'app']); ?>
