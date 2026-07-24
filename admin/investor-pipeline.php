<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$user = mg_require_admin_page_permission('admin.investor_pipeline.view');
$canManage = mg_admin_page_user_has_permission($user, 'admin.investor_pipeline.manage');
$canPublish = mg_admin_page_user_has_permission($user, 'admin.investment.publish');
$canRefreshMetrics = mg_admin_page_user_has_permission($user, 'admin.investment.metrics.refresh');
$canAi = mg_admin_page_user_has_permission($user, 'admin.investment.ai');
$page_title = 'Investor Pipeline | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-investor-pipeline-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/investment-system-v1.css?v=1.0.0','/assets/css/investor-pipeline-v2.css?v=2.0.0'];
$page_scripts = ['/assets/js/investor-pipeline-v2.js?v=2.0.0'];
$adminActive = 'investor-pipeline';
$csrfToken = mg_csrf_token();
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace mg-admin-workspace">
    <section class="mg-pipeline-app" data-investor-pipeline
      data-csrf-token="<?= mg_e($csrfToken) ?>"
      data-can-manage="<?= $canManage ? '1' : '0' ?>"
      data-can-publish="<?= $canPublish ? '1' : '0' ?>"
      data-can-refresh-metrics="<?= $canRefreshMetrics ? '1' : '0' ?>"
      data-can-ai="<?= $canAi ? '1' : '0' ?>">
      <header class="mg-pipeline-hero">
        <div>
          <a href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Investment operations</span>
          <h1>Investor Pipeline</h1>
          <p>Manage approved investors, round relationships, follow-ups, selected-round access, portal publication, live evidence snapshots, and draft-only Claude support.</p>
        </div>
        <div class="mg-pipeline-hero-actions">
          <a class="mg-btn mg-btn-ghost" href="/admin/investor-access-requests.php">Access requests</a>
          <a class="mg-btn mg-btn-soft" href="/admin/investment-wizard.php">Investment Wizard</a>
          <?php if ($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-sync-profiles>Sync approved investors</button><?php endif; ?>
        </div>
      </header>

      <nav class="mg-pipeline-tabs" aria-label="Investor operations sections">
        <button class="is-active" type="button" data-pipeline-tab="pipeline">Pipeline</button>
        <button type="button" data-pipeline-tab="publishing">Portal Publishing</button>
        <button type="button" data-pipeline-tab="metrics">Live Evidence</button>
      </nav>

      <div class="mg-investment-notice" data-pipeline-notice role="status" aria-live="polite"></div>

      <section data-tab-panel="pipeline">
        <div class="mg-pipeline-stats" data-pipeline-stats></div>
        <form class="mg-pipeline-filter" data-pipeline-filter>
          <label><span>Search</span><input name="q" placeholder="Investor, firm, or email"></label>
          <label><span>Stage</span><select name="stage"><option value="">All stages</option><option value="approved">Approved</option><option value="qualified">Qualified</option><option value="contacted">Contacted</option><option value="meeting_scheduled">Meeting Scheduled</option><option value="due_diligence">Due Diligence</option><option value="interested">Interested</option><option value="soft_committed">Soft Committed</option><option value="signed">Signed</option><option value="funded">Funded</option><option value="passed">Passed</option><option value="declined">Declined</option><option value="archived">Archived</option></select></label>
          <label><span>Priority</span><select name="priority"><option value="">All priorities</option><option value="critical">Critical</option><option value="high">High</option><option value="normal">Normal</option><option value="low">Low</option></select></label>
          <button class="mg-btn mg-btn-soft" type="submit">Apply filters</button>
          <button class="mg-btn mg-btn-ghost" type="button" data-pipeline-refresh>Refresh</button>
        </form>
        <section class="mg-investment-panel mg-pipeline-table-panel">
          <header><div><span>Approved investor operations</span><h2>Investor pipeline</h2><p data-pipeline-summary>Loading approved investors…</p></div></header>
          <div class="mg-investment-table-wrap"><table class="mg-investment-table mg-pipeline-table"><thead><tr><th>Investor</th><th>Stage</th><th>Priority</th><th>Score</th><th>Next follow-up</th><th>Round totals</th><th>Tasks</th><th></th></tr></thead><tbody data-pipeline-list></tbody></table></div>
        </section>
      </section>

      <section data-tab-panel="publishing" hidden>
        <div class="mg-publishing-layout">
          <aside class="mg-investment-panel mg-round-list-panel"><header><div><span>Official rounds</span><h2>Portal publication</h2><p>Select a round to control what approved investors can see.</p></div></header><div data-publication-rounds></div></aside>
          <main class="mg-investment-panel" data-publication-editor><div class="mg-investment-empty"><h2>Select an official round.</h2><p>Review the Investor View Preview before private publication.</p></div></main>
        </div>
      </section>

      <section data-tab-panel="metrics" hidden>
        <div class="mg-evidence-toolbar">
          <div><span class="mg-eyebrow">Governed evidence adapters</span><h2>Live Evidence & Snapshots</h2><p>Refresh supported canonical Microgifter measures and create dated, traceable investment snapshots.</p></div>
          <?php if ($canRefreshMetrics): ?><button class="mg-btn mg-btn-primary" type="button" data-refresh-metrics>Refresh selected metrics</button><?php endif; ?>
        </div>
        <div class="mg-evidence-layout">
          <section class="mg-investment-panel"><header><div><span>Adapter registry</span><h2>Available metrics</h2></div></header><div data-metric-adapters></div></section>
          <section class="mg-investment-panel"><header><div><span>Snapshot history</span><h2>Dated evidence</h2></div></header><div data-metric-history><p>Select a round workspace to load history.</p></div></section>
        </div>
      </section>
    </section>
  </main>
</section>
<div class="mg-investment-drawer-layer" data-pipeline-drawer-layer hidden>
  <button class="mg-investment-drawer-backdrop" type="button" data-pipeline-close aria-label="Close investor detail"></button>
  <aside class="mg-investment-drawer mg-pipeline-drawer" role="dialog" aria-modal="true" tabindex="-1">
    <header><div><span>Investor operations</span><h2 data-pipeline-drawer-title>Investor detail</h2><p data-pipeline-drawer-subtitle></p></div><button type="button" data-pipeline-close aria-label="Close">×</button></header>
    <div class="mg-investment-drawer-body" data-pipeline-detail></div>
  </aside>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
