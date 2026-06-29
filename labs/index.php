<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'home',
]);
?>
<section class="labs-hero">
  <div>
    <span class="labs-eyebrow">Proof-based training rewards</span>
    <h1 class="labs-h1">Complete Actions. Earn Rewards.</h1>
    <p class="labs-copy">Complete guided training sequences, upload proof, build streaks, and unlock rewards for verified consistency.</p>
    <div class="labs-actions">
      <a class="labs-btn labs-btn-primary" href="/signup.php">Start Training Lab</a>
      <a class="labs-btn" href="/pricing.php">View Pricing</a>
    </div>
  </div>
  <div class="labs-card-soft labs-visual" aria-label="Training Lab action flow preview">
    <div class="labs-visual-panel">
      <div class="labs-mini-row"><span class="labs-mini-icon">1</span><strong>Complete sequence</strong><span class="labs-pill">Active</span></div>
      <div class="labs-mini-row"><span class="labs-mini-icon">2</span><strong>Upload proof</strong><span class="labs-pill">Ready</span></div>
      <div class="labs-mini-row"><span class="labs-mini-icon">3</span><strong>Build streak</strong><span class="labs-pill">4 days</span></div>
      <div class="labs-mini-row"><span class="labs-mini-icon">4</span><strong>Earn reward</strong><span class="labs-pill">Unlocked</span></div>
    </div>
  </div>
</section>
<section class="labs-grid">
  <article class="labs-card"><span class="labs-mini-icon">✓</span><h3>Complete Sequences</h3><p class="labs-muted">Participants follow guided tasks designed around real action and consistency.</p></article>
  <article class="labs-card"><span class="labs-mini-icon">↑</span><h3>Upload Proof</h3><p class="labs-muted">Photo, video, and document proof states are visual in Stage 1.</p></article>
  <article class="labs-card"><span class="labs-mini-icon">★</span><h3>Earn Rewards</h3><p class="labs-muted">Reward states connect visually now and integrate with Microgifter later.</p></article>
</section>
<?php labs_page_end(['section' => 'public']); ?>
