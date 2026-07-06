<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.commerce.view');
$page_title = 'Stamp Enforcement | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-package-page mg-admin-stamp-enforcement-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-package-moderation.css','/assets/css/stamp-ledger.css'];
$page_scripts = ['/assets/js/admin-stamp-enforcement.js?v=20260706-stamp-gate-v1'];
$adminActive = 'stamp-enforcement';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-package-shell" data-stamp-enforcement-page>
      <header class="mg-admin-package-hero">
        <div>
          <a class="mg-admin-package-back" href="/admin/stamp-health.php">← Stamp health</a>
          <span class="mg-eyebrow">Service Gate v1</span>
          <h1>Stamp enforcement audit</h1>
          <p>Verify every billable merchant service is tied to the configured Stamp action cost. Costs come from the Stamp actions catalog, not hard-coded endpoint values.</p>
        </div>
        <div class="mg-admin-package-hero-actions">
          <span>Gate status</span>
          <strong data-enforcement-status>Loading</strong>
        </div>
      </header>

      <section class="mg-admin-package-summary" aria-label="Stamp enforcement summary">
        <article><span>Services</span><strong data-enforcement-total>—</strong><small>Tracked services</small></article>
        <article><span>Enforced</span><strong data-enforcement-enforced>—</strong><small>Central/debit gated</small></article>
        <article><span>Needs attention</span><strong data-enforcement-attention>—</strong><small>Broken/missing marker</small></article>
        <article><span>Needs review</span><strong data-enforcement-review>—</strong><small>Policy not wired</small></article>
        <article><span>Actions</span><strong data-enforcement-actions>—</strong><small>Configured Stamp actions</small></article>
      </section>

      <section class="mg-stamp-panel">
        <header>
          <div>
            <span class="mg-eyebrow">Configured costs + code enforcement</span>
            <h2>Billable service matrix</h2>
            <p>The Stamp value column is resolved from the existing Stamp action cost admin/table. Change costs in the Stamp actions catalog and the Service Gate will use that value.</p>
          </div>
          <div class="mg-heading-actions">
            <a class="mg-btn mg-btn-soft" href="/admin/package-moderation.php#pkg-tab-actions">Stamp actions</a>
            <button class="mg-btn mg-btn-primary" type="button" data-run-enforcement-audit>Run audit</button>
          </div>
        </header>
        <div class="mg-form-status" data-enforcement-message>Loading Stamp enforcement audit…</div>
        <div class="mg-stamp-action-table-wrap" style="margin-top:16px">
          <table class="mg-stamp-table">
            <thead>
              <tr>
                <th>Service</th>
                <th>Action key</th>
                <th>Stamp value</th>
                <th>Status</th>
                <th>Enforced in</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody data-enforcement-list><tr><td colspan="6">Loading…</td></tr></tbody>
          </table>
        </div>
      </section>

      <section class="mg-stamp-panel" style="margin-top:16px">
        <header>
          <div>
            <span class="mg-eyebrow">Service Gate contract</span>
            <h2>Shared helper</h2>
            <p>New endpoints should call <code>mg_stamp_require_service()</code> before creating wallet items, campaign sends, paid promotions, or other billable merchant actions.</p>
          </div>
          <span class="mg-package-status">stamp_service_gate_v1</span>
        </header>
        <pre class="mg-code-block">mg_stamp_require_service($pdo, $merchantUserId, $actorUserId, $serviceKey, $quantity, $idempotencyKey, [
  'source_type' => 'merchant_service',
  'source_id' => $publicId,
  'metadata' => [...]
]);</pre>
      </section>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
