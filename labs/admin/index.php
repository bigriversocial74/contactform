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
    <p class="labs-copy">Stage 2 surfaces the browser-only demo proof state in the backend overview.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="/admin/review-queue.php">Review Queue</a>
</section>
<section class="labs-kpis">
  <?php labs_stat_card('Active campaigns', '12', 'demo campaigns'); ?>
  <?php labs_stat_card('Participants', '248', 'static count'); ?>
  <div class="labs-kpi"><span class="labs-muted">Primary review</span><strong data-demo-review-status>Not submitted</strong><small>demo state</small></div>
  <div class="labs-kpi"><span class="labs-muted">Primary reward</span><strong data-demo-reward-status>Pending</strong><small>visual only</small></div>
</section>
<section class="labs-dashboard-grid">
  <article class="labs-card">
    <h2>Program activity</h2>
    <p class="labs-muted">Progress updates from localStorage demo state.</p>
    <div class="labs-progress-track"><div class="labs-progress-fill" data-demo-progress-fill></div></div><br>
    <span class="labs-pill" data-demo-progress-label>80% complete</span>
  </article>
  <aside class="labs-card">
    <h2>Needs attention</h2>
    <div class="labs-mini-row"><strong>Proof review</strong><span class="labs-pill" data-demo-review-status>Not submitted</span></div>
    <div class="labs-mini-row"><strong>Proof status</strong><span class="labs-pill" data-demo-proof-status>Not submitted</span></div>
    <div class="labs-mini-row"><strong>Last update</strong><span class="labs-pill" data-demo-updated-at>Not updated yet</span></div>
  </aside>
</section>
<section class="labs-section-band labs-split-card">
  <div>
    <span class="labs-eyebrow">Backend rule</span>
    <h2>Three backend pages define the admin style.</h2>
    <p class="labs-copy">Overview, campaigns, and review queue establish the backend UI pattern. Additional backend sections should inherit these components later.</p>
  </div>
  <div class="labs-image-slot"><img src="/assets/img/admin/backend-overview.svg" alt="Backend overview mockup illustration" style="position:relative;width:100%;max-height:330px;object-fit:contain"></div>
</section>
<?php labs_page_end(['section' => 'admin']); ?>
