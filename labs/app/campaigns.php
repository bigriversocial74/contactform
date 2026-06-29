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
<section class="labs-kpis">
  <?php labs_stat_card('Active', '3', 'demo campaigns'); ?>
  <?php labs_stat_card('Due soon', '2', 'visual tasks'); ?>
  <?php labs_stat_card('In review', '1', 'static state'); ?>
  <?php labs_stat_card('Completed', '5', 'sample history'); ?>
</section>
<section class="labs-grid">
  <article class="labs-card labs-price-card"><span class="labs-pill">In Progress</span><h2>5-Day Movement Challenge</h2><p class="labs-muted">4 of 5 actions complete. Upload the final proof item to finish the visual sequence.</p><div class="labs-progress-track"><div class="labs-progress-fill" style="width:80%"></div></div><br><a class="labs-btn labs-btn-primary" href="/app/campaign-detail.php">Open Campaign</a></article>
  <article class="labs-card labs-price-card"><span class="labs-pill">Active</span><h2>Customer Service Basics</h2><p class="labs-muted">2 of 6 actions complete. Continue the next training step from the task list.</p><div class="labs-progress-track"><div class="labs-progress-fill" style="width:34%"></div></div><br><a class="labs-btn" href="/app/campaign-detail.php">Open Campaign</a></article>
  <article class="labs-card labs-price-card"><span class="labs-pill">Review Pending</span><h2>Safety Readiness</h2><p class="labs-muted">Completion state is waiting on a visual backend review queue item.</p><div class="labs-progress-track"><div class="labs-progress-fill" style="width:92%"></div></div><br><a class="labs-btn" href="/app/campaign-detail.php">Open Campaign</a></article>
</section>
<?php labs_page_end(['section' => 'app']); ?>
