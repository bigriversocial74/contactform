<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin-auth.php';
require_once __DIR__ . '/includes/share-market/admin-actions.php';

$user = mg_require_admin_page_any(['share_market.admin']);
$page_title = 'Share Market Candidate Packet | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_styles = ['/assets/css/admin-dashboard.css', '/assets/css/share-market-candidate-packet.css'];
$page_scripts = ['/assets/js/share-market-candidate-packet.js'];

require __DIR__ . '/includes/header.php';
?>
<main class="sm-packet" data-share-packet-root>
  <header class="sm-packet-head">
    <div>
      <h1>Candidate Review Packet</h1>
      <p>Read-only review page for a saved evidence candidate.</p>
    </div>
    <div class="sm-packet-actions">
      <a class="btn" href="/account-share-market-execution-audit.php">Back to audit console</a>
      <button class="primary" type="button" data-share-packet-print>Print packet</button>
      <button type="button" data-share-packet-download>Download JSON</button>
    </div>
  </header>
  <div class="sm-packet-status" data-share-packet-status>Loading candidate packet…</div>
  <section class="sm-packet-grid" data-share-packet-summary></section>
  <section class="sm-packet-section"><h2>Readiness snapshot</h2><div class="sm-packet-list" data-share-packet-readiness></div></section>
  <section class="sm-packet-section"><h2>Evidence snapshots</h2><div class="sm-packet-grid" data-share-packet-evidence></div></section>
  <section class="sm-packet-section"><h2>Packet JSON</h2><pre class="sm-packet-json" data-share-packet-json>{}</pre></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
