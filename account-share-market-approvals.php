<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/share-market/admin-actions.php';

$user = mg_require_admin_page_any(['share_market.admin']);
$page_title = 'DAVE Share Market Review Console | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_styles = ['/assets/css/admin-dashboard.css'];
$page_scripts = ['/assets/js/account.js', '/assets/js/share-market-program-review-console.js', '/assets/js/share-market-credit-reserve-admin.js', '/assets/js/share-market-approval-queue.js', '/assets/js/share-market-execution-prep.js'];

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
      <a class="is-active" href="/account-share-market-approvals.php"><strong>DAVE Review Console</strong><span>Merchant, credit, and series reviews</span></a>
      <a href="/account-share-market-execution-audit.php"><strong>Audit Review</strong><span>Evidence and preflight records</span></a>
      <a href="/account-marketplace.php"><strong>Marketplace Index</strong><span>Aggregate value and movement</span></a>
    </nav>
  </aside>
  <main class="mg-app-workspace mg-account-main">
    <?php require __DIR__ . '/includes/account/share-market-program-review-console.php'; ?>
    <?php require __DIR__ . '/includes/account/share-market-approval-queue.php'; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
