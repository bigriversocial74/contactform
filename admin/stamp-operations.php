<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.commerce.view');
$page_title = 'Stamp Operations | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-package-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-package-moderation.css','/assets/css/stamp-ledger.css'];
$adminActive = 'stamp-operations';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-package-shell" data-stamp-operations-page>
      <header class="mg-admin-package-hero">
        <div>
          <a class="mg-admin-package-back" href="/admin/package-moderation.php">← Package moderation</a>
          <span class="mg-eyebrow">Stamp operations</span>
          <h1>Stamp operations dashboard</h1>
          <p>A read-only command view for Stamp purchase risk, reconciliation state, recovery actions, and quick triage. The Stamp purchase ledger remains the source of truth.</p>
        </div>
        <div class="mg-admin-package-hero-actions"><span>Ops status</span><strong data-stamp-ops-status>Loading</strong><a class="mg-btn mg-btn-soft" href="/admin/stamp-monthly-close.php">Monthly close</a><a class="mg-btn mg-btn-soft" href="/stamp-payment-reconciliation.php">Reconciliation</a><a class="mg-btn mg-btn-soft" href="/admin/stamp-health.php">Stamp health</a></div>
      </header>
      <section class="mg-admin-package-summary" aria-label="Stamp operations summary">
        <article><span>Needs attention</span><strong data-stamp-ops-count="needs_attention">0</strong><small>Open risk queue</small></article>
        <article><span>Paid/uncredited</span><strong data-stamp-ops-count="paid_uncredited">0</strong><small>Verified-payment review</small></article>
        <article><span>Awaiting webhook</span><strong data-stamp-ops-count="awaiting_webhook">0</strong><small>Provider pending</small></article>
        <article><span>Failed/cancelled</span><strong data-stamp-ops-count="failed_payment">0</strong><small>Payment exceptions</small></article>
        <article><span>Reconciled</span><strong data-stamp-ops-count="reconciled">0</strong><small>Paid and credited</small></article>
      </section>
      <section class="mg-stamp-panel">
        <header><div><span class="mg-eyebrow">Quick links</span><h2>Reconciliation queues</h2><p>Jump directly into the filtered reconciliation table for investigation and recovery actions.</p></div><button class="mg-btn mg-btn-primary" type="button" data-refresh-stamp-ops>Refresh operations</button></header>
        <div class="mg-form-status" data-stamp-ops-message>Loading Stamp operations...</div>
        <div class="mg-admin-package-review-grid" style="margin-top:14px" data-stamp-ops-links></div>
      </section>
      <section class="mg-stamp-panel" style="margin-top:16px">
        <header><div><span class="mg-eyebrow">Priority queue</span><h2>Recent risky Stamp purchases</h2><p>Sorted by operational priority, then most recent. Use the links to open the filtered reconciliation row.</p></div><a class="mg-btn mg-btn-soft" href="/stamp-payment-reconciliation.php?filter=review">Open full review queue</a></header>
        <div class="mg-stamp-action-table-wrap" style="margin-top:16px"><table class="mg-stamp-table"><thead><tr><th>Priority</th><th>Purchase</th><th>Account</th><th>Status</th><th>Payment</th><th>Total</th><th>Action</th></tr></thead><tbody data-stamp-ops-risk-list><tr><td colspan="7">Loading...</td></tr></tbody></table></div>
      </section>
      <section class="mg-stamp-panel" style="margin-top:16px">
        <header><div><span class="mg-eyebrow">Audit</span><h2>Recent recovery actions</h2><p>Provider sync, webhook recovery, reconciliation actions, paid/uncredited flags, and verified admin recovery credit events from audit logs.</p></div><a class="mg-btn mg-btn-soft" href="/admin/audit-logs.php">Open audit logs</a></header>
        <div class="mg-stamp-action-table-wrap" style="margin-top:16px"><table class="mg-stamp-table"><thead><tr><th>When</th><th>Action</th><th>Actor</th><th>Purchase</th><th>Provider</th><th>Open</th></tr></thead><tbody data-stamp-ops-action-list><tr><td colspan="6">Loading...</td></tr></tbody></table></div>
      </section>
    </section>
  </div>
</section>
<script src="/assets/js/admin-stamp-operations-dashboard.js?v=20260707-stamp-ops-v1" defer></script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
