<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'How It Works | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'how-it-works',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">How it works</span>
    <h1>From action to verified reward.</h1>
    <p class="labs-copy">Training Lab turns guided tasks into proof, review, action receipts, and reward progress.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="/signup.php">Start Demo Flow</a>
</section>
<section class="labs-grid">
  <article class="labs-card"><span class="labs-mini-icon">1</span><h2>Create campaign</h2><p class="labs-muted">Organizations define the training goal, task sequence, proof type, and reward concept.</p></article>
  <article class="labs-card"><span class="labs-mini-icon">2</span><h2>Complete actions</h2><p class="labs-muted">Participants follow a simple sequence and complete real-world actions.</p></article>
  <article class="labs-card"><span class="labs-mini-icon">3</span><h2>Upload proof</h2><p class="labs-muted">Proof upload is visual in Stage 1 and will become functional later.</p></article>
  <article class="labs-card"><span class="labs-mini-icon">4</span><h2>Review proof</h2><p class="labs-muted">Admins review submissions and approve verified completion in a later phase.</p></article>
  <article class="labs-card"><span class="labs-mini-icon">5</span><h2>Create receipt</h2><p class="labs-muted">Action Receipts become the structured record of completed training actions.</p></article>
  <article class="labs-card"><span class="labs-mini-icon">6</span><h2>Unlock reward</h2><p class="labs-muted">Rewards connect back to the Microgifter wallet system after approval.</p></article>
</section>
<?php labs_page_end(['section' => 'public']); ?>
