<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$admin_user = mg_require_admin_page_any(['commerce.manage','admin']);
$page_title = 'Bundle Settlement Adjustments | Microgifter';
$page_section = 'admin';
$page_styles = ['/assets/css/bundle-settlement-adjustments-v10.css'];
$page_scripts = ['/assets/js/bundle-settlement-adjustments-v10.js'];
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="mg-adjustments" data-adjustments-page>
  <header>
    <div><span>Product Bundles · Phase 10</span><h1>Refunds, disputes and reversals</h1><p>Review settlement adjustments and prepare provider reversal requests without claiming that funds have moved.</p></div>
    <strong>Provider dispatch controlled</strong>
  </header>
  <section class="mg-adjustments-toolbar"><button type="button" data-refresh>Refresh</button><span data-summary>Loading…</span></section>
  <section class="mg-adjustments-list" data-list aria-live="polite" aria-busy="true"></section>
</main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
