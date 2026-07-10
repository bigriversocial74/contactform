<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Secure Checkout | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'cart';
$can_merchant_nav = true;
$can_create_microgift = true;
$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/checkout.css',
    '/assets/css/account-commerce.css',
    '/assets/css/account-commerce-fixes.css',
    '/assets/css/cart-checkout-runtime-v1.css',
];
$page_scripts = ['/assets/js/checkout.js'];
$session_id = trim((string) ($_GET['session'] ?? ''));

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-commerce-shell">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-account-shell">
    <section class="mg-commerce-page mg-checkout-page" data-checkout data-session-id="<?= mg_e($session_id) ?>">
      <div class="mg-commerce-shell">
        <header class="mg-commerce-hero mg-checkout-hero">
          <span class="mg-eyebrow">Secure checkout</span>
          <h1>Complete your purchase</h1>
          <p>Your frozen order snapshot and payment session can be safely refreshed or resumed without creating duplicate orders.</p>
        </header>

        <div class="mg-checkout-process" aria-label="Checkout process">
          <div><span>01</span><strong>Cart</strong><small>Reviewed</small></div>
          <div><span>02</span><strong>Snapshot</strong><small>Terms frozen</small></div>
          <div><span>03</span><strong>Order</strong><small>Created once</small></div>
          <div class="is-active"><span>04</span><strong>Payment</strong><small>Recoverable session</small></div>
          <div><span>05</span><strong>Delivery</strong><small>After payment</small></div>
        </div>

        <div class="mg-checkout-card" data-checkout-content aria-live="polite" aria-busy="true">
          <div class="mg-empty-state">Loading secure checkout…</div>
        </div>
      </div>
    </section>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
