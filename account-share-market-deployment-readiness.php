<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/share-market/admin-actions.php';

$user = mg_require_admin_page_any(['share_market.admin']);
$page_title = 'Share Market Deployment Readiness | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_styles = ['/assets/css/admin-dashboard.css', '/assets/css/share-market-candidate-packet.css'];
$page_scripts = ['/assets/js/account.js', '/assets/js/share-market-deployment-readiness.js'];

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app">
  <aside class="mg-app-sidebar mg-account-left">
    <div class="mg-app-sidebar-brand">
      <a class="mg-brand mg-sidebar-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a>
    </div>
    <nav class="mg-app-side-nav mg-account-nav" aria-label="Share Market administration">
      <a href="/account-admin.php"><strong>Admin dashboard</strong><span>Platform overview</span></a>
      <a href="/account-share-market-admin.php"><strong>Share Market Admin</strong><span>Pool and action controls</span></a>
      <a href="/account-share-market-approvals.php"><strong>Approval Queue</strong><span>Maker-checker review</span></a>
      <a href="/account-share-market-execution-audit.php"><strong>Audit Review</strong><span>Evidence and preflight records</span></a>
      <a class="is-active" href="/account-share-market-deployment-readiness.php"><strong>Deployment Readiness</strong><span>Install and route checks</span></a>
      <a href="/account-marketplace.php"><strong>Marketplace Index</strong><span>Aggregate value and movement</span></a>
    </nav>
  </aside>
  <main class="mg-app-workspace mg-account-main">
    <section class="sm-packet" data-share-deploy-root>
      <header class="sm-packet-head">
        <div>
          <h1>Deployment Readiness Checklist</h1>
          <p>Read-only install verification for Buy-In admin tables, permissions, routes, pages, and assets.</p>
        </div>
        <div class="sm-packet-actions">
          <a class="btn" href="/account-share-market-execution-audit.php">Back to audit console</a>
          <button class="primary" type="button" data-share-deploy-refresh>Refresh</button>
          <button type="button" data-share-deploy-download>Download JSON</button>
        </div>
      </header>
      <div class="sm-packet-status" data-share-deploy-status>Loading deployment readiness…</div>
      <section class="sm-packet-grid" data-share-deploy-summary></section>
      <section class="sm-packet-section"><h2>Missing install items</h2><div class="sm-packet-list" data-share-deploy-missing></div></section>
      <section class="sm-packet-section"><h2>All checks</h2><div class="sm-packet-list" data-share-deploy-checks></div></section>
      <section class="sm-packet-section"><h2>Readiness JSON</h2><pre class="sm-packet-json" data-share-deploy-json>{}</pre></section>
    </section>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
