<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Proof-Based Training Rewards | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'blog',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Article</span>
    <h1>Proof-based training rewards</h1>
    <p class="labs-copy">A sample static article page for the Stage 1 public site shell.</p>
  </div>
  <a class="labs-btn" href="/blog.php">Back to Blog</a>
</section>
<article class="labs-card" style="max-width:820px;margin:auto">
  <p class="labs-muted">Training programs often fail when actions are not visible. Training Lab is designed around a simple loop: complete the action, submit proof, review the submission, create an action receipt, and show reward progress.</p>
  <h2>Action first</h2>
  <p class="labs-muted">The system starts with real actions instead of passive completion. Participants know what to do next and can see how each action moves them forward.</p>
  <h2>Proof and review</h2>
  <p class="labs-muted">Proof states are visual in Stage 1. Future phases will connect proof upload, review decisions, and action receipts to the main Microgifter account system.</p>
  <h2>Rewards later</h2>
  <p class="labs-muted">Rewards are shown as visual progress only in this shell. Real reward issuing will come after the core proof and review loop is approved.</p>
</article>
<?php labs_page_end(['section' => 'public']); ?>
