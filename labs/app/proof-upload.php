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
    <span class="labs-eyebrow">Visual proof upload</span>
    <h1>Submit proof</h1>
    <p class="labs-copy">Stage 1 displays the upload interface only. Files are not stored or processed.</p>
  </div>
</section>
<section class="labs-dashboard-grid">
  <form class="labs-card" action="#" method="post">
    <h2>Day 5 Movement Proof</h2>
    <p class="labs-muted">Use this page to review the future proof-submission layout before upload handling is added.</p>
    <label>Proof note<br><textarea rows="5" placeholder="Add a short note about the completed action."></textarea></label><br><br>
    <label>Visual file picker<br><input type="file" disabled></label><br><br>
    <button class="labs-btn labs-btn-primary" type="button">Visual Submit Button</button>
  </form>
  <aside class="labs-card">
    <h2>Review status</h2>
    <div class="labs-mini-row"><strong>Current state</strong><span class="labs-pill">Not submitted</span></div>
    <div class="labs-mini-row"><strong>Storage</strong><span class="labs-pill">Disabled</span></div>
    <div class="labs-mini-row"><strong>Review</strong><span class="labs-pill">Later</span></div>
  </aside>
</section>
<section class="labs-section-band">
  <span class="labs-eyebrow">Stage 1 boundary</span>
  <h2>No media is uploaded in this phase.</h2>
  <p class="labs-copy">This page is only the interface pattern for future upload and review work.</p>
</section>
<?php labs_page_end(['section' => 'app']); ?>
