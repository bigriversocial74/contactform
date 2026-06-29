<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'Campaigns | Training Lab by Microgifter',
    'section' => 'app',
    'active' => 'app-campaigns',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Campaigns</span>
    <h1>Active training campaigns</h1>
    <p class="labs-copy">Static campaign cards for Stage 1. Campaign data is not connected to a database yet.</p>
  </div>
</section>
<section class="labs-grid">
  <article class="labs-card"><h2>5-Day Movement Challenge</h2><p class="labs-muted">4 of 5 actions complete.</p><span class="labs-pill">In Progress</span><br><br><a class="labs-btn labs-btn-primary" href="/app/campaign-detail.php">Open Campaign</a></article>
  <article class="labs-card"><h2>Customer Service Basics</h2><p class="labs-muted">2 of 6 actions complete.</p><span class="labs-pill">Active</span><br><br><a class="labs-btn" href="/app/campaign-detail.php">Open Campaign</a></article>
  <article class="labs-card"><h2>Safety Readiness</h2><p class="labs-muted">Reward available after review.</p><span class="labs-pill">Review Pending</span><br><br><a class="labs-btn" href="/app/campaign-detail.php">Open Campaign</a></article>
</section>
<?php labs_page_end(['section' => 'app']); ?>
