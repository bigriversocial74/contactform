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
    <p class="labs-copy">Static demo dashboard for the Stage 1 UI shell.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="/app/campaigns.php">View Campaigns</a>
</section>
<section class="labs-kpis">
  <div class="labs-kpi"><span class="labs-muted">Active campaigns</span><strong>3</strong></div>
  <div class="labs-kpi"><span class="labs-muted">Current streak</span><strong>4</strong></div>
  <div class="labs-kpi"><span class="labs-muted">Proof submitted</span><strong>8</strong></div>
  <div class="labs-kpi"><span class="labs-muted">Rewards unlocked</span><strong>2</strong></div>
</section>
<section class="labs-grid">
  <article class="labs-card" style="grid-column:span 2">
    <h2>Next action</h2>
    <div class="labs-mini-row"><span class="labs-mini-icon">1</span><strong>5-Day Movement Challenge</strong><span class="labs-pill">Upload proof</span></div>
    <div class="labs-mini-row"><span class="labs-mini-icon">2</span><strong>Hydration Check-In</strong><span class="labs-pill">Complete task</span></div>
  </article>
  <aside class="labs-card"><h2>Reward progress</h2><p class="labs-muted">Complete one more verified action to unlock the next Microgifter reward.</p><a class="labs-btn" href="/app/rewards.php">View Rewards</a></aside>
</section>
<?php labs_page_end(['section' => 'app']); ?>
