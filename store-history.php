<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$user = mg_require_auth('/signin.php', '/store-history.php');
$page_title = 'Store History | Microgifter';
$page_section = 'account-commerce';
$header_mode = 'account';
$accountView = 'store-history';
$page_styles = ['/assets/css/account-commerce.css','/assets/css/store-history.css'];
$page_scripts = ['/assets/js/account-sidebar.js','/assets/js/store-history.js'];
$page_manifest = [
    'id' => 'store-history',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => 'mg-store-history-body',
    'onboarding' => ['enabled' => false, 'page' => 'store-history', 'sections' => []],
];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-account-page mg-store-history-page" data-store-history>
  <div class="mg-account-layout">
    <?php require __DIR__ . '/includes/account-sidebar.php'; ?>
    <section class="mg-account-shell mg-store-history-shell">
      <header class="mg-store-history-hero">
        <div>
          <span class="mg-account-eyebrow">Customer Store History</span>
          <h1>Store History</h1>
          <p>Review each merchant store visit, the source post that brought you in, messages received, rewards sent, claims, and the Store Canvas timeline behind your IN/OUT Box activity.</p>
        </div>
        <a class="mg-btn mg-btn-primary" href="/feed.php">Enter stores from feed</a>
      </header>
      <section class="mg-store-history-grid" aria-label="Store history summary">
        <article class="mg-store-history-stat"><span>Store visits</span><strong data-store-stat="visits">0</strong></article>
        <article class="mg-store-history-stat"><span>Messages</span><strong data-store-stat="messages">0</strong></article>
        <article class="mg-store-history-stat"><span>Rewards</span><strong data-store-stat="rewards">0</strong></article>
        <article class="mg-store-history-stat"><span>Claims</span><strong data-store-stat="claims">0</strong></article>
      </section>
      <section class="mg-store-history-panel">
        <header class="mg-store-history-toolbar">
          <div>
            <span class="mg-account-eyebrow">Timeline</span>
            <h2>Merchant store sessions</h2>
          </div>
          <p class="mg-store-history-status" data-store-history-status>Loading…</p>
        </header>
        <div class="mg-store-history-list" data-store-history-list>
          <div class="mg-store-history-loading">Loading Store History…</div>
        </div>
      </section>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
