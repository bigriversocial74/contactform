<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'Campaign Detail | Training Lab by Microgifter',
    'section' => 'app',
    'active' => 'app-campaigns',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">5-Day Movement Challenge</span>
    <h1>Campaign detail</h1>
    <p class="labs-copy">Follow the sequence, submit proof, and view visual progress states.</p>
  </div>
  <a class="labs-btn labs-btn-primary" href="/app/sequence-tasks.php">Continue Sequence</a>
</section>
<section class="labs-grid">
  <article class="labs-card" style="grid-column:span 2">
    <h2>Sequence status</h2>
    <div class="labs-mini-row"><span class="labs-mini-icon">1</span><strong>Day 1 Walk</strong><span class="labs-pill">Approved</span></div>
    <div class="labs-mini-row"><span class="labs-mini-icon">2</span><strong>Day 2 Stretch</strong><span class="labs-pill">Approved</span></div>
    <div class="labs-mini-row"><span class="labs-mini-icon">5</span><strong>Day 5 Proof</strong><span class="labs-pill">Ready</span></div>
  </article>
  <aside class="labs-card">
    <h2>Progress</h2>
    <p class="labs-muted">Static visual state for campaign completion.</p>
    <span class="labs-pill">80% complete</span>
  </aside>
</section>
<?php labs_page_end(['section' => 'app']); ?>
