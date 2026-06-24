<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.commerce.view');
$page_title = 'Stamp Health | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-package-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-package-moderation.css','/assets/css/stamp-ledger.css'];
$adminActive = 'stamp-health';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-package-shell" data-stamp-health-page>
      <header class="mg-admin-package-hero">
        <div><a class="mg-admin-package-back" href="/admin/package-moderation.php">← Package moderation</a><span class="mg-eyebrow">Verification</span><h1>Stamp system health</h1><p>Verify Stamp migrations, required tables, API files, debit actions, bundles, provider webhook readiness, and production launch blockers.</p></div>
        <div class="mg-admin-package-hero-actions"><span>Status</span><strong data-stamp-health-status>Loading</strong></div>
      </header>
      <section class="mg-admin-package-summary" aria-label="Stamp health summary">
        <article><span>System</span><strong data-health-overall>—</strong><small>Overall readiness</small></article>
        <article><span>Tables</span><strong data-health-tables>—</strong><small>Schema checks</small></article>
        <article><span>Files</span><strong data-health-files>—</strong><small>Endpoint checks</small></article>
        <article><span>Actions</span><strong data-health-actions>—</strong><small>Enabled sends</small></article>
        <article><span>Bundles</span><strong data-health-bundles>—</strong><small>Active packages</small></article>
      </section>
      <section class="mg-stamp-panel">
        <header><div><span class="mg-eyebrow">Readiness checklist</span><h2>Health checks</h2><p>Run this after applying migrations and before testing live Stamp purchases or provider delivery webhooks.</p></div><button class="mg-btn mg-btn-primary" type="button" data-run-stamp-health>Run health check</button></header>
        <div class="mg-form-status" data-stamp-health-message>Loading Stamp health checks…</div>
        <div class="mg-stamp-action-table-wrap" style="margin-top:16px"><table class="mg-stamp-table"><thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead><tbody data-stamp-health-list><tr><td colspan="3">Loading…</td></tr></tbody></table></div>
      </section>
      <section class="mg-stamp-panel" style="margin-top:16px">
        <header><div><span class="mg-eyebrow">CLI</span><h2>Server-side verification</h2><p>Use this runner after migrations are applied on staging/production.</p></div><span class="mg-package-status">Script</span></header>
        <pre class="mg-code-block">php scripts/stamp_health_check.php
php scripts/stamp_monthly_renewal.php --dry-run</pre>
      </section>
    </section>
  </div>
</section>
<script src="/assets/js/admin-stamp-health.js" defer></script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
