<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'Sequence Tasks | Training Lab by Microgifter',
    'section' => 'app',
    'active' => 'app-tasks',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Sequence Tasks</span>
    <h1>Complete today’s action</h1>
    <p class="labs-copy">Stage 2 adds browser-only demo state using localStorage. No backend writes are performed.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="/app/proof-upload.php">Upload Proof</a>
</section>
<section class="labs-kpis">
  <?php labs_stat_card('Completed actions', '4', 'demo default'); ?>
  <div class="labs-kpi"><span class="labs-muted">Live completed actions</span><strong data-demo-completed-actions>4</strong><small>localStorage demo</small></div>
  <div class="labs-kpi"><span class="labs-muted">Proof status</span><strong data-demo-proof-status>Not submitted</strong><small>browser only</small></div>
  <div class="labs-kpi"><span class="labs-muted">Reward status</span><strong data-demo-reward-status>Pending</strong><small>visual state</small></div>
</section>
<section class="labs-card">
  <div class="labs-table-wrap">
    <table class="labs-table">
      <thead><tr><th>Task</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <tr><td>Day 1 Walk</td><td><span class="labs-pill">Approved</span></td><td><a class="labs-btn" href="#">View</a></td></tr>
        <tr><td>Day 2 Stretch</td><td><span class="labs-pill">Approved</span></td><td><a class="labs-btn" href="#">View</a></td></tr>
        <tr><td>Day 5 Movement Proof</td><td><span class="labs-pill" data-demo-proof-status>Not submitted</span></td><td><a class="labs-btn labs-btn-primary" href="/app/proof-upload.php">Upload</a></td></tr>
      </tbody>
    </table>
  </div>
</section>
<?php labs_page_end(['section' => 'app']); ?>
