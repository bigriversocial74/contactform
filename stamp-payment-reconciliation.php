<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.commerce.view');
$page_title = 'Stamp Payment Reconciliation | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-package-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-package-moderation.css','/assets/css/stamp-ledger.css'];
$adminActive = 'stamp-payment-reconciliation';

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-package-shell" data-stamp-payment-reconciliation-page>
      <header class="mg-admin-package-hero">
        <div>
          <a class="mg-admin-package-back" href="/admin/package-moderation.php">Back to Package moderation</a>
          <span class="mg-eyebrow">Stamp payments</span>
          <h1>Stamp payment reconciliation</h1>
          <p>Run live checkout QA, review provider/webhook state, sync provider status, reprocess signed webhooks, and flag paid-but-uncredited Stamp purchases without adding a merchant self-credit path.</p>
        </div>
        <div class="mg-admin-package-hero-actions"><span>Reconciliation</span><strong data-stamp-reconciliation-overall>Loading</strong><a class="mg-btn mg-btn-soft" href="/admin/stamp-health.php">Stamp health</a><a class="mg-btn mg-btn-soft" href="/merchant-stamps.php">Merchant view</a></div>
      </header>
      <section class="mg-admin-package-summary" aria-label="Stamp payment reconciliation summary">
        <article><span>Reconciled</span><strong data-stamp-reconciled-count>0</strong><small>Paid and credited</small></article>
        <article><span>Awaiting webhook</span><strong data-stamp-awaiting-count>0</strong><small>Payment pending</small></article>
        <article><span>Review</span><strong data-stamp-review-count>0</strong><small>Failed, mismatched, or paid/uncredited</small></article>
        <article><span>QA checks</span><strong data-stamp-qa-count>0</strong><small>Runtime readiness</small></article>
      </section>
      <section class="mg-stamp-panel" data-stamp-checkout-qa-panel>
        <header><div><span class="mg-eyebrow">Option 1</span><h2>Live checkout QA stabilization</h2><p>Checks required files, owner-scope, CSRF, provider metadata, webhook completion, admin-only manual completion, and Stripe configuration readiness.</p></div><button class="mg-btn mg-btn-primary" type="button" data-run-stamp-checkout-qa>Run checkout QA</button></header>
        <div class="mg-form-status" data-stamp-qa-message>Loading checkout QA checks...</div>
        <div class="mg-stamp-action-table-wrap" style="margin-top:16px"><table class="mg-stamp-table"><thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead><tbody data-stamp-qa-list><tr><td colspan="3">Loading...</td></tr></tbody></table></div>
      </section>
      <section class="mg-stamp-panel" data-stamp-reconciliation-panel style="margin-top:16px">
        <header><div><span class="mg-eyebrow">Option 2</span><h2>Webhook recovery + provider sync</h2><p>Filter purchases, sync provider status, view/reprocess signed webhook events, flag paid-uncredited records, retry hosted checkout, and export CSV.</p></div><div class="mg-heading-actions"><button class="mg-btn mg-btn-soft" type="button" data-export-stamp-reconciliation>Export CSV</button><button class="mg-btn mg-btn-soft" type="button" data-refresh-stamp-reconciliation>Refresh reconciliation</button></div></header>
        <div class="mg-admin-package-review-grid" style="margin-top:14px">
          <article><h3>Filter queue</h3><div class="mg-heading-actions" data-stamp-reconciliation-filters><button class="mg-btn mg-btn-primary" type="button" data-filter="all">All</button><button class="mg-btn mg-btn-soft" type="button" data-filter="review">Review needed</button><button class="mg-btn mg-btn-soft" type="button" data-filter="paid_uncredited">Paid/uncredited</button><button class="mg-btn mg-btn-soft" type="button" data-filter="awaiting_webhook">Awaiting webhook</button><button class="mg-btn mg-btn-soft" type="button" data-filter="reconciled">Reconciled</button><button class="mg-btn mg-btn-soft" type="button" data-filter="failed_payment">Failed/cancelled</button></div></article>
          <article><h3>Search</h3><label>Purchase, account, provider, webhook, ledger<input data-stamp-reconciliation-search placeholder="Search reconciliation records"></label></article>
        </div>
        <div class="mg-form-status" data-stamp-reconciliation-message>Loading Stamp purchase reconciliation...</div>
        <div class="mg-stamp-action-table-wrap" style="margin-top:16px"><table class="mg-stamp-table"><thead><tr><th>Purchase</th><th>Account</th><th>Bundle / total</th><th>Purchase</th><th>Provider intent</th><th>Webhook</th><th>Reconciliation</th><th>Actions</th></tr></thead><tbody data-stamp-reconciliation-list><tr><td colspan="8">Loading...</td></tr></tbody></table></div>
      </section>
    </section>
  </div>
</section>
<script src="/assets/js/admin-stamp-payment-reconciliation.js?v=20260706-stamp-webhook-recovery" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
