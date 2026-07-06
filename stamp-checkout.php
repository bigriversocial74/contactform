<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Stamp Checkout | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/stamp-ledger.css','/assets/css/merchant-stamps-ledger.css'];
$page_scripts = ['/assets/js/stamp-checkout.js?v=20260706-stamp-checkout'];
$purchase_id = trim((string)($_GET['purchase'] ?? ''));
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-merchant-app">
  <main class="mg-app-workspace mg-merchant-main" style="max-width:980px;margin:0 auto;width:100%;">
    <section class="mg-stamp-ledger-workspace" data-stamp-checkout data-purchase-id="<?= mg_e($purchase_id) ?>">
      <section class="mg-app-panel mg-stamp-ledger-panel">
        <div class="mg-app-panel-head mg-stamp-ledger-panel-head">
          <div>
            <a class="mg-admin-package-back" href="/merchant-stamps.php#stamp-purchases">← Back to Stamp ledger</a>
            <span class="mg-eyebrow">Secure Stamp checkout</span>
            <h1>Complete Stamp purchase</h1>
            <p>Your Stamp bundle is registered. Stamps are credited only after verified payment or admin review.</p>
          </div>
        </div>
        <div class="mg-app-panel-body" data-stamp-checkout-content>
          <div class="mg-empty-state">Loading Stamp checkout…</div>
        </div>
      </section>
    </section>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
