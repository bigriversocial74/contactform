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
    <p class="labs-copy">This is the static task checklist pattern for Stage 1.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="/app/proof-upload.php">Upload Proof</a>
</section>
<section class="labs-card">
  <table class="labs-table">
    <thead><tr><th>Task</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
      <tr><td>Day 1 Walk</td><td><span class="labs-pill">Approved</span></td><td>View</td></tr>
      <tr><td>Day 2 Stretch</td><td><span class="labs-pill">Approved</span></td><td>View</td></tr>
      <tr><td>Day 5 Movement Proof</td><td><span class="labs-pill">Ready</span></td><td><a href="/app/proof-upload.php">Upload</a></td></tr>
    </tbody>
  </table>
</section>
<?php labs_page_end(['section' => 'app']); ?>
