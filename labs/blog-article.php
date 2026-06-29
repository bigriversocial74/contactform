<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Proof-Based Training Programs | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'blog',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Article</span>
    <h1>Proof-based training programs</h1>
    <p class="labs-copy">A sample static article page for the Stage 1 public site shell.</p>
  </div>
  <a class="labs-btn" href="/blog.php">Back to Blog</a>
</section>
<article class="labs-card" style="max-width:860px;margin:auto">
  <span class="labs-pill">Product education</span>
  <h2>Make action visible</h2>
  <p class="labs-muted">Training programs often lose momentum when completed actions are hard to see. Training Lab is designed around a simple participant loop: know the next task, complete the task, submit proof, and see progress.</p>
  <div class="labs-section-band" style="margin:28px 0">
    <span class="labs-eyebrow">Article summary</span>
    <h2>Action first, dashboard second.</h2>
    <p class="labs-copy">The product starts with the participant journey before adding deeper backend reporting.</p>
  </div>
  <h2>Action first</h2>
  <p class="labs-muted">The system starts with real actions instead of passive completion. Participants know what to do next and can see how each action moves them forward.</p>
  <h2>Proof and review</h2>
  <p class="labs-muted">Proof states are visual in Stage 1. Future phases will connect proof upload, review decisions, and action receipts to the main Microgifter account system.</p>
  <h2>Progress later</h2>
  <p class="labs-muted">Progress and wallet states are shown visually in this shell. Real issuing logic comes after the core proof and review loop is approved.</p>
</article>
<?php labs_page_end(['section' => 'public']); ?>
