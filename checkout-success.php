<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Order Complete | Microgifter';
$page_section = 'checkout';
$header_mode = 'account';
$page_styles = ['/assets/css/checkout.css','/assets/css/account-commerce.css','/assets/css/account-commerce-fixes.css'];
$page_scripts = ['/assets/js/order-success.js','/assets/js/account-sidebar.js'];
$accountView = 'orders';
$order_id = trim((string)($_GET['order'] ?? ''));
require __DIR__ . '/includes/header.php';
?>
<section class="mg-account-page">
  <div class="mg-account-layout">
    <?php require __DIR__ . '/includes/account-sidebar.php'; ?>
    <main class="mg-account-shell" data-order-success data-order-id="<?= mg_e($order_id) ?>">
      <section class="mg-commerce-page">
        <div class="mg-commerce-shell">
          <header class="mg-commerce-hero">
            <span class="mg-eyebrow">Payment received</span>
            <h1>Order complete</h1>
            <p>Your payment was recorded, your receipt is available, and eligible purchased units are routed into PPPM issuance.</p>
          </header>
          <div class="mg-commerce-grid">
            <section class="mg-commerce-panel">
              <div data-order-success-receipt><div class="mg-empty-state">Loading receipt…</div></div>
            </section>
            <aside class="mg-commerce-panel mg-commerce-summary">
              <span class="mg-eyebrow">Next steps</span>
              <h2>Manage this purchase</h2>
              <p class="mg-commerce-note">Permanent PPPM IDs are created during issuance and remain separate from payment IDs.</p>
              <div class="mg-commerce-actions is-stack">
                <a class="mg-btn mg-btn-primary" href="/account-commerce.php">Open commerce center</a>
                <a class="mg-btn mg-btn-soft" href="/account/orders.php">View orders only</a>
                <a class="mg-btn mg-btn-soft" href="/inbox.php">Open inbox</a>
                <a class="mg-btn mg-btn-soft" href="/cart.php">Back to cart</a>
              </div>
            </aside>
          </div>
        </div>
      </section>
    </main>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
