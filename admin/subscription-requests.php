<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('subscriptions.admin');
$page_title = 'Subscription Requests | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-subscription-requests-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-subscription-requests.css'];
$page_scripts = ['/assets/js/admin-subscription-requests.js'];
$adminActive = 'subscription-requests';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-subreq-shell" data-admin-subscription-requests>
      <header class="mg-admin-subreq-hero">
        <div>
          <a class="mg-admin-subreq-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Subscription operations</span>
          <h1>Subscription requests</h1>
          <p>Review package upgrade, downgrade, Enterprise, and payment-required package changes submitted from the My Subscription dashboard.</p>
        </div>
        <div class="mg-admin-subreq-hero-actions">
          <span>Last updated <strong data-subreq-updated>—</strong></span>
          <button class="mg-btn mg-btn-primary" type="button" data-subreq-refresh>Refresh</button>
        </div>
      </header>

      <section class="mg-admin-subreq-summary" aria-label="Subscription package request summary">
        <article><span>Total loaded</span><strong data-subreq-stat="total">—</strong><small>Most recent requests</small></article>
        <article><span>Needs review</span><strong data-subreq-stat="pending_admin_review">—</strong><small>Admin action required</small></article>
        <article><span>Payment required</span><strong data-subreq-stat="pending_payment">—</strong><small>Checkout handoff</small></article>
        <article><span>Approved</span><strong data-subreq-stat="completed">—</strong><small>Marked active</small></article>
        <article><span>Closed</span><strong data-subreq-stat="closed">—</strong><small>Rejected or canceled</small></article>
      </section>

      <form class="mg-admin-subreq-filters" data-subreq-filters role="search">
        <label class="is-search">Search
          <input type="search" name="q" maxlength="160" autocomplete="off" placeholder="Email, name, package, status, or request ID">
        </label>
        <label>Status
          <select name="status">
            <option value="pending">Pending</option>
            <option value="all">All requests</option>
            <option value="pending_admin_review">Pending admin review</option>
            <option value="pending_payment">Payment required</option>
            <option value="completed">Completed</option>
            <option value="closed">Closed</option>
          </select>
        </label>
        <label>Package
          <select name="package">
            <option value="">All packages</option>
            <option value="starter">Starter</option>
            <option value="growth">Growth</option>
            <option value="pro">Pro</option>
            <option value="enterprise">Enterprise</option>
          </select>
        </label>
        <div class="mg-admin-subreq-filter-actions">
          <button class="mg-btn mg-btn-primary" type="submit">Apply filters</button>
          <button class="mg-btn mg-btn-ghost" type="reset" data-subreq-reset>Reset</button>
        </div>
      </form>

      <section class="mg-admin-subreq-panel">
        <header class="mg-admin-subreq-panel-head">
          <div>
            <h2>Package change queue</h2>
            <p data-subreq-summary>Loading package requests…</p>
          </div>
          <span class="mg-admin-subreq-readonly">Admin review</span>
        </header>

        <div class="mg-admin-subreq-status" data-subreq-status role="status" aria-live="polite"></div>

        <div class="mg-admin-subreq-state" data-subreq-loading aria-busy="true">
          <strong>Loading subscription requests</strong>
          <span>Preparing package-change queue.</span>
        </div>

        <div class="mg-admin-subreq-state mg-hidden" data-subreq-error role="alert">
          <strong>Unable to load requests</strong>
          <span data-subreq-error-message>The subscription request queue could not be loaded.</span>
          <button class="mg-btn mg-btn-soft" type="button" data-subreq-retry>Try again</button>
        </div>

        <div class="mg-admin-subreq-state mg-hidden" data-subreq-empty>
          <strong>No matching package requests</strong>
          <span>Try changing the status, package, or search filter.</span>
        </div>

        <div class="mg-admin-subreq-table-wrap mg-hidden" data-subreq-content>
          <table class="mg-admin-subreq-table">
            <thead>
              <tr>
                <th scope="col">Request</th>
                <th scope="col">Account</th>
                <th scope="col">Package change</th>
                <th scope="col">Status</th>
                <th scope="col">Billing</th>
                <th scope="col">Updated</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody data-subreq-list></tbody>
          </table>
        </div>
      </section>

      <div class="mg-admin-subreq-review-layer mg-hidden" data-subreq-review-layer>
        <button class="mg-admin-subreq-review-backdrop" type="button" data-subreq-review-close aria-label="Close review dialog"></button>
        <aside class="mg-admin-subreq-review-modal" role="dialog" aria-modal="true" aria-labelledby="mg-admin-subreq-review-title">
          <header>
            <div>
              <span class="mg-eyebrow">Admin action</span>
              <h2 id="mg-admin-subreq-review-title" data-subreq-review-title>Review request</h2>
              <p data-subreq-review-subtitle>Confirm the package request action and record the operator note.</p>
            </div>
            <button class="mg-admin-subreq-review-close" type="button" data-subreq-review-close aria-label="Close review dialog">×</button>
          </header>
          <form data-subreq-review-form>
            <input type="hidden" name="request_id" data-subreq-review-id>
            <input type="hidden" name="action" data-subreq-review-action>
            <div class="mg-admin-subreq-review-context" data-subreq-review-context></div>
            <label class="mg-admin-subreq-review-note"><span>Admin note</span>
              <textarea name="note" rows="4" maxlength="2000" required placeholder="Explain why this request is being approved, rejected, or canceled."></textarea>
            </label>
            <div class="mg-admin-subreq-review-notice" data-subreq-review-notice role="status" aria-live="polite"></div>
            <footer>
              <button class="mg-btn mg-btn-ghost" type="button" data-subreq-review-close>Cancel</button>
              <button class="mg-btn mg-btn-primary" type="submit" data-subreq-review-submit>Submit review</button>
            </footer>
          </form>
        </aside>
      </div>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
