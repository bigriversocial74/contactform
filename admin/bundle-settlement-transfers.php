<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$admin_user=mg_require_admin_page_any(['commerce.manage','admin']);
$page_title='Bundle Settlement Transfers | Microgifter';
$page_section='admin';
$page_styles=['/assets/css/bundle-settlement-transfer-v9.css'];
$page_scripts=['/assets/js/bundle-settlement-transfer-v9.js'];
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="mg-transfer-ops" data-transfer-page>
  <header><div><span>Product Bundles · Phase 9</span><h1>Transfer operations</h1><p>Queue release-ready merchant settlements for Stripe Connect dispatch. Provider execution remains adapter-controlled.</p></div><strong data-transfer-gate>Checking gate…</strong></header>
  <section class="mg-transfer-toolbar"><button type="button" data-refresh>Refresh queue</button><span data-transfer-summary>Loading…</span></section>
  <section class="mg-transfer-list" data-transfer-list aria-live="polite" aria-busy="true"></section>
</main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
