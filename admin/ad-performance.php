<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/api/ads/_ads.php';

$user = mg_require_auth('/signin.php', '/admin/ad-performance.php');
$pdo = mg_db();
$page_title = 'Ad Performance | Microgifter Admin';
$page_section = 'account';
$header_mode = 'account';
$adminActive = 'ad-performance';
$page_body_class = 'mg-admin-ad-review-page mg-admin-ad-performance-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/merchant-ad-manager.css','/assets/css/sponsored-campaign-card.css','/assets/css/ad-performance-dashboard.css'];
$page_scripts = ['/assets/js/ad-performance-dashboard.js'];
$page_manifest = [
    'id' => 'admin-ad-performance',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => $page_body_class,
    'onboarding' => ['enabled' => false, 'page' => 'admin-ad-performance', 'sections' => []],
];
$schema = mg_ads_schema_status($pdo);
$canAdminAds = mg_ads_user_can_admin($user);

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <main class="mg-ads-shell" data-ad-performance-dashboard data-performance-scope="admin">
      <section class="mg-ads-hero">
        <article class="mg-ads-hero-card">
          <a class="mg-system-health-back" href="/admin/ad-review.php">Back to Campaign Ads review</a>
          <br><br><span class="mg-ads-eyebrow">Platform Ad Performance</span>
          <h1>Measure sponsored campaign performance across Microgifter.</h1>
          <p>Track ad funnel movement, placement performance, campaign-level conversion, CRM creation, and future Pre Sale Revenue attribution across all merchants.</p>
          <div class="mg-ads-actions">
            <a class="mg-btn mg-btn-soft" href="/admin/ad-placements.php">Placement Controls</a>
            <button class="mg-btn mg-btn-primary" type="button" data-performance-refresh>Refresh performance</button>
          </div>
        </article>
        <aside class="mg-ads-hero-card">
          <span class="mg-ads-eyebrow">Admin scope</span>
          <h2>All merchants</h2>
          <p class="mg-ads-muted">This dashboard is read-only and uses the Campaign Ads Manager event table. Billing and auction reporting remain out of scope.</p>
          <p class="mg-ads-status" data-performance-status role="status"></p>
        </aside>
      </section>

      <?php if (!$canAdminAds): ?>
        <section class="mg-ads-panel"><div class="mg-ads-alert">Admin ad performance permission is required.</div></section>
      <?php else: ?>
        <?php if (!$schema['ready']): ?>
          <section class="mg-ads-panel"><div class="mg-ads-alert">SQL migration required: run <strong>database/microgifter_ads_manager_phase1.sql</strong> before viewing performance.</div></section>
        <?php endif; ?>
        <section class="mg-ads-kpi-grid mg-ads-performance-kpis" data-performance-kpis></section>
        <section class="mg-ads-performance-grid">
          <article class="mg-ads-panel">
            <h2>Platform ad funnel</h2>
            <div class="mg-ads-funnel" data-performance-funnel></div>
          </article>
          <article class="mg-ads-panel">
            <h2>Pre Sale Revenue attribution</h2>
            <div data-performance-value></div>
          </article>
        </section>
        <section class="mg-ads-panel">
          <h2>Placement performance by surface</h2>
          <div data-performance-placements></div>
        </section>
        <section class="mg-ads-panel">
          <h2>Campaign leaderboard</h2>
          <div class="mg-ads-list" data-performance-campaigns></div>
        </section>
      <?php endif; ?>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
