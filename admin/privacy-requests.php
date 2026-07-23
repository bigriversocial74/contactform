<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user=mg_require_admin_page_permission('admin.privacy_requests.view');
$permissions=is_array($user['permissions']??null)?$user['permissions']:[];
$roles=is_array($user['roles']??null)?$user['roles']:[];
$canManage=in_array('super_admin',$roles,true)||in_array('admin.privacy_requests.manage',$permissions,true);
$page_title='Privacy Requests | Microgifter';
$page_section='account';
$header_mode='account';
$page_body_class='mg-admin-privacy-page';
$page_styles=['/assets/css/admin-shell.css','/assets/css/admin-privacy-requests-v1.css?v=1.0.0'];
$page_scripts=['/assets/js/admin-privacy-requests-v1.js?v=1.0.0'];
$adminActive='privacy-requests';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-privacy" data-admin-privacy data-can-manage="<?= $canManage?'true':'false' ?>">
      <header class="mg-admin-privacy-hero">
        <div><a href="/account-admin.php">← Admin dashboard</a><span>Privacy governance</span><h1>Privacy requests</h1><p>Review verified requests, jurisdiction deadlines, account restrictions, merchant-controller handoffs, legal holds, retention actions, and final erasure receipts.</p></div>
        <div class="mg-admin-privacy-summary" data-privacy-summary><strong>—</strong><span>requests loaded</span></div>
      </header>

      <form class="mg-admin-privacy-filters" data-privacy-filters role="search">
        <label class="is-wide">Search<input type="search" name="q" maxlength="160" autocomplete="off" placeholder="Request ID, email, or account name"></label>
        <label>Status<select name="status"><option value="">All statuses</option><option value="submitted">Submitted</option><option value="identity_verified">Identity verified</option><option value="acknowledged">Acknowledged</option><option value="under_review">Under review</option><option value="approved">Approved</option><option value="restricted">Restricted</option><option value="blocked_by_hold">Blocked by hold</option><option value="processing">Processing</option><option value="completed">Completed</option><option value="partially_completed">Partially completed</option><option value="denied">Denied</option><option value="cancelled">Cancelled</option></select></label>
        <label>Jurisdiction<select name="jurisdiction"><option value="">All jurisdictions</option><option value="eu_eea">EU / EEA</option><option value="uk">United Kingdom</option><option value="california">California</option><option value="other_us">Other US</option><option value="other">Other</option></select></label>
        <div><button class="mg-btn mg-btn-primary" type="submit">Apply</button><button class="mg-btn mg-btn-ghost" type="reset">Reset</button><button class="mg-btn mg-btn-soft" type="button" data-privacy-refresh>Refresh</button></div>
      </form>

      <section class="mg-admin-privacy-panel">
        <header><div><h2>Request queue</h2><p data-privacy-status>Loading privacy requests…</p></div><span>Deadlines shown in UTC</span></header>
        <div class="mg-admin-privacy-state" data-privacy-loading>Loading protected privacy workflow data…</div>
        <div class="mg-admin-privacy-state is-error" data-privacy-error hidden></div>
        <div class="mg-admin-privacy-table-wrap" data-privacy-table-wrap hidden><table><thead><tr><th>Request</th><th>Account</th><th>Jurisdiction</th><th>Status</th><th>Due</th><th>Dependencies</th><th></th></tr></thead><tbody data-privacy-list></tbody></table></div>
        <div class="mg-admin-privacy-state" data-privacy-empty hidden>No privacy requests match these filters.</div>
      </section>
    </section>
  </main>
</section>

<div class="mg-admin-privacy-drawer-layer" data-privacy-drawer-layer hidden>
  <button class="mg-admin-privacy-backdrop" type="button" data-privacy-close aria-label="Close privacy request"></button>
  <aside class="mg-admin-privacy-drawer" role="dialog" aria-modal="true" aria-labelledby="privacy-request-title" tabindex="-1">
    <header><div><span>Privacy request</span><h2 id="privacy-request-title" data-privacy-drawer-title>Request detail</h2><p data-privacy-drawer-subtitle></p></div><button type="button" data-privacy-close aria-label="Close">×</button></header>
    <div class="mg-admin-privacy-drawer-body"><div class="mg-admin-privacy-state" data-privacy-detail-loading>Loading request detail…</div><div data-privacy-detail></div></div>
  </aside>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
