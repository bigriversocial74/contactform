<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$user=mg_require_auth();
$canViewMerchantCatalog=mg_has_permission('admin.merchants.view')||mg_has_permission('admin.catalog.view');
$page_title='Merchant & Catalog Operations | Microgifter';
$page_section='account';
$header_mode='account';
$page_body_class='mg-admin-merchant-catalog-page';
$page_styles=['/assets/css/admin-merchant-catalog.css'];
$page_scripts=$canViewMerchantCatalog?['/assets/js/admin-merchant-catalog.js']:[];

require __DIR__ . '/includes/header.php';
?>
<section class="mg-mc-shell" data-mc-root>
  <header class="mg-mc-hero">
    <div>
      <a class="mg-mc-back" href="/account-admin.php">← Admin dashboard</a>
      <span class="mg-eyebrow">Merchant, storefront, product, and media operations</span>
      <h1>Merchant &amp; catalog operations</h1>
      <p>Inspect merchant readiness, storefront publication, product versions, placements, and catalog assets from one protected workspace.</p>
    </div>
    <?php if($canViewMerchantCatalog): ?><div class="mg-mc-hero-actions"><span>Updated <strong data-mc-updated>—</strong></span><button class="mg-btn mg-btn-ghost" type="button" data-mc-refresh disabled>Refresh</button></div><?php endif; ?>
  </header>

  <?php if(!$canViewMerchantCatalog): ?>
    <section class="mg-app-panel mg-mc-access"><h2>Merchant catalog operations access is not active.</h2><p>This workspace requires the merchant or catalog administrative view permission.</p><a class="mg-btn mg-btn-soft" href="/account-admin.php">Back to admin</a></section>
  <?php else: ?>
    <section class="mg-mc-metrics" data-mc-metrics aria-label="Merchant catalog summary"></section>
    <form class="mg-mc-filters" data-mc-filters role="search">
      <label class="is-search">Search<input type="search" name="q" maxlength="160" autocomplete="off" placeholder="Merchant, store, product, asset, email, or reference"></label>
      <label>Domain<select name="domain"><option value="all">All records</option><option value="workspace">Merchant workspaces</option><option value="storefront">Storefronts</option><option value="product">Products</option><option value="asset">Assets</option></select></label>
      <label>Status<select name="status"><option value="">Any status</option><option value="attention">Needs attention</option><option value="active">Active</option><option value="pending_review">Pending review</option><option value="published">Published</option><option value="review">Review</option><option value="paused">Paused</option><option value="suspended">Suspended</option><option value="ready">Ready</option><option value="pending">Pending</option><option value="quarantined">Quarantined</option><option value="failed">Failed</option><option value="archived">Archived</option></select></label>
      <label>Merchant user ID<input type="number" name="merchant_user_id" min="1" step="1" inputmode="numeric"></label>
      <label>From<input type="date" name="date_from"></label><label>To<input type="date" name="date_to"></label>
      <div class="mg-mc-filter-actions"><button class="mg-btn mg-btn-primary" type="submit">Apply filters</button><button class="mg-btn mg-btn-ghost" type="reset">Reset</button></div>
    </form>

    <section class="mg-mc-panel">
      <header class="mg-mc-panel-head"><div><h2>Operational records</h2><p data-mc-summary>Loading merchant and catalog records…</p></div><span class="mg-mc-protected">Permission gated</span></header>
      <div class="mg-mc-live" data-mc-live role="status" aria-live="polite"></div>
      <div class="mg-mc-state" data-mc-loading><strong>Loading operations</strong><span>Preparing merchant, storefront, product, and asset context.</span></div>
      <div class="mg-mc-state mg-hidden" data-mc-error role="alert"><strong>Unable to load operations</strong><span data-mc-error-message>The workspace could not be loaded.</span><button class="mg-btn mg-btn-soft" type="button" data-mc-retry>Try again</button></div>
      <div class="mg-mc-state mg-hidden" data-mc-empty><strong>No matching records</strong><span>Try broader search, status, merchant, or date filters.</span></div>
      <div class="mg-mc-table-wrap mg-hidden" data-mc-content><table class="mg-mc-table"><thead><tr><th>Record</th><th>Status</th><th>Merchant</th><th>Health</th><th>Updated</th><th></th></tr></thead><tbody data-mc-list></tbody></table></div>
      <footer class="mg-mc-pagination mg-hidden" data-mc-pagination><span data-mc-page-label></span><div><button class="mg-btn mg-btn-ghost" type="button" data-mc-prev>Previous</button><button class="mg-btn mg-btn-soft" type="button" data-mc-next>Next</button></div></footer>
    </section>

    <div class="mg-mc-drawer-layer mg-hidden" data-mc-drawer-layer>
      <button class="mg-mc-drawer-backdrop" type="button" data-mc-close aria-label="Close detail"></button>
      <aside class="mg-mc-drawer" data-mc-drawer role="dialog" aria-modal="true" aria-labelledby="mg-mc-drawer-title" tabindex="-1">
        <header class="mg-mc-drawer-head"><div><span class="mg-eyebrow" data-mc-drawer-domain>Operations detail</span><h2 id="mg-mc-drawer-title" data-mc-drawer-title>Record detail</h2><p data-mc-drawer-subtitle>Protected merchant and catalog context.</p></div><button class="mg-mc-drawer-close" type="button" data-mc-close aria-label="Close detail">×</button></header>
        <div class="mg-mc-drawer-body">
          <div class="mg-mc-state" data-mc-detail-loading><strong>Loading detail</strong><span>Preparing readiness, lifecycle, ownership, and related records.</span></div>
          <div class="mg-mc-state mg-hidden" data-mc-detail-error role="alert"><strong>Unable to load detail</strong><span data-mc-detail-error-message>The detail request failed.</span><button class="mg-btn mg-btn-soft" type="button" data-mc-detail-retry>Try again</button></div>
          <div class="mg-mc-detail mg-hidden" data-mc-detail-content>
            <section class="mg-mc-detail-section"><header><div><h3>Overview</h3><p>Canonical identity, ownership, status, and timestamps.</p></div></header><div class="mg-mc-facts" data-mc-facts></div></section>
            <section class="mg-mc-detail-section"><header><div><h3>Readiness &amp; issues</h3><p>Publishing, ownership, onboarding, and asset warnings.</p></div></header><div data-mc-issues></div></section>
            <section class="mg-mc-detail-section"><header><div><h3>Related records</h3><p>Bounded workspace, storefront, version, placement, location, team, and media context.</p></div></header><div class="mg-mc-related" data-mc-related></div></section>
            <section class="mg-mc-detail-section"><header><div><h3>Operations timeline</h3><p>Administrative lifecycle changes and reasons.</p></div></header><div class="mg-mc-timeline" data-mc-events></div></section>
            <section class="mg-mc-detail-section mg-mc-actions"><header><div><h3>Protected actions</h3><p>Every transition requires a reason and confirmation.</p></div><span class="mg-mc-protected">Audited</span></header><div data-mc-actions></div></section>
          </div>
        </div>
      </aside>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
