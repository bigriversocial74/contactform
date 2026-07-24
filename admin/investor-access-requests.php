<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$user = mg_require_admin_page_permission('admin.investor_access.view');
$canManage = mg_admin_page_user_has_permission($user, 'admin.investor_access.manage') && in_array('super_admin', is_array($user['roles'] ?? null) ? $user['roles'] : [], true);
$page_title = 'Investor Access Requests | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-investment-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/investment-system-v1.css?v=1.0.0'];
$page_scripts = ['/assets/js/admin-investor-access-v1.js?v=1.0.0'];
$adminActive = 'investor-access';
$csrfToken = mg_csrf_token();
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace mg-admin-workspace">
    <section class="mg-investment-admin" data-admin-investor-access data-csrf-token="<?= mg_e($csrfToken) ?>" data-can-manage="<?= $canManage ? '1' : '0' ?>">
      <header class="mg-investment-hero is-admin"><div><a href="/account-admin.php">← Admin dashboard</a><span class="mg-eyebrow">Investor identity operations</span><h1>Investor Access Requests</h1><p>Review professional identity, firm information, expected range, request history, and Investor role access.</p></div><div class="mg-investment-hero-actions"><a class="mg-btn mg-btn-primary" href="/admin/investment-wizard.php">Investment Wizard</a><a class="mg-btn mg-btn-soft" href="/admin/investor-pipeline.php">Investor Pipeline</a><button class="mg-btn mg-btn-ghost" type="button" data-access-refresh>Refresh</button></div></header>
      <form class="mg-investment-filter" data-access-filter><label>Status<select name="status"><option value="">All statuses</option><option value="pending">Pending</option><option value="more_information_requested">More information requested</option><option value="approved">Approved</option><option value="denied">Denied</option><option value="revoked">Revoked</option><option value="withdrawn">Withdrawn</option></select></label><button class="mg-btn mg-btn-soft" type="submit">Apply</button></form>
      <section class="mg-investment-panel"><header><div><span>Review queue</span><h2>Investor applicants</h2><p data-access-summary>Loading requests…</p></div></header><div class="mg-investment-notice" data-access-list-notice></div><div class="mg-investment-table-wrap"><table class="mg-investment-table"><thead><tr><th>Applicant</th><th>Firm</th><th>Type / range</th><th>Status</th><th>Requested</th><th></th></tr></thead><tbody data-access-list></tbody></table></div></section>
    </section>
  </main>
</section>
<div class="mg-investment-drawer-layer" data-access-drawer-layer hidden><button class="mg-investment-drawer-backdrop" type="button" data-access-close aria-label="Close"></button><aside class="mg-investment-drawer" role="dialog" aria-modal="true" tabindex="-1"><header><div><span>Investor applicant</span><h2 data-access-drawer-title>Request detail</h2><p data-access-drawer-subtitle></p></div><button type="button" data-access-close aria-label="Close">×</button></header><div class="mg-investment-drawer-body" data-access-detail></div></aside></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
