<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Success | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'pricing',
]);
?>
<section class="labs-hero">
  <div>
    <span class="labs-eyebrow">Visual confirmation</span>
    <h1 class="labs-h1">Workspace ready for review.</h1>
    <p class="labs-copy">This is a non-functional confirmation shell. No payment was processed and no subscription was created.</p>
    <div class="labs-actions">
      <a class="labs-btn labs-btn-primary" href="/receipt.php">View Receipt</a>
      <a class="labs-btn" href="/app/index.php">Open Demo App</a>
    </div>
  </div>
  <div class="labs-card-soft labs-visual">
    <div class="labs-visual-panel">
      <div class="labs-mini-row"><span class="labs-mini-icon">1</span><strong>Plan selected</strong><span class="labs-pill">Visual</span></div>
      <div class="labs-mini-row"><span class="labs-mini-icon">2</span><strong>Summary viewed</strong><span class="labs-pill">Complete</span></div>
      <div class="labs-mini-row"><span class="labs-mini-icon">3</span><strong>Processing status</strong><span class="labs-pill">Disabled</span></div>
      <div class="labs-mini-row"><span class="labs-mini-icon">4</span><strong>Next step</strong><span class="labs-pill">Demo app</span></div>
    </div>
  </div>
</section>
<section class="labs-section-band">
  <span class="labs-eyebrow">Stage 1 confirmation</span>
  <h2>This page validates the checkout path without creating records.</h2>
  <p class="labs-copy">Future billing and account actions should connect through the main Microgifter systems in a later phase.</p>
</section>
<?php labs_page_end(['section' => 'public']); ?>
