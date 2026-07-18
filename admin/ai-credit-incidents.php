<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.ai_credit_incidents');
$page_title = 'AI Credit Accounting | Microgifter Admin';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-ai-credit-incidents-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-ai-credit-incidents.css'];
$page_scripts = ['/assets/js/admin-ai-credit-incidents.js'];
$adminActive = 'operations-command';
$csrfToken = mg_csrf_token();

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <main class="mg-ai-credit-incidents" data-ai-credit-incidents data-csrf-token="<?= mg_e($csrfToken) ?>">
      <header class="mg-ai-credit-hero">
        <div>
          <a href="/admin/operations-command.php">← Operations command center</a>
          <span class="mg-ai-credit-eyebrow">Accounting controls</span>
          <h1>AI credit reconciliation</h1>
          <p>Automated provider-to-ledger verification, owner-scoped incident evidence, controlled debit recovery, and complete resolution history.</p>
        </div>
        <div class="mg-ai-credit-hero-actions">
          <label><span>Window</span><select data-run-days><option value="7">7 days</option><option value="30" selected>30 days</option><option value="90">90 days</option><option value="180">180 days</option><option value="365">365 days</option></select></label>
          <button class="mg-btn mg-btn-primary" type="button" data-run-reconciliation>Run reconciliation</button>
          <button class="mg-btn mg-btn-soft" type="button" data-refresh>Refresh</button>
        </div>
      </header>

      <section class="mg-ai-credit-status" data-status role="status" aria-live="polite">Loading AI credit incident queue…</section>

      <section class="mg-ai-credit-summary" data-summary aria-label="AI credit reconciliation summary"></section>

      <section class="mg-ai-credit-toolbar">
        <label><span>Status</span><select data-status-filter><option value="active">Active</option><option value="open">Open</option><option value="under_review">Under review</option><option value="resolved">Resolved</option><option value="dismissed">Dismissed</option><option value="">All</option></select></label>
        <label><span>Incident type</span><select data-type-filter><option value="">All types</option><option value="provider_without_ledger">Provider without ledger</option><option value="ledger_without_provider">Ledger without provider</option><option value="token_mismatch">Token mismatch</option><option value="missing_response_reference">Missing response reference</option><option value="credit_debit_failed">Credit debit failed</option><option value="preflight_state_missing">Missing preflight state</option><option value="call_context_missing">Missing call context</option></select></label>
        <div data-last-run class="mg-ai-credit-last-run"></div>
      </section>

      <section class="mg-ai-credit-layout">
        <div class="mg-ai-credit-list" data-incident-list><div class="mg-ai-credit-empty">Loading incidents…</div></div>
        <aside class="mg-ai-credit-detail" data-incident-detail><div class="mg-ai-credit-empty">Select an accounting incident to review its evidence and history.</div></aside>
      </section>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
