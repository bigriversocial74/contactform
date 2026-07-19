<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title='Bundle Order | Microgifter';
$page_section='account';
$page_styles=['/assets/css/bundle-storefront-v4.css','/assets/css/bundle-lifecycle-v5.css','/assets/css/bundle-delivery-v6.css'];
$page_scripts=['/assets/js/bundle-delivery-v6.js'];
$order_id=trim((string)($_GET['id']??''));
require __DIR__ . '/includes/header.php';
?>
<main class="mg-bundle-storefront" data-bundle-order data-order-id="<?=mg_e($order_id)?>">
  <section class="mg-bundle-order-shell" data-bundle-order-content aria-live="polite" aria-busy="true">
    <div class="mg-bundle-lifecycle-empty">Loading your bundle lifecycle…</div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
