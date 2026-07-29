<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$user=mg_require_admin_page_permission('admin.investment.governance.view');
$canManage=mg_admin_page_user_has_permission($user,'admin.investment.governance.manage');
$canPublish=mg_admin_page_user_has_permission($user,'admin.investment.governance.publish');
$canVote=mg_admin_page_user_has_permission($user,'admin.investment.governance.vote');
$canRights=mg_admin_page_user_has_permission($user,'admin.investment.rights.manage');
$canObligations=mg_admin_page_user_has_permission($user,'admin.investment.obligations.manage');
$canTax=mg_admin_page_user_has_permission($user,'admin.investment.tax_documents.manage');
$canAi=mg_admin_page_user_has_permission($user,'admin.investment.ai');
$page_title='Investor Governance | Microgifter';$page_section='account';$header_mode='account';$page_body_class='mg-admin-investor-governance-page';
$page_styles=['/assets/css/admin-shell.css','/assets/css/investment-system-v1.css?v=1.0.0','/assets/css/investor-governance-v5.css?v=5.0.0'];
$page_scripts=['/assets/js/investor-governance-v5.js?v=5.0.0','/assets/js/investor-module-audit-v1.js?v=1.0.0'];$adminActive='investor-governance';$csrfToken=mg_csrf_token();
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace mg-admin-workspace">
    <section class="mg-governance-app" data-investor-governance
      data-csrf-token="<?= mg_e($csrfToken) ?>"
      data-can-manage="<?= $canManage?'1':'0' ?>"
      data-can-publish="<?= $canPublish?'1':'0' ?>"
      data-can-vote="<?= $canVote?'1':'0' ?>"
      data-can-rights="<?= $canRights?'1':'0' ?>"
      data-can-obligations="<?= $canObligations?'1':'0' ?>"
      data-can-tax="<?= $canTax?'1':'0' ?>"
      data-can-ai="<?= $canAi?'1':'0' ?>">
      <header class="mg-governance-hero">
        <div><a href="/account-admin.php">← Admin dashboard</a><span class="mg-eyebrow">Post-close governance operations</span><h1>Governance Command Center</h1><p>Coordinate board participants, appointments, meetings, packets, externally executed consents, counsel-confirmed investor rights, reporting obligations, holdings references, tax-document delivery, and governed material notices.</p></div>
        <div class="mg-governance-hero-actions"><a class="mg-btn mg-btn-ghost" href="/admin/investor-center.php">Investor Center</a><a class="mg-btn mg-btn-ghost" href="/admin/investment-wizard.php">Investment Wizard</a><a class="mg-btn mg-btn-soft" href="/admin/investor-pipeline.php">Pipeline</a><a class="mg-btn mg-btn-soft" href="/admin/investor-diligence.php">Diligence</a><a class="mg-btn mg-btn-soft" href="/admin/investment-closing.php">Closing</a></div>
      </header>

      <div class="mg-governance-toolbar"><label><span>Official round</span><select data-governance-round><option value="">All rounds</option></select></label><button class="mg-btn mg-btn-soft" type="button" data-governance-refresh>Refresh</button><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-refresh-holdings>Refresh holdings references</button><?php endif; ?></div>
      <div class="mg-governance-stats" data-governance-stats></div>
      <nav class="mg-governance-tabs" aria-label="Investor governance sections">
        <button class="is-active" type="button" data-governance-tab="overview">Overview</button>
        <button type="button" data-governance-tab="participants">Participants</button>
        <button type="button" data-governance-tab="meetings">Board Meetings</button>
        <button type="button" data-governance-tab="consents">Consents</button>
        <button type="button" data-governance-tab="rights">Investor Rights</button>
        <button type="button" data-governance-tab="obligations">Obligations</button>
        <button type="button" data-governance-tab="holdings">Holdings Reference</button>
        <button type="button" data-governance-tab="tax">Tax Documents</button>
        <button type="button" data-governance-tab="notices">Material Notices</button>
        <button type="button" data-governance-tab="assistant">Claude Assistant</button>
      </nav>
      <div class="mg-investment-notice" data-governance-notice role="status" aria-live="polite"></div>

      <section data-governance-panel="overview"><div class="mg-governance-overview" data-governance-overview><section class="mg-investment-panel mg-investment-empty"><h2>Loading governance operations…</h2></section></div></section>
      <section data-governance-panel="participants" hidden><div class="mg-governance-split"><section class="mg-investment-panel"><header><div><span>Governance directory</span><h2>Participants</h2><p>Directors, observers, officers, counsel and administrative participants.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-create-participant>Add participant</button><?php endif; ?></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Name</th><th>Type</th><th>Organization</th><th>Confidentiality</th><th>Appointments</th><th>Status</th><th></th></tr></thead><tbody data-participant-list></tbody></table></div></section><section class="mg-investment-panel"><header><div><span>Approved roles</span><h2>Appointments</h2><p>Record appointment sources and externally approved governance roles.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-soft" type="button" data-create-appointment>Add appointment</button><?php endif; ?></header><div data-appointment-list></div></section></div></section>
      <section data-governance-panel="meetings" hidden><section class="mg-investment-panel"><header><div><span>Board operations</span><h2>Meetings, packets and minutes</h2><p>Plan meetings, record attendance, structure agendas, version packet documents and preserve immutable minutes.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-create-meeting>Create meeting</button><?php endif; ?></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Meeting</th><th>Schedule</th><th>Status</th><th>Quorum</th><th>Attendees</th><th>Agenda</th><th>Packet</th><th>Minutes</th><th></th></tr></thead><tbody data-meeting-list></tbody></table></div></section></section>
      <section data-governance-panel="consents" hidden><section class="mg-investment-panel"><header><div><span>Externally executed approvals</span><h2>Written consents and resolutions</h2><p>Microgifter records approved external execution references; it does not provide electronic signatures or cast votes.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-create-consent>Create consent</button><?php endif; ?></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Consent</th><th>Type</th><th>Status</th><th>Counsel</th><th>Responses</th><th>Due</th><th>Effective</th><th></th></tr></thead><tbody data-consent-list></tbody></table></div></section></section>
      <section data-governance-panel="rights" hidden><section class="mg-investment-panel"><header><div><span>Counsel-confirmed rights</span><h2>Investor rights matrix</h2><p>Rights are recorded from approved agreements or counsel instructions and are never inferred automatically.</p></div><?php if($canRights): ?><button class="mg-btn mg-btn-primary" type="button" data-create-right>Add right</button><?php endif; ?></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Investor</th><th>Right</th><th>Cadence</th><th>Counsel</th><th>Status</th><th>Visible</th><th>Expires</th><th></th></tr></thead><tbody data-right-list></tbody></table></div></section></section>
      <section data-governance-panel="obligations" hidden><section class="mg-investment-panel"><header><div><span>Recurring reporting calendar</span><h2>Information-rights obligations</h2><p>Track preparation, internal review, counsel review, publication and completion evidence without automatically sending communications.</p></div><?php if($canObligations): ?><button class="mg-btn mg-btn-primary" type="button" data-create-obligation>Add obligation</button><?php endif; ?></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Obligation</th><th>Recipient</th><th>Due</th><th>Review</th><th>Status</th><th>Publication</th><th>Assigned</th><th></th></tr></thead><tbody data-obligation-list></tbody></table></div></section></section>
      <section data-governance-panel="holdings" hidden><section class="mg-investment-panel"><header><div><span>Administrative reference only</span><h2>Investor holdings reference</h2><p>Generated from maker/checker verified closing records. This is not the official stock ledger, transfer-agent record or legal ownership record.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-refresh-holdings>Refresh selected round</button><?php endif; ?></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Investor</th><th>Round</th><th>Instrument</th><th>Verified funded</th><th>Batch</th><th>Rights</th><th>Tax</th><th>Generated</th></tr></thead><tbody data-holdings-list></tbody></table></div></section></section>
      <section data-governance-panel="tax" hidden><section class="mg-investment-panel"><header><div><span>Externally prepared documents</span><h2>Tax and annual document delivery</h2><p>Microgifter tracks controlled references and versions; it does not prepare tax forms or provide tax advice.</p></div><?php if($canTax): ?><button class="mg-btn mg-btn-primary" type="button" data-create-tax-document>Add tax document</button><?php endif; ?></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Investor</th><th>Document</th><th>Year</th><th>Status</th><th>Version</th><th>Provider</th><th>Viewed</th><th></th></tr></thead><tbody data-tax-list></tbody></table></div></section></section>
      <section data-governance-panel="notices" hidden><section class="mg-investment-panel"><header><div><span>Counsel-directed communication</span><h2>Material event and notice center</h2><p>Notices require explicit review and publication. Email is never sent automatically.</p></div><?php if($canManage): ?><button class="mg-btn mg-btn-primary" type="button" data-create-notice>Create notice</button><?php endif; ?></header><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Notice</th><th>Type</th><th>Audience</th><th>Status</th><th>Counsel</th><th>Recipients</th><th>Viewed</th><th>Acknowledged</th><th></th></tr></thead><tbody data-notice-list></tbody></table></div></section></section>
      <section data-governance-panel="assistant" hidden><div class="mg-governance-split"><section class="mg-investment-panel"><header><div><span>Draft-only support</span><h2>Claude Governance Assistant</h2><p>Creates internal drafts and cannot determine rights, appoint directors, cast votes, approve resolutions, sign documents, publish notices, prepare tax forms or modify the official stock ledger.</p></div></header><div data-governance-ai></div></section><section class="mg-investment-panel"><header><div><span>Operating boundary</span><h2>Required external authority</h2></div></header><div class="mg-governance-boundary"><p>Board appointments, legal rights, consent thresholds, signatures, tax documents and official ownership records must come from authorized counsel, accountants, external providers or the company’s official corporate records.</p></div></section></div></section>
    </section>
  </main>
</section>
<div class="mg-investment-drawer-layer" data-governance-drawer-layer hidden><button class="mg-investment-drawer-backdrop" type="button" data-governance-close aria-label="Close"></button><aside class="mg-investment-drawer mg-governance-drawer" role="dialog" aria-modal="true" tabindex="-1"><header><div><span>Investor governance</span><h2 data-governance-drawer-title>Editor</h2><p data-governance-drawer-subtitle></p></div><button type="button" data-governance-close aria-label="Close">×</button></header><div class="mg-investment-drawer-body" data-governance-drawer-body></div></aside></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
