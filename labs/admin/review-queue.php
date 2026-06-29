<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'Review Queue | Training Lab by Microgifter',
    'section' => 'admin',
    'active' => 'admin-review',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Review queue</span>
    <h1>Proof submissions</h1>
    <p class="labs-copy">Static review queue shell. Buttons are visual only in Stage 1.</p>
  </div>
</section>
<section class="labs-card">
  <div class="labs-table-wrap">
    <table class="labs-table">
      <thead><tr><th>Participant</th><th>Campaign</th><th>Task</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <tr><td>Jamie R.</td><td>Movement Challenge</td><td>Day 5 Proof</td><td><span class="labs-pill">Needs Review</span></td><td><button class="labs-btn" type="button">View</button></td></tr>
        <tr><td>Chris M.</td><td>Safety Readiness</td><td>Checklist Proof</td><td><span class="labs-pill">Needs Review</span></td><td><button class="labs-btn" type="button">View</button></td></tr>
        <tr><td>Avery L.</td><td>Service Basics</td><td>Scenario Proof</td><td><span class="labs-pill">Changes Needed</span></td><td><button class="labs-btn" type="button">View</button></td></tr>
      </tbody>
    </table>
  </div>
</section>
<section class="labs-grid" style="margin-top:18px">
  <article class="labs-card"><h2>Review guide</h2><p class="labs-muted">Visual placeholder for proof review guidelines and reviewer notes.</p></article>
  <article class="labs-card"><h2>Queue filters</h2><p class="labs-muted">Filter controls will be added when real review data exists.</p></article>
  <article class="labs-card"><h2>Actions</h2><p class="labs-muted">Approve and request-change actions are intentionally disabled in Stage 1.</p></article>
</section>
<?php labs_page_end(['section' => 'admin']); ?>
