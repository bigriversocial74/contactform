<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$user=mg_require_admin_page_permission('admin.investment.diligence.view');
$canManage=mg_admin_page_user_has_permission($user,'admin.investment.diligence.manage');
$canPublish=mg_admin_page_user_has_permission($user,'admin.investment.diligence.publish');
$canEngagement=mg_admin_page_user_has_permission($user,'admin.investment.engagement.view');
$canAi=mg_admin_page_user_has_permission($user,'admin.investment.ai');
$page_title='Investor Diligence | Microgifter';$page_section='account';$header_mode='account';$page_body_class='mg-admin-investor-diligence-page';
$page_styles=['/assets/css/admin-shell.css','/assets/css/investment-system-v1.css?v=1.0.0','/assets/css/investor-diligence-v3.css?v=3.0.0'];
$page_scripts=['/assets/js/investor-diligence-v3.js?v=3.0.0'];$adminActive='investor-diligence';$csrfToken=mg_csrf_token();
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace mg-admin-workspace">
    <section class="mg-diligence-app" data-investor-diligence data-csrf-token="<?= mg_e($csrfToken) ?>" data-can-manage="<?= $canManage?'1':'0' ?>" data-can-publish="<?= $canPublish?'1':'0' ?>" data-can-engagement="<?= $canEngagement?'1':'0' ?>" data-can-ai="<?= $canAi?'1':'0' ?>">
      <header class="mg-diligence-hero">
        <div><a href="/account-admin.php">← Admin dashboard</a><span class="mg-eyebrow">Investment operations</span><h1>Investor Due Diligence</h1><p>Govern the investor data room, answer diligence requests, publish approved Q&A and updates, manage meetings, review non-binding interest, and measure engagement.</p></div>
        <div class="mg-diligence-actions"><a class="mg-btn mg-btn-ghost" href="/admin/investor-access-requests.php">Access Requests</a><a class="mg-btn mg-btn-soft" href="/admin/investment-wizard.php">Investment Wizard</a><a class="mg-btn mg-btn-primary" href="/admin/investor-pipeline.php">Investor Pipeline</a></div>
      </header>

      <div class="mg-diligence-toolbar"><label><span>Official round</span><select data-diligence-round><option value="">All rounds</option></select></label><button class="mg-btn mg-btn-soft" type="button" data-diligence-refresh>Refresh</button></div>
      <div class="mg-diligence-stats" data-diligence-stats></div>
      <nav class="mg-diligence-tabs" aria-label="Investor diligence sections">
        <button class="is-active" type="button" data-diligence-tab="dataroom">Data Room</button>
        <button type="button" data-diligence-tab="requests">Requests</button>
        <button type="button" data-diligence-tab="qa">Q&amp;A Library</button>
        <button type="button" data-diligence-tab="meetings">Meetings</button>
        <button type="button" data-diligence-tab="communications">Communications</button>
        <button type="button" data-diligence-tab="interest">Interest</button>
        <button type="button" data-diligence-tab="engagement">Engagement</button>
      </nav>
      <div class="mg-investment-notice" data-diligence-notice role="status" aria-live="polite"></div>

      <section data-diligence-panel="dataroom">
        <div class="mg-diligence-split"><section class="mg-investment-panel"><header><div><span>Governed folders</span><h2>Data-room structure</h2><p>Round-scoped folders with investor visibility controls.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-soft" type="button" data-create-folder>Add folder</button><?php endif; ?></header><div data-folder-list></div></section><section class="mg-investment-panel"><header><div><span>Versioned records</span><h2>Documents</h2><p>Approved external document references with legal-review and expiration controls.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-create-document>Add document</button><?php endif; ?></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Document</th><th>Folder</th><th>Status</th><th>Visibility</th><th>Version</th><th>Expires</th><th></th></tr></thead><tbody data-document-list></tbody></table></div></section></div>
      </section>

      <section data-diligence-panel="requests" hidden><section class="mg-investment-panel"><header><div><span>Investor-submitted diligence</span><h2>Questions and document requests</h2><p>Draft responses remain internal until explicitly published.</p></div></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Investor</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Due</th><th></th></tr></thead><tbody data-request-list></tbody></table></div></section></section>

      <section data-diligence-panel="qa" hidden><section class="mg-investment-panel"><header><div><span>Reusable approved answers</span><h2>Investor Q&amp;A Library</h2><p>Round-specific or general answers with controlled publishing.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-create-qa>Add Q&amp;A</button><?php endif; ?></header><div data-qa-list></div></section></section>

      <section data-diligence-panel="meetings" hidden><section class="mg-investment-panel"><header><div><span>Investor conversations</span><h2>Meetings and outcomes</h2><p>Prepare agendas, record sentiment, outcomes, and next steps.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-create-meeting>Schedule meeting</button><?php endif; ?></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Investor</th><th>Type</th><th>Starts</th><th>Status</th><th>Sentiment</th><th>Next step</th><th></th></tr></thead><tbody data-meeting-list></tbody></table></div></section></section>

      <section data-diligence-panel="communications" hidden><section class="mg-investment-panel"><header><div><span>Portal-published updates</span><h2>Investor Communications</h2><p>Draft and review updates before publishing them to eligible portal recipients.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-create-communication>Create update</button><?php endif; ?></header><div data-communication-list></div></section></section>

      <section data-diligence-panel="interest" hidden><section class="mg-investment-panel"><header><div><span>Non-binding submissions</span><h2>Investor Interest</h2><p>Review proposed ranges and next steps without changing signed or funded totals.</p></div></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Investor</th><th>Round</th><th>Range</th><th>Timing</th><th>Meeting</th><th>Status</th><th></th></tr></thead><tbody data-interest-list></tbody></table></div></section></section>

      <section data-diligence-panel="engagement" hidden><section class="mg-investment-panel"><header><div><span>Transparent deterministic scoring</span><h2>Investor Engagement</h2><p>100-point scores from portal, document, metric, question, communication, meeting, and recency activity.</p></div><?php if($canEngagement): ?><button class="mg-btn mg-btn-primary" type="button" data-refresh-engagement>Refresh snapshots</button><?php endif; ?></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Investor</th><th>Round</th><th>Score</th><th>Sessions</th><th>Documents</th><th>Questions</th><th>Meetings</th><th>Last activity</th><th>Status</th></tr></thead><tbody data-engagement-list></tbody></table></div></section></section>
    </section>
  </main>
</section>
<div class="mg-investment-drawer-layer" data-diligence-drawer-layer hidden><button class="mg-investment-drawer-backdrop" type="button" data-diligence-close aria-label="Close"></button><aside class="mg-investment-drawer mg-diligence-drawer" role="dialog" aria-modal="true" tabindex="-1"><header><div><span>Investor diligence</span><h2 data-diligence-drawer-title>Editor</h2><p data-diligence-drawer-subtitle></p></div><button type="button" data-diligence-close aria-label="Close">×</button></header><div class="mg-investment-drawer-body" data-diligence-drawer-body></div></aside></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
