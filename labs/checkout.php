<?php
require_once __DIR__ . '/includes/labs-layout.php';
labs_page_start([
    'title' => 'Checkout | Training Lab by Microgifter',
    'section' => 'public',
    'active' => 'pricing',
]);
?>
<section class="labs-page-title">
  <div>
    <span class="labs-eyebrow">Secure checkout UI</span>
    <h1>Checkout</h1>
    <p class="labs-copy">Stage 1 checkout is a non-functional UI shell. Payment integration comes later only after approval.</p>
  </div>
</section>
<section class="labs-grid">
  <form class="labs-card" style="grid-column:span 2" action="#" method="post">
    <h2>Billing contact</h2>
    <label>Name<br><input type="text" placeholder="Full name"></label><br><br>
    <label>Email<br><input type="email" placeholder="billing@example.com"></label><br><br>
    <label>Organization<br><input type="text" placeholder="Organization name"></label><br><br>
    <button class="labs-btn labs-btn-primary" type="button">Complete Visual Checkout</button>
  </form>
  <aside class="labs-card">
    <h2>Summary</h2>
    <p class="labs-muted">Organization Plan</p>
    <p><strong style="font-size:2rem">$475</strong></p>
    <span class="labs-pill">No payment processed</span>
  </aside>
</section>
<?php labs_page_end(['section' => 'public']); ?>
