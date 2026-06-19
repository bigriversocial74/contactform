<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
$page_title = 'My Orders | Microgifter';
$page_section = 'account-orders';
$header_mode = 'account';
$page_styles = ['/assets/css/checkout.css','/assets/css/account-commerce.css','/assets/css/account-commerce-fixes.css'];
$page_scripts = ['/assets/js/account-orders.js','/assets/js/account-sidebar.js'];
$accountView = 'orders';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-account-page">
  <div class="mg-account-layout">
    <?php require dirname(__DIR__) . '/includes/account-sidebar.php'; ?>
    <main class="mg-account-shell" data-account-orders>
      <section class="mg-commerce-page">
        <div class="mg-commerce-shell">
          <header class="mg-commerce-hero">
            <span class="mg-eyebrow">Account</span>
            <h1>My orders</h1>
            <p>Track pending, paid, and fulfilled Microgifter purchases from one customer account view.</p>
          </header>
          <section class="mg-commerce-panel">
            <div class="mg-section-head">
              <div>
                <span class="mg-eyebrow">Commerce history</span>
                <h2>Orders and receipts</h2>
              </div>
              <a class="mg-btn mg-btn-soft" href="/cart.php">Open cart</a>
            </div>
            <div data-account-orders-list><div class="mg-empty-state">Loading orders…</div></div>
          </section>
        </div>
      </section>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
