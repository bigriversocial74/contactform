<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Cart | Microgifter';
$page_section = 'cart';
$header_mode = mg_current_user() ? 'account' : 'public';
$page_styles = ['/assets/css/checkout.css','/assets/css/account-commerce.css','/assets/css/cart-layout-fixes.css'];
$page_scripts = [];
$accountView = 'cart';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-account-page mg-cart-account-page" data-cart-page>
  <div class="mg-account-layout mg-cart-account-layout">
    <?php require __DIR__ . '/includes/account-sidebar.php'; ?>
    <section class="mg-account-shell mg-commerce-page">
      <section class="mg-commerce-shell">
        <header class="mg-commerce-hero">
          <span class="mg-eyebrow">Customer checkout</span>
          <h1>Your cart</h1>
          <p>Review your Microgifter items before creating a secure checkout draft and pending order.</p>
        </header>
        <div class="mg-commerce-grid">
          <section class="mg-commerce-panel" aria-live="polite">
            <div class="mg-section-head">
              <div>
                <span class="mg-eyebrow">Items</span>
                <h2>Cart items</h2>
              </div>
              <button class="mg-btn mg-btn-soft" type="button" data-cart-refresh>Refresh</button>
            </div>
            <div data-cart-items><div class="mg-empty-state">Loading cart…</div></div>
          </section>
          <aside class="mg-commerce-panel mg-commerce-summary">
            <div class="mg-section-head">
              <div>
                <span class="mg-eyebrow">Summary</span>
                <h2>Order preview</h2>
              </div>
            </div>
            <div data-cart-summary><div class="mg-empty-state">Calculating…</div></div>
            <div class="mg-commerce-actions">
              <button class="mg-btn mg-btn-primary" type="button" data-cart-checkout>Create secure checkout</button>
              <button class="mg-btn mg-btn-soft" type="button" data-cart-clear>Clear cart</button>
            </div>
            <p class="mg-commerce-note">Checkout uses server-side cart totals, frozen checkout drafts, idempotent order creation, and provider-safe payment sessions.</p>
            <div data-cart-status class="mg-commerce-status"></div>
          </aside>
        </div>
      </section>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>