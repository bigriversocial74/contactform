<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$cart_user = mg_current_user();
$page_title = 'Cart | Microgifter';
$page_section = $cart_user ? 'agent' : 'cart';
$header_mode = $cart_user ? 'agent' : 'public';
$page_styles = ['/assets/css/agent-workspace-layout.css','/assets/css/checkout.css','/assets/css/account-commerce.css','/assets/css/account-commerce-fixes.css','/assets/css/cart-layout-fixes.css'];
$page_scripts = [];
$agent_tab = 'cart';
$can_merchant_nav = true;
$can_create_microgift = true;
require __DIR__ . '/includes/header.php';
?>
<?php if (!$cart_user): ?>
<section class="mg-checkout-public-gate">
  <div class="mg-checkout-gate-card">
    <span class="mg-eyebrow">Checkout account required</span>
    <h1>Sign in to review your cart.</h1>
    <p>Microgifter uses a server-side cart, frozen checkout drafts, idempotent orders, and secure payment sessions. Sign in or create an account to continue checkout.</p>
    <div class="mg-commerce-actions">
      <a class="mg-btn mg-btn-primary" href="/signin.php?next=/cart.php">Sign in</a>
      <a class="mg-btn mg-btn-soft" href="/signup.php?next=/cart.php">Create account</a>
      <a class="mg-btn mg-btn-soft" href="/discover.php">Keep exploring</a>
    </div>
  </div>
</section>
<?php else: ?>
<section class="mg-app-shell mg-account-commerce-shell" data-cart-page>
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-account-shell">
    <section class="mg-commerce-page">
      <section class="mg-commerce-shell">
        <header class="mg-commerce-hero mg-checkout-hero">
          <span class="mg-eyebrow">Customer checkout</span>
          <h1>Your cart</h1>
          <p>Review your Microgifter items before creating a secure checkout draft, pending order, and payment session.</p>
        </header>

        <div class="mg-checkout-process" aria-label="Checkout process">
          <div class="is-active"><span>01</span><strong>Cart</strong><small>Live server totals</small></div>
          <div><span>02</span><strong>Draft</strong><small>Frozen snapshot</small></div>
          <div><span>03</span><strong>Order</strong><small>Idempotent creation</small></div>
          <div><span>04</span><strong>Payment</strong><small>Cash checkout</small></div>
          <div><span>05</span><strong>Issuance</strong><small>Microgifts delivered</small></div>
        </div>

        <div class="mg-commerce-grid mg-cart-grid">
          <section class="mg-commerce-panel mg-cart-items-panel" aria-live="polite">
            <div class="mg-section-head">
              <div>
                <span class="mg-eyebrow">Items</span>
                <h2>Cart items</h2>
              </div>
              <button class="mg-btn mg-btn-soft" type="button" data-cart-refresh>Refresh</button>
            </div>
            <div data-cart-items><div class="mg-empty-state">Loading cart…</div></div>
          </section>

          <aside class="mg-commerce-panel mg-commerce-summary mg-sticky-summary">
            <div class="mg-section-head">
              <div>
                <span class="mg-eyebrow">Summary</span>
                <h2>Order preview</h2>
              </div>
            </div>
            <div data-cart-summary><div class="mg-empty-state">Calculating…</div></div>
            <div class="mg-commerce-actions is-stack" data-cart-payment-actions>
              <button class="mg-btn mg-btn-primary" type="button" data-cart-checkout-provider="cash">Pay with cash</button>
              <button class="mg-btn mg-btn-soft" type="button" data-cart-clear>Clear cart</button>
              <a class="mg-btn mg-btn-soft" href="/discover.php">Continue shopping</a>
            </div>
            <p class="mg-commerce-note" data-cart-payment-note>Cash checkout is enabled. Card checkout is hidden until Stripe is ready.</p>
            <div data-cart-status class="mg-commerce-status" role="status" aria-live="polite"></div>
          </aside>
        </div>
      </section>
    </section>
  </main>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
