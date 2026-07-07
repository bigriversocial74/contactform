<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.commerce.view');
$page_title = 'Stamp Monthly Close | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-package-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-package-moderation.css','/assets/css/stamp-ledger.css'];
$adminActive = 'stamp-monthly-close';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-package-shell" data-stamp-monthly-close-page>
      <header class="mg-admin-package-hero">
        <div>
          <a class="mg-admin-package-back" href="/admin/stamp-operations.php">← Stamp operations</a>
          <span class="mg-eyebrow">Finance close</span>
          <h1>Stamp ledger export + monthly close</h1>
          <p>Review a read-only monthly close snapshot before exporting Stamp ledger and reconciliation CSV files for finance, admin review, and operational controls.</p>
        </div>
        <div class="mg-admin-package-hero-actions"><span>Close status</span><strong data-stamp-close-status>Loading</strong><a class="mg-btn mg-btn-soft" href="/admin/stamp-operations.php">Operations</a><a class="mg-btn mg-btn-soft" href="/stamp-payment-reconciliation.php">Reconciliation</a></div>
      </header>
      <section class="mg-stamp-panel">
        <header><div><span class="mg-eyebrow">Monthly close period</span><h2>Review close snapshot</h2><p>Select a month, load the close, then export the ledger and reconciliation snapshots.</p></div><div class="mg-heading-actions"><label>Period <input type="month" data-stamp-close-period value="<?= mg_e(date('Y-m')) ?>"></label><button class="mg-btn mg-btn-primary" type="button" data-load-stamp-close>Load close</button></div></header>
        <div class="mg-form-status" data-stamp-close-message>Loading monthly close report...</div>
      </section>
      <section class="mg-admin-package-summary" aria-label="Stamp monthly close summary" style="margin-top:16px">
        <article><span>Ledger entries</span><strong data-stamp-close-count="entries">0</strong><small>Entries this period</small></article>
        <article><span>Accounts</span><strong data-stamp-close-count="accounts">0</strong><small>Active in ledger</small></article>
        <article><span>Credits</span><strong data-stamp-close-count="credits">0</strong><small>Stamps added</small></article>
        <article><span>Debits</span><strong data-stamp-close-count="debits">0</strong><small>Stamps used</small></article>
        <article><span>Exceptions</span><strong data-stamp-close-count="exceptions">0</strong><small>Needs review</small></article>
      </section>
      <section class="mg-stamp-panel" style="margin-top:16px">
        <header><div><span class="mg-eyebrow">Exports</span><h2>Close package</h2><p>Download CSV snapshots for the selected period. These exports do not alter ledger or purchase records.</p></div><div class="mg-heading-actions"><a class="mg-btn mg-btn-soft" href="#" data-stamp-close-export="ledger">Export ledger CSV</a><a class="mg-btn mg-btn-soft" href="#" data-stamp-close-export="reconciliation">Export reconciliation CSV</a></div></header>
        <div class="mg-admin-package-review-grid" style="margin-top:14px">
          <article><h3>Account balances</h3><p data-stamp-close-balances>Loading...</p></article>
          <article><h3>Purchase summary</h3><p data-stamp-close-purchases>Loading...</p></article>
          <article><h3>Source of truth</h3><p data-stamp-close-source>Stamp ledger entries, balances, purchases, and payment intents.</p></article>
        </div>
      </section>
      <section class="mg-stamp-panel" style="margin-top:16px">
        <header><div><span class="mg-eyebrow">Ledger rollup</span><h2>Entry type summary</h2><p>Credits, debits, voids, and adjustments grouped for the selected close period.</p></div></header>
        <div class="mg-stamp-action-table-wrap" style="margin-top:16px"><table class="mg-stamp-table"><thead><tr><th>Entry type</th><th>Entries</th><th>Credits</th><th>Debits</th><th>Net delta</th></tr></thead><tbody data-stamp-close-ledger-summary><tr><td colspan="5">Loading...</td></tr></tbody></table></div>
      </section>
      <section class="mg-stamp-panel" style="margin-top:16px">
        <header><div><span class="mg-eyebrow">Close exceptions</span><h2>Purchases needing review</h2><p>Any non-reconciled Stamp purchase created during the selected period.</p></div><a class="mg-btn mg-btn-soft" href="/stamp-payment-reconciliation.php?filter=review">Open review queue</a></header>
        <div class="mg-stamp-action-table-wrap" style="margin-top:16px"><table class="mg-stamp-table"><thead><tr><th>State</th><th>Purchase</th><th>Account</th><th>Payment</th><th>Total</th><th>Action</th></tr></thead><tbody data-stamp-close-exceptions><tr><td colspan="6">Loading...</td></tr></tbody></table></div>
      </section>
      <section class="mg-stamp-panel" style="margin-top:16px">
        <header><div><span class="mg-eyebrow">Recent ledger</span><h2>Latest ledger entries in period</h2><p>Read-only sample of the ledger entries included in the monthly close.</p></div></header>
        <div class="mg-stamp-action-table-wrap" style="margin-top:16px"><table class="mg-stamp-table"><thead><tr><th>When</th><th>Entry</th><th>Account</th><th>Delta</th><th>Balance after</th><th>Source</th></tr></thead><tbody data-stamp-close-recent-ledger><tr><td colspan="6">Loading...</td></tr></tbody></table></div>
      </section>
    </section>
  </div>
</section>
<script src="/assets/js/admin-stamp-monthly-close.js?v=20260707-stamp-monthly-close-v1" defer></script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
