<?php
require_once __DIR__ . '/../includes/labs-layout.php';
labs_page_start([
    'title' => 'Wallet | Training Lab by Microgifter',
    'section' => 'app',
    'active' => 'app-wallet',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Microgifter wallet relationship</span>
    <h1>Wallet preview</h1>
    <p class="labs-copy">This page previews how Training Lab rewards will relate back to the main Microgifter wallet later.</p>
  </div>
</section>
<section class="labs-grid">
  <article class="labs-card"><h2>Available</h2><p><strong style="font-size:2rem">2</strong></p><p class="labs-muted">Static demo reward cards.</p></article>
  <article class="labs-card"><h2>Pending Review</h2><p><strong style="font-size:2rem">1</strong></p><p class="labs-muted">Awaiting approval state.</p></article>
  <article class="labs-card"><h2>Claim Status</h2><span class="labs-pill">Visual only</span><p class="labs-muted">No claim codes are created in Stage 1.</p></article>
</section>
<?php labs_page_end(['section' => 'app']); ?>
