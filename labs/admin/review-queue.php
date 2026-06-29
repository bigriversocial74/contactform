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
    <p class="labs-copy">Stage 2 lets the reviewer approve the demo proof state in the browser only.</p>
  </div>
</section>
<section class="labs-card">
  <div class="labs-table-wrap">
    <table class="labs-table">
      <thead><tr><th>Participant</th><th>Campaign</th><th>Task</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <tr><td>Jamie R.</td><td>Movement Challenge</td><td>Day 5 Proof</td><td><span class="labs-pill" data-demo-review-status>Not submitted</span></td><td><button class="labs-btn labs-btn-primary" type="button" data-demo-action="approve-proof">Approve Demo</button></td></tr>
        <tr><td>Chris M.</td><td>Safety Readiness</td><td>Checklist Proof</td><td><span class="labs-pill">Needs Review</span></td><td><button class="labs-btn" type="button">View</button></td></tr>
        <tr><td>Avery L.</td><td>Service Basics</td><td>Scenario Proof</td><td><span class="labs-pill">Changes Needed</span></td><td><button class="labs-btn" type="button">View</button></td></tr>
      </tbody>
    </table>
  </div>
</section>
<section class="labs-grid" style="margin-top:18px">
  <article class="labs-card"><h2>Review guide</h2><p class="labs-muted">Reviewer buttons update browser-only demo state. No backend approval is performed.</p></article>
  <article class="labs-card"><h2>Last update</h2><p class="labs-muted" data-demo-updated-at>Not updated yet</p></article>
  <article class="labs-card"><h2>Actions</h2><p class="labs-muted">Approve and reset are demo-state actions only.</p><button class="labs-btn" type="button" data-demo-action="reset-demo">Reset Demo</button></article>
</section>
<?php labs_page_end(['section' => 'admin']); ?>
