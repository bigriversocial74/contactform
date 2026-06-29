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
<section class="labs-card" style="max-width:860px;margin:auto">
  <div class="labs-mini-row"><span>Reference</span><strong>TL-DEMO-0001</strong></div>
  <div class="labs-mini-row"><span>Plan</span><strong>Organization Plan</strong></div>
  <div class="labs-mini-row"><span>Seats</span><strong>25 visual seats</strong></div>
  <div class="labs-mini-row"><span>Monthly amount</span><strong>475 demo credits</strong></div>
  <div class="labs-mini-row"><span>Status</span><span class="labs-pill">Demo only</span></div>
</section>
<?php labs_page_end(['section' => 'public']); ?>
