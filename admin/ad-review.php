<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/api/ads/_ads.php';

$user = mg_require_auth('/signin.php', '/admin/ad-review.php');
$pdo = mg_db();
$page_title = 'Campaign Ads Review | Microgifter Admin';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-ad-review-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/merchant-ad-manager.css','/assets/css/sponsored-campaign-card.css','/assets/css/ad-health-alerts.css'];
$page_scripts = ['/assets/js/sponsored-campaign-card.js','/assets/js/ad-health-alerts.js','/assets/js/admin-ad-review.js'];
$page_manifest = [
    'id' => 'admin-ad-review',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => $page_body_class,
    'onboarding' => ['enabled' => false, 'page' => 'admin-ad-review', 'sections' => []],
];
$csrfToken = mg_csrf_token();
$schema = mg_ads_schema_status($pdo);
$canAdminAds = mg_ads_user_can_admin($user);

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <main class="mg-ads-shell" data-admin-ad-review data-csrf-token="<?php echo mg_e($csrfToken); ?>">
      <section class="mg-ads-hero">
        <article class="mg-ads-hero-card">
          <a class="mg-system-health-back" href="/account-admin.php">Back to Admin dashboard</a>
          <br><br><span class="mg-ads-eyebrow">Ad Operations - Phase 1</span>
          <h1>Review sponsored campaigns before they go live.</h1>
          <p>Approve, reject, pause, or reactivate controlled Campaign Ads Manager placements across the Feed, Sidebar, World Canvas, and Target Zones.</p>
        </article>
        <aside class="mg-ads-kpi-grid" aria-label="Advertising performance summary">
          <div class="mg-ads-kpi"><span>Impressions</span><strong data-kpi="impressions">0</strong></div>
          <div class="mg-ads-kpi"><span>Clicks</span><strong data-kpi="clicks">0</strong></div>
          <div class="mg-ads-kpi"><span>Claims</span><strong data-kpi="claims">0</strong></div>
          <div class="mg-ads-kpi"><span>Redemptions</span><strong data-kpi="redemptions">0</strong></div>
        </aside>
      </section>

      <?php if (!$canAdminAds): ?>
        <section class="mg-ads-panel"><div class="mg-ads-alert">Admin ad review permission is required.</div></section>
      <?php else: ?>
        <?php if (!$schema['ready']): ?>
          <section class="mg-ads-panel"><div class="mg-ads-alert">SQL migration required: run <strong>database/microgifter_ads_manager_phase1.sql</strong> before reviewing ads.</div></section>
        <?php endif; ?>
        <section class="mg-ad-health-alerts" data-ad-health-alerts data-health-scope="admin" aria-live="polite"></section>
        <section class="mg-ads-panel" style="margin-bottom:18px">
          <span class="mg-ads-eyebrow">Demo ads</span>
          <h2>Create demo ads about advertising on Microgifter</h2>
          <p class="mg-ads-muted">Create approved sample ads for Feed, Sidebar, World Canvas, and Target Zone placements. The content promotes Campaign Ads Manager so you can test how Microgifter advertising looks and tracks.</p>
          <div class="mg-ads-actions">
            <button class="mg-btn mg-btn-primary" type="button" data-create-demo-ads>Create Demo Ads</button>
            <button class="mg-btn mg-btn-soft" type="button" data-refresh>Refresh</button>
          </div>
        </section>
        <section class="mg-ads-panel" style="margin-bottom:18px">
          <div class="mg-ads-actions">
            <label class="mg-ads-field" style="max-width:260px"><span>Status filter</span><select data-status-filter><option value="pending_review">Pending Review</option><option value="approved">Approved</option><option value="active">Active</option><option value="paused">Paused</option><option value="rejected">Rejected</option><option value="">All statuses</option></select></label>
          </div>
          <p class="mg-ads-status" data-admin-ad-status role="status"></p>
        </section>
        <section class="mg-ads-admin-layout">
          <article class="mg-ads-panel">
            <h2>Review queue</h2>
            <div class="mg-ads-list" data-admin-ad-list><div class="mg-ads-empty">Loading campaigns...</div></div>
          </article>
          <aside data-admin-ad-detail><div class="mg-ads-empty">Select an ad campaign to review.</div></aside>
        </section>
      <?php endif; ?>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
