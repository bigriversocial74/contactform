<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'Proof Upload | Training Lab by Microgifter',
    'section' => 'app',
    'active' => 'app-tasks',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Demo proof upload</span>
    <h1>Submit proof</h1>
    <p class="labs-copy">Stage 2 stores demo proof state in the browser only. Files are still not stored or processed.</p>
  </div>
</section>
<section class="labs-dashboard-grid">
  <form class="labs-card" action="#" method="post">
    <h2>Day 5 Movement Proof</h2>
    <p class="labs-muted">Use this page to test the demo state path before real upload handling is added.</p>
    <label>Proof note<br><textarea rows="5" placeholder="Add a short note about the completed action."></textarea></label><br><br>
    <label>Visual file picker<br><input type="file" disabled></label><br><br>
    <button class="labs-btn labs-btn-primary" type="button" data-demo-action="submit-proof">Submit Demo Proof</button>
    <button class="labs-btn" type="button" data-demo-action="reset-demo">Reset Demo State</button>
  </form>
  <aside class="labs-card">
    <h2>Review status</h2>
    <div class="labs-mini-row"><strong>Current state</strong><span class="labs-pill" data-demo-proof-status>Not submitted</span></div>
    <div class="labs-mini-row"><strong>Review</strong><span class="labs-pill" data-demo-review-status>Not submitted</span></div>
    <div class="labs-mini-row"><strong>Last update</strong><span class="labs-pill" data-demo-updated-at>Not updated yet</span></div>
  </aside>
</section>
<section class="labs-section-band">
  <span class="labs-eyebrow">Stage 2 boundary</span>
  <h2>Demo state only. No media is uploaded.</h2>
  <p class="labs-copy">This page updates localStorage so the flow feels connected across Training Lab pages.</p>
</section>
<?php labs_page_end(['section' => 'app']); ?>
