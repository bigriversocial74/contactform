<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Order Complete | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'orders';
$can_merchant_nav = true;
$can_create_microgift = true;
$page_styles = ['/assets/css/agent-workspace-layout.css','/assets/css/checkout.css','/assets/css/account-commerce.css','/assets/css/account-commerce-fixes.css'];
$page_scripts = ['/assets/js/order-success.js'];
$order_id = trim((string)($_GET['order'] ?? ''));
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-commerce-shell">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-account-shell" data-order-success data-order-id="<?= mg_e($order_id) ?>">
    <section class="mg-commerce-page mg-order-complete-page">
      <div class="mg-commerce-shell">
        <header class="mg-commerce-hero mg-checkout-hero">
          <span class="mg-eyebrow">Payment received</span>
          <h1>Order complete</h1>
          <p>Your receipt, payment state, and Microgifter issuance status are shown below.</p>
        </header>

        <div class="mg-checkout-process" aria-label="Checkout process">
          <div><span>01</span><strong>Cart</strong><small>Reviewed</small></div>
          <div><span>02</span><strong>Draft</strong><small>Snapshot locked</small></div>
          <div><span>03</span><strong>Order</strong><small>Created</small></div>
          <div><span>04</span><strong>Payment</strong><small>Recorded</small></div>
          <div class="is-active"><span>05</span><strong>Issuance</strong><small>Microgifts routed</small></div>
        </div>

        <div class="mg-commerce-grid">
          <section class="mg-commerce-panel mg-receipt-panel">
            <div data-order-success-receipt><div class="mg-empty-state">Loading receipt…</div></div>
          </section>
          <aside class="mg-commerce-panel mg-commerce-summary mg-sticky-summary">
            <span class="mg-eyebrow">Next steps</span>
            <h2>Manage this purchase</h2>
            <p class="mg-commerce-note">Permanent PPPM IDs are created during issuance and remain separate from payment IDs.</p>
            <div class="mg-commerce-actions is-stack">
              <a class="mg-btn mg-btn-primary" href="/inbox.php">Open inbox</a>
              <a class="mg-btn mg-btn-soft" href="/account-commerce.php">Open commerce center</a>
              <a class="mg-btn mg-btn-soft" href="/account/orders.php">View orders only</a>
              <a class="mg-btn mg-btn-soft" href="/discover.php">Continue shopping</a>
            </div>
          </aside>
        </div>
      </div>
    </section>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
