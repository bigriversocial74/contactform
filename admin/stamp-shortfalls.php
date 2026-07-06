<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_any(['security.logs.view', 'admin.security_logs.view', 'admin.commerce.view']);
$page_title = 'Stamp Shortfalls | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-package-page mg-admin-stamp-shortfalls-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-package-moderation.css','/assets/css/stamp-ledger.css'];
$page_scripts = ['/assets/js/admin-stamp-shortfalls.js?v=20260706-stamp-shortfalls'];
$adminActive = 'stamp-shortfalls';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-package-shell" data-stamp-shortfalls-page>
      <header class="mg-admin-package-hero">
        <div>
          <a class="mg-admin-package-back" href="/admin/stamp-health.php">← Stamp health</a>
          <span class="mg-eyebrow">Stamp operations</span>
          <h1>Stamp shortfalls</h1>
          <p>Review customer regifts that were allowed even though the originating merchant did not have enough Stamps to sponsor the regift debit.</p>
        </div>
        <div class="mg-admin-package-hero-actions">
          <span>Status</span>
          <strong data-shortfall-status>Loading</strong>
        </div>
      </header>

      <section class="mg-admin-package-summary" aria-label="Stamp shortfall summary">
        <article><span>Open events</span><strong data-shortfall-count>—</strong><small>Shortfall records loaded</small></article>
        <article><span>Total shortfall</span><strong data-shortfall-total>—</strong><small>Unfunded Stamps</small></article>
        <article><span>Required</span><strong data-shortfall-required>—</strong><small>Requested debits</small></article>
        <article><span>Merchants</span><strong data-shortfall-merchants>—</strong><small>Unique sponsors</small></article>
      </section>

      <section class="mg-stamp-panel">
        <header>
          <div>
            <span class="mg-eyebrow">Merchant-sponsored regifts</span>
            <h2>Shortfall report</h2>
            <p>Use this report to find merchants that need Stamp credits, billing follow-up, or package review. Customer regifts remain smooth; merchant liability is tracked here.</p>
          </div>
          <div class="mg-heading-actions">
            <label style="display:grid;gap:4px;font-size:.72rem;font-weight:800;color:#64748b">Limit
              <input type="number" min="10" max="200" value="100" data-shortfall-limit style="max-width:96px">
            </label>
            <button class="mg-btn mg-btn-primary" type="button" data-run-shortfall-report>Refresh report</button>
          </div>
        </header>
        <div class="mg-form-status" data-shortfall-message>Loading Stamp shortfalls…</div>
        <div class="mg-stamp-action-table-wrap" style="margin-top:16px">
          <table class="mg-stamp-table">
            <thead>
              <tr>
                <th>Merchant sponsor</th>
                <th>Customer actor</th>
                <th>Source</th>
                <th>Shortfall</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody data-shortfall-list><tr><td colspan="6">Loading…</td></tr></tbody>
          </table>
        </div>
      </section>

      <section class="mg-stamp-panel" style="margin-top:16px">
        <header>
          <div>
            <span class="mg-eyebrow">Resolution path</span>
            <h2>How to resolve</h2>
            <p>Open the merchant in User Center, use the Stamps section to add Stamps or review package status, then confirm future regifts debit normally.</p>
          </div>
          <a class="mg-btn mg-btn-soft" href="/admin/users.php">Open User Center</a>
        </header>
      </section>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
