<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/admin-auth.php';

$user = mg_require_admin_page_any(mg_admin_commerce_read_permissions());
$canViewCommerce = true;
$page_title = 'Commerce Operations | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-commerce-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-commerce.css'];
$page_scripts = ['/assets/js/admin-commerce.js'];
$adminActive = 'commerce';

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-commerce-shell" data-commerce-root>
      <header class="mg-commerce-hero">
        <div>
          <a class="mg-commerce-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Financial and lifecycle operations</span>
          <h1>Commerce operations</h1>
          <p>Inspect orders, payments, refunds, disputes, subscriptions, tips, and Microgift lifecycle activity from one protected workspace.</p>
        </div>
        <div class="mg-commerce-hero-actions"><span>Updated <strong data-commerce-updated>—</strong></span><button class="mg-btn mg-btn-ghost" type="button" data-commerce-refresh disabled>Refresh</button></div>
      </header>

      <section class="mg-commerce-metrics" data-commerce-metrics aria-label="Commerce operations summary"></section>
      <form class="mg-commerce-filters" data-commerce-filters role="search">
        <label class="is-search">Search<input type="search" name="q" maxlength="160" autocomplete="off" placeholder="Reference, person, merchant, or title"></label>
        <label>Domain<select name="domain"><option value="all">All activity</option><option value="order">Orders</option><option value="refund">Refunds</option><option value="dispute">Disputes</option><option value="subscription">Subscriptions</option><option value="tip">Tips</option><option value="microgift">Microgifts</option><option value="case">Review cases</option></select></label>
        <label>Status<select name="status"><option value="">Any status</option><option value="attention">Needs attention</option><option value="paid">Paid</option><option value="failed">Failed</option><option value="disputed">Disputed</option><option value="pending">Pending</option><option value="active">Active</option><option value="paused">Paused</option><option value="posted">Posted</option><option value="reversed">Reversed</option><option value="redeemed">Redeemed</option><option value="open">Open</option><option value="reviewing">Reviewing</option><option value="resolved">Resolved</option></select></label>
        <label>Case priority<select name="priority"><option value="">Any priority</option><option value="urgent">Urgent</option><option value="high">High</option><option value="normal">Normal</option><option value="low">Low</option></select></label>
        <label>From<input type="date" name="date_from"></label><label>To<input type="date" name="date_to"></label>
        <div class="mg-commerce-filter-actions"><button class="mg-btn mg-btn-primary" type="submit">Apply filters</button><button class="mg-btn mg-btn-ghost" type="reset">Reset</button></div>
      </form>

      <section class="mg-commerce-panel">
        <header class="mg-commerce-panel-head"><div><h2>Operational activity</h2><p data-commerce-summary>Loading commerce activity…</p></div><span class="mg-commerce-protected">Permission gated</span></header>
        <div class="mg-commerce-live" data-commerce-live role="status" aria-live="polite"></div>
        <div class="mg-commerce-state" data-commerce-loading><strong>Loading commerce operations</strong><span>Preparing cross-domain financial and lifecycle context.</span></div>
        <div class="mg-commerce-state mg-hidden" data-commerce-error role="alert"><strong>Unable to load commerce operations</strong><span data-commerce-error-message>The workspace could not be loaded.</span><button class="mg-btn mg-btn-soft" type="button" data-commerce-retry>Try again</button></div>
        <div class="mg-commerce-state mg-hidden" data-commerce-empty><strong>No matching activity</strong><span>Try broader search, domain, status, priority, or date filters.</span></div>
        <div class="mg-commerce-table-wrap mg-hidden" data-commerce-content><table class="mg-commerce-table"><thead><tr><th>Activity</th><th>Status</th><th>Amount</th><th>Merchant / recipient</th><th>Customer / sender</th><th>Created</th><th></th></tr></thead><tbody data-commerce-list></tbody></table></div>
        <footer class="mg-commerce-pagination mg-hidden" data-commerce-pagination><span data-commerce-page-label></span><div><button class="mg-btn mg-btn-ghost" type="button" data-commerce-prev>Previous</button><button class="mg-btn mg-btn-soft" type="button" data-commerce-next>Next</button></div></footer>
      </section>

      <div class="mg-commerce-drawer-layer mg-hidden" data-commerce-drawer-layer>
        <button class="mg-commerce-drawer-backdrop" type="button" data-commerce-close aria-label="Close commerce detail"></button>
        <aside class="mg-commerce-drawer" data-commerce-drawer role="dialog" aria-modal="true" aria-labelledby="mg-commerce-drawer-title" tabindex="-1">
          <header class="mg-commerce-drawer-head"><div><span class="mg-eyebrow" data-commerce-drawer-domain>Commerce detail</span><h2 id="mg-commerce-drawer-title" data-commerce-drawer-title>Activity detail</h2><p data-commerce-drawer-subtitle>Protected operational context.</p></div><button class="mg-commerce-drawer-close" type="button" data-commerce-close aria-label="Close commerce detail">×</button></header>
          <div class="mg-commerce-drawer-body">
            <div class="mg-commerce-state" data-commerce-detail-loading><strong>Loading detail</strong><span>Preparing lifecycle, payment, ledger, and review context.</span></div>
            <div class="mg-commerce-state mg-hidden" data-commerce-detail-error role="alert"><strong>Unable to load detail</strong><span data-commerce-detail-error-message>The detail request failed.</span><button class="mg-btn mg-btn-soft" type="button" data-commerce-detail-retry>Try again</button></div>
            <div class="mg-commerce-detail mg-hidden" data-commerce-detail-content>
              <section class="mg-commerce-detail-section"><header><div><h3>Overview</h3><p>Canonical status, people, amount, and timestamps.</p></div></header><div class="mg-commerce-facts" data-commerce-facts></div></section>
              <section class="mg-commerce-detail-section"><header><div><h3>Lifecycle timeline</h3><p>Newest financial and fulfillment events first.</p></div></header><div class="mg-commerce-timeline" data-commerce-timeline></div></section>
              <section class="mg-commerce-detail-section"><header><div><h3>Related records</h3><p>Bounded supporting records from canonical domain tables.</p></div></header><div class="mg-commerce-related" data-commerce-related></div></section>
              <section class="mg-commerce-detail-section"><header><div><h3>Operations cases</h3><p>Open, assign, document, resolve, dismiss, or reopen a review case.</p></div></header><div data-commerce-cases></div></section>
              <section class="mg-commerce-detail-section mg-commerce-actions"><header><div><h3>Protected actions</h3><p>Every action requires a reason and confirmation.</p></div><span class="mg-commerce-protected">Audited</span></header><div data-commerce-actions></div></section>
            </div>
          </div>
        </aside>
      </div>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>