<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$user=mg_require_admin_page_permission('admin.investment.closing.view');
$canManage=mg_admin_page_user_has_permission($user,'admin.investment.closing.manage');
$canVerify=mg_admin_page_user_has_permission($user,'admin.investment.closing.verify');
$canCompliance=mg_admin_page_user_has_permission($user,'admin.investment.compliance.manage');
$canRelations=mg_admin_page_user_has_permission($user,'admin.investment.relations.manage');
$canAi=mg_admin_page_user_has_permission($user,'admin.investment.ai');
$page_title='Investment Closing | Microgifter';$page_section='account';$header_mode='account';$page_body_class='mg-admin-investment-closing-page';
$page_styles=['/assets/css/admin-shell.css','/assets/css/investment-system-v1.css?v=1.0.0','/assets/css/investment-closing-v4.css?v=4.0.0'];
$page_scripts=['/assets/js/investment-closing-v4.js?v=4.0.0','/assets/js/investor-module-audit-v1.js?v=1.0.0'];$adminActive='investment-closing';$csrfToken=mg_csrf_token();
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace mg-admin-workspace">
    <section class="mg-closing-app" data-investment-closing
      data-csrf-token="<?= mg_e($csrfToken) ?>"
      data-can-manage="<?= $canManage?'1':'0' ?>"
      data-can-verify="<?= $canVerify?'1':'0' ?>"
      data-can-compliance="<?= $canCompliance?'1':'0' ?>"
      data-can-relations="<?= $canRelations?'1':'0' ?>"
      data-can-ai="<?= $canAi?'1':'0' ?>">
      <header class="mg-closing-hero">
        <div><a href="/account-admin.php">← Admin dashboard</a><span class="mg-eyebrow">Investment operations</span><h1>Closing Command Center</h1><p>Coordinate closing readiness, investor onboarding, externally executed documents, maker/checker financial verification, compliance deadlines, rolling closes, capitalization reconciliation, and funded-investor reporting.</p></div>
        <div class="mg-closing-hero-actions"><a class="mg-btn mg-btn-ghost" href="/admin/investor-center.php">Investor Center</a><a class="mg-btn mg-btn-ghost" href="/admin/investment-wizard.php">Investment Wizard</a><a class="mg-btn mg-btn-soft" href="/admin/investor-pipeline.php">Investor Pipeline</a><a class="mg-btn mg-btn-soft" href="/admin/investor-diligence.php">Due Diligence</a><a class="mg-btn mg-btn-soft" href="/admin/investor-governance.php">Governance</a><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-closing-sync>Sync closing records</button><?php endif; ?></div>
      </header>

      <div class="mg-closing-toolbar"><label><span>Official round</span><select data-closing-round><option value="">All rounds</option></select></label><button class="mg-btn mg-btn-soft" type="button" data-closing-refresh>Refresh</button><?php if($canManage): ?><button class="mg-btn mg-btn-ghost" type="button" data-refresh-readiness>Recalculate readiness</button><?php endif; ?></div>
      <div class="mg-closing-stats" data-closing-stats></div>
      <nav class="mg-closing-tabs" aria-label="Investment closing sections">
        <button class="is-active" type="button" data-closing-tab="overview">Overview</button>
        <button type="button" data-closing-tab="investors">Investor Closing</button>
        <button type="button" data-closing-tab="batches">Closing Batches</button>
        <button type="button" data-closing-tab="compliance">Compliance</button>
        <button type="button" data-closing-tab="verification">Financial Verification</button>
        <button type="button" data-closing-tab="packets">Document Packets</button>
        <button type="button" data-closing-tab="reconciliation">Reconciliation</button>
        <button type="button" data-closing-tab="reports">Investor Relations</button>
      </nav>
      <div class="mg-investment-notice" data-closing-notice role="status" aria-live="polite"></div>

      <section data-closing-panel="overview"><div class="mg-closing-overview" data-closing-overview><section class="mg-investment-panel mg-investment-empty"><h2>Select an official round.</h2><p>Closing readiness and blockers will appear here.</p></section></div></section>
      <section data-closing-panel="investors" hidden><section class="mg-investment-panel"><header><div><span>Controlled closing lifecycle</span><h2>Investor closing records</h2><p>Signed and funded amounts are changed only through maker/checker verification.</p></div></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Investor</th><th>Status</th><th>Instrument</th><th>Signed</th><th>Verified funded</th><th>Onboarding</th><th>Batch</th><th>Verification</th><th></th></tr></thead><tbody data-closing-record-list></tbody></table></div></section></section>
      <section data-closing-panel="batches" hidden><section class="mg-investment-panel"><header><div><span>Rolling and final closes</span><h2>Closing batches</h2><p>Completed batches become immutable and require a separately audited Super Admin reopen action.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-create-batch>Create batch</button><?php endif; ?></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Batch</th><th>Sequence</th><th>Status</th><th>Investors</th><th>Included</th><th>Planned</th><th>Actual</th><th>Approvals</th><th></th></tr></thead><tbody data-closing-batch-list></tbody></table></div></section></section>
      <section data-closing-panel="compliance" hidden><section class="mg-investment-panel"><header><div><span>Counsel-supplied tracking</span><h2>Compliance and filings</h2><p>Microgifter records externally determined statuses; it does not make legal determinations or submit filings.</p></div><div><?php if($canCompliance): ?><button class="mg-btn mg-btn-soft" type="button" data-seed-compliance>Seed standard checklist</button><button class="mg-btn mg-btn-primary" type="button" data-create-compliance>Add requirement</button><?php endif; ?></div></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Requirement</th><th>Category</th><th>Status</th><th>Counsel</th><th>Due</th><th>Assigned</th><th>Reference</th><th></th></tr></thead><tbody data-compliance-list></tbody></table></div></section></section>
      <section data-closing-panel="verification" hidden><section class="mg-investment-panel"><header><div><span>Maker/checker protection</span><h2>Signed and funded verification</h2><p>The submitting administrator cannot approve or reject their own request.</p></div></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Investor</th><th>Type</th><th>Previous</th><th>Requested</th><th>Status</th><th>Submitted by</th><th>Reviewer</th><th></th></tr></thead><tbody data-verification-list></tbody></table></div></section></section>
      <section data-closing-panel="packets" hidden><section class="mg-investment-panel"><header><div><span>Externally executed documents</span><h2>Closing document packets</h2><p>Track approved external references, signatures, counsel review, expiration, and completion.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-create-packet>Create packet</button><?php endif; ?></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Investor</th><th>Packet</th><th>Status</th><th>Required</th><th>Completed</th><th>Documents</th><th>Completed at</th><th></th></tr></thead><tbody data-packet-list></tbody></table></div></section></section>
      <section data-closing-panel="reconciliation" hidden><div class="mg-closing-split"><section class="mg-investment-panel"><header><div><span>Scenario versus actual</span><h2>Capitalization reconciliation</h2><p>Administrative estimates only; not the official corporate stock ledger.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-create-reconciliation>Create snapshot</button><?php endif; ?></header><div data-reconciliation-list><p>Select a round to load reconciliation history.</p></div></section><section class="mg-investment-panel"><header><div><span>Draft-only support</span><h2>Claude Closing Assistant</h2><p>Creates internal drafts and cannot verify funds, approve documents, file notices, sign agreements, or issue securities.</p></div></header><div data-closing-ai></div></section></div></section>
      <section data-closing-panel="reports" hidden><div class="mg-closing-split"><section class="mg-investment-panel"><header><div><span>Funded-investor reporting</span><h2>Reporting periods</h2><p>Publish immutable report versions to maker/checker verified funded investors.</p></div><?php if($canRelations): ?><button class="mg-btn mg-btn-primary" type="button" data-create-period>Create period</button><?php endif; ?></header><div data-report-period-list></div></section><section class="mg-investment-panel"><header><div><span>Actual versus plan</span><h2>Use-of-funds actuals</h2><p>Only explicitly investor-visible, evidence-backed records appear in the Investor Portal.</p></div><?php if($canRelations): ?><button class="mg-btn mg-btn-soft" type="button" data-create-actual>Add actual</button><?php endif; ?></header><div data-use-actual-list></div></section></div></section>
    </section>
  </main>
</section>
<div class="mg-investment-drawer-layer" data-closing-drawer-layer hidden><button class="mg-investment-drawer-backdrop" type="button" data-closing-close aria-label="Close"></button><aside class="mg-investment-drawer mg-closing-drawer" role="dialog" aria-modal="true" tabindex="-1"><header><div><span>Investment closing</span><h2 data-closing-drawer-title>Editor</h2><p data-closing-drawer-subtitle></p></div><button type="button" data-closing-close aria-label="Close">×</button></header><div class="mg-investment-drawer-body" data-closing-drawer-body></div></aside></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
