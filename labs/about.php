<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'About | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'about',
]);
?>
<section class="labs-hero">
  <div>
    <span class="labs-eyebrow">About Training Lab</span>
    <h1 class="labs-h1">A rewards layer for verified consistency.</h1>
    <p class="labs-copy">Training Lab helps teams create action-based campaigns where progress is earned through completed tasks, proof, review, and consistent participation.</p>
    <div class="labs-actions">
      <a class="labs-btn labs-btn-primary" href="/how-it-works.php">How It Works</a>
      <a class="labs-btn" href="/pricing.php">View Pricing</a>
    </div>
  </div>
  <div class="labs-card-soft labs-visual">
    <div class="labs-visual-panel">
      <div class="labs-mini-row"><span class="labs-mini-icon">A</span><strong>Action-based</strong><span class="labs-pill">Training</span></div>
      <div class="labs-mini-row"><span class="labs-mini-icon">P</span><strong>Proof-backed</strong><span class="labs-pill">Review</span></div>
      <div class="labs-mini-row"><span class="labs-mini-icon">R</span><strong>Reward-ready</strong><span class="labs-pill">Wallet</span></div>
    </div>
  </div>
</section>
<section class="labs-grid">
  <article class="labs-card"><h2>For organizations</h2><p class="labs-muted">Create campaign structure, assign tasks, and review proof from one admin area.</p></article>
  <article class="labs-card"><h2>For participants</h2><p class="labs-muted">Know what to do next, build streaks, and see reward progress clearly.</p></article>
  <article class="labs-card"><h2>For Microgifter</h2><p class="labs-muted">Extend the existing account, wallet, and reward model with proof-of-action records.</p></article>
</section>
<?php labs_page_end(['section' => 'public']); ?>
