<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title='Bundle Orders | Microgifter';
$page_section='account';
$page_styles=['/assets/css/bundle-lifecycle-v5.css'];
$page_scripts=['/assets/js/bundle-lifecycle-v5.js'];
require __DIR__ . '/includes/header.php';
?>
<main class="mg-bundle-lifecycle-page" data-bundle-orders-page>
  <section class="mg-bundle-lifecycle-shell">
    <header class="mg-bundle-lifecycle-hero">
      <span>Action Center</span>
      <h1>Your bundle orders</h1>
      <p>Track each bundle as one parent purchase while following every included Microgift through delivery, claim, and redemption.</p>
    </header>
    <div class="mg-bundle-order-list" data-bundle-order-list aria-live="polite" aria-busy="true">
      <div class="mg-bundle-lifecycle-empty">Loading bundle orders…</div>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
