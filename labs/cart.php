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
<section class="labs-grid">
  <article class="labs-card" style="grid-column:span 2">
    <div class="labs-mini-row"><span class="labs-mini-icon">TL</span><strong>Organization Plan</strong><span>$19 / user / month</span></div>
    <div class="labs-mini-row"><span class="labs-mini-icon">25</span><strong>Participant seats</strong><span>Visual quantity</span></div>
  </article>
  <aside class="labs-card">
    <h2>Order summary</h2>
    <p class="labs-muted">Estimated monthly total</p>
    <p><strong style="font-size:2rem">$475</strong></p>
    <a class="labs-btn labs-btn-primary" href="/checkout.php">Continue Checkout</a>
  </aside>
</section>
<?php labs_page_end(['section' => 'public']); ?>
