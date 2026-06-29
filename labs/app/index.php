<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'App Dashboard | Training Lab by Microgifter',
    'section' => 'app',
    'active' => 'app-dashboard',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Participant dashboard</span>
    <h1>Today’s training progress</h1>
    <p class="labs-copy">Stage 2 adds browser-only demo state across proof, review, rewards, and wallet pages.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="/app/campaigns.php">View Campaigns</a>
</section>
<section class="labs-kpis">
  <?php labs_stat_card('Active campaigns', '3', '2 due this week'); ?>
  <div class="labs-kpi"><span class="labs-muted">Current streak</span><strong data-demo-streak-days>4</strong><small>days</small></div>
  <div class="labs-kpi"><span class="labs-muted">Completed actions</span><strong data-demo-completed-actions>4</strong><small>of 5</small></div>
  <div class="labs-kpi"><span class="labs-muted">Reward status</span><strong data-demo-reward-status>Pending</strong><small>demo state</small></div>
</section>
<section class="labs-dashboard-grid">
  <article class="labs-card">
    <h2>Next action</h2>
    <div class="labs-mini-row"><span class="labs-mini-icon">1</span><strong>5-Day Movement Challenge</strong><span class="labs-pill" data-demo-proof-status>Not submitted</span></div>
    <div class="labs-mini-row"><span class="labs-mini-icon">2</span><strong>Hydration Check-In</strong><span class="labs-pill">Complete task</span></div>
    <div class="labs-mini-row"><span class="labs-mini-icon">3</span><strong>Customer Service Basics</strong><span class="labs-pill">Review tips</span></div>
  </article>
  <aside class="labs-card">
    <h2>Reward progress</h2>
    <p class="labs-muted">Submit and approve demo proof to update this progress across pages.</p>
    <div class="labs-progress-track"><div class="labs-progress-fill" data-demo-progress-fill></div></div>
    <br>
    <a class="labs-btn" href="/app/rewards.php">View Rewards</a>
  </aside>
</section>
<section class="labs-section-band labs-split-card">
  <div>
    <span class="labs-eyebrow">Stage 2 note</span>
    <h2>This dashboard now reads localStorage demo state.</h2>
    <p class="labs-copy">The data shown here is browser-only. Real proof, review, wallet, and reward integration will come after the demo flow is approved.</p>
  </div>
  <div class="labs-image-slot"><img src="/assets/img/app/participant-dashboard.svg" alt="Participant dashboard mockup illustration" style="position:relative;width:100%;max-height:330px;object-fit:contain"></div>
</section>
<?php labs_page_end(['section' => 'app']); ?>
