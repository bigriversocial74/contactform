<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/api/ads/_ads.php';

$user = mg_require_auth('/signin.php', '/merchant-ad-performance.php');
$pdo = mg_db();
$page_title = 'Campaign Ads Performance | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'ads-performance';
$page_body_class = 'mg-ad-manager-page mg-ad-performance-page';
$page_styles = ['/assets/css/merchant-ad-manager.css','/assets/css/sponsored-campaign-card.css','/assets/css/ad-performance-dashboard.css'];
$page_scripts = ['/assets/js/ad-performance-dashboard.js'];
$page_manifest = [
    'id' => 'merchant-ad-performance',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => $page_body_class,
    'onboarding' => ['enabled' => false, 'page' => 'merchant-ad-performance', 'sections' => []],
];
$schema = mg_ads_schema_status($pdo);

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-agent-app">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <div class="mg-app-workspace">
    <main class="mg-ads-shell" data-ad-performance-dashboard data-performance-scope="merchant">
      <section class="mg-ads-hero">
        <article class="mg-ads-hero-card">
          <span class="mg-ads-eyebrow">Campaign Ads Performance</span>
          <h1>See what your sponsored campaigns are doing.</h1>
          <p>Track impressions, clicks, wallet saves, claims, redemptions, CRM contacts, placement performance, and future Pre Sale Revenue attribution as the ad system matures.</p>
          <div class="mg-ads-actions">
            <a class="mg-btn mg-btn-soft" href="/merchant-ad-manager.php">Campaign Ads Manager</a>
            <button class="mg-btn mg-btn-primary" type="button" data-performance-refresh>Refresh performance</button>
          </div>
        </article>
        <aside class="mg-ads-hero-card">
          <span class="mg-ads-eyebrow">Read-only reporting</span>
          <h2>Merchant scope</h2>
          <p class="mg-ads-muted">This dashboard only reports on your merchant-owned campaigns and placement events. No billing or auction logic is enabled.</p>
          <p class="mg-ads-status" data-performance-status role="status"></p>
        </aside>
      </section>

      <?php if (!mg_ads_user_can_merchant($user, $pdo)): ?>
        <section class="mg-ads-panel"><div class="mg-ads-alert">Merchant access is required to view Campaign Ads performance.</div></section>
      <?php else: ?>
        <?php if (!$schema['ready']): ?>
          <section class="mg-ads-panel"><div class="mg-ads-alert">SQL migration required: run <strong>database/microgifter_ads_manager_phase1.sql</strong> before viewing performance.</div></section>
        <?php endif; ?>
        <section class="mg-ads-kpi-grid mg-ads-performance-kpis" data-performance-kpis></section>
        <section class="mg-ads-performance-grid">
          <article class="mg-ads-panel">
            <h2>Ad funnel</h2>
            <div class="mg-ads-funnel" data-performance-funnel></div>
          </article>
          <article class="mg-ads-panel">
            <h2>Pre Sale Revenue attribution</h2>
            <div data-performance-value></div>
          </article>
        </section>
        <section class="mg-ads-panel">
          <h2>Placement performance</h2>
          <div data-performance-placements></div>
        </section>
        <section class="mg-ads-panel">
          <h2>Campaign performance</h2>
          <div class="mg-ads-list" data-performance-campaigns></div>
        </section>
      <?php endif; ?>
    </main>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
