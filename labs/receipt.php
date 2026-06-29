<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Receipt | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'pricing',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Visual receipt</span>
    <h1>Receipt preview</h1>
    <p class="labs-copy">This receipt is static demo content for Stage 1. It does not represent a real order.</p>
  </div>
  <a class="labs-btn" href="/pricing.php">Back to Pricing</a>
</section>
<section class="labs-dashboard-grid">
  <article class="labs-card">
    <h2>Demo receipt</h2>
    <div class="labs-mini-row"><span>Reference</span><strong>TL-DEMO-0001</strong></div>
    <div class="labs-mini-row"><span>Plan</span><strong>Organization Plan</strong></div>
    <div class="labs-mini-row"><span>Seats</span><strong>25 visual seats</strong></div>
    <div class="labs-mini-row"><span>Monthly amount</span><strong>475 demo credits</strong></div>
    <div class="labs-mini-row"><span>Status</span><span class="labs-pill">Demo only</span></div>
  </article>
  <aside class="labs-card">
    <h2>Next steps</h2>
    <p class="labs-muted">Move from this visual receipt into the app dashboard for review.</p>
    <a class="labs-btn labs-btn-primary" href="/app/index.php">Open Demo App</a>
  </aside>
</section>
<?php labs_page_end(['section' => 'public']); ?>
