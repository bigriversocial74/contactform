<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$admin_user = mg_require_admin_page_any(['commerce.manage','admin']);
$page_title = 'Bundle Provider Reconciliation | Microgifter';
$page_section = 'admin';
$page_styles = ['/assets/css/bundle-provider-reconciliation-v11.css'];
$page_scripts = ['/assets/js/bundle-provider-reconciliation-v11.js'];
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="mg-provider-reconciliation" data-provider-reconciliation-page>
  <header>
    <div><span>Product Bundles · Phase 11</span><h1>Provider dispatch & reconciliation</h1><p>Review queued, submitted, succeeded, failed, and reversed Stripe settlement transfers.</p></div>
    <button type="button" data-refresh>Refresh</button>
  </header>
  <section class="mg-provider-summary" data-summary aria-live="polite"></section>
  <section class="mg-provider-grid">
    <div><h2>Transfers</h2><div data-transfer-list aria-busy="true"></div></div>
    <div><h2>Provider events</h2><div data-event-list aria-busy="true"></div></div>
  </section>
</main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
