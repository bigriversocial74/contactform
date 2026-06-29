<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Cart | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'pricing',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Visual commerce shell</span>
    <h1>Your cart</h1>
    <p class="labs-copy">Cart and checkout are visual only in Stage 1. No billing records or payments are created.</p>
  </div>
</section>
<section class="labs-dashboard-grid">
  <article class="labs-card">
    <h2>Selected plan</h2>
    <div class="labs-mini-row"><span class="labs-mini-icon">TL</span><strong>Organization Plan</strong><span class="labs-pill">Monthly</span></div>
    <div class="labs-mini-row"><span class="labs-mini-icon">25</span><strong>Participant seats</strong><span>Visual quantity</span></div>
    <div class="labs-mini-row"><span class="labs-mini-icon">0</span><strong>Processing</strong><span class="labs-pill">Disabled</span></div>
  </article>
  <aside class="labs-card">
    <h2>Summary</h2>
    <p class="labs-muted">Estimated monthly total</p>
    <p><strong style="font-size:2rem">475 demo credits</strong></p>
    <a class="labs-btn labs-btn-primary" href="/checkout.php">Continue Checkout</a>
  </aside>
</section>
<section class="labs-section-band">
  <span class="labs-eyebrow">Commerce boundary</span>
  <h2>No payment is collected in Stage 1.</h2>
  <p class="labs-copy">This page only validates cart layout and checkout navigation.</p>
</section>
<?php labs_page_end(['section' => 'public']); ?>
