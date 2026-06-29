<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
$user = mg_require_admin_page_any(['admin.merchants.view','admin.catalog.view','admin.settings.manage']);
$page_title = 'Merchant PWA Apps | Microgifter Admin';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-merchant-pwa-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-merchant-pwa.css'];
$page_scripts = ['/assets/js/admin-merchant-pwa.js'];
$adminActive = 'merchant-pwa';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-merchant-pwa" data-admin-merchant-pwa>
      <header class="mg-admin-merchant-pwa-hero">
        <div>
          <span class="mg-eyebrow">Global oversight</span>
          <h1>Merchant-branded PWA apps</h1>
          <p>Review every merchant install screen, app manifest, branded start URL, asset readiness, and launch status from one admin control center.</p>
        </div>
        <div class="mg-admin-merchant-pwa-actions">
          <a class="mg-btn mg-btn-soft" href="/admin/pwa-branding.php">Platform PWA</a>
          <a class="mg-btn mg-btn-ghost" href="/merchant-pwa.php">Merchant setup</a>
        </div>
      </header>
      <section class="mg-admin-merchant-pwa-status" data-admin-merchant-pwa-status>Loading merchant app profiles…</section>
      <section class="mg-admin-merchant-pwa-summary" data-admin-merchant-pwa-summary>
        <article><span>Total</span><strong data-pwa-total>0</strong></article>
        <article><span>Active</span><strong data-pwa-active>0</strong></article>
        <article><span>Draft</span><strong data-pwa-draft>0</strong></article>
        <article><span>Paused</span><strong data-pwa-paused>0</strong></article>
        <article><span>Missing assets</span><strong data-pwa-missing>0</strong></article>
      </section>
      <section class="mg-admin-merchant-pwa-panel">
        <header>
          <div><h2>Review queue</h2><p>Filter, inspect, preview, activate, pause, or archive merchant-branded app profiles.</p></div>
          <div class="mg-admin-merchant-pwa-filters">
            <input type="search" placeholder="Search merchant or slug" data-pwa-search>
            <select data-pwa-status-filter><option value="">All statuses</option><option value="active">Active</option><option value="draft">Draft</option><option value="paused">Paused</option><option value="archived">Archived</option></select>
            <button class="mg-btn mg-btn-soft" type="button" data-pwa-refresh>Refresh</button>
          </div>
        </header>
        <div class="mg-admin-merchant-pwa-list" data-admin-merchant-pwa-list><p class="mg-muted">Loading profiles…</p></div>
      </section>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
