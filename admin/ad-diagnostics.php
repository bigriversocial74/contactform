<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/api/ads/_ads.php';

$user = mg_require_auth('/signin.php', '/admin/ad-diagnostics.php');
$pdo = mg_db();
$page_title = 'Ad Diagnostics | Microgifter Admin';
$page_section = 'account';
$header_mode = 'account';
$adminActive = 'ad-diagnostics';
$page_body_class = 'mg-admin-ad-review-page mg-admin-ad-diagnostics-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/merchant-ad-manager.css','/assets/css/sponsored-campaign-card.css','/assets/css/ad-diagnostics.css','/assets/css/ad-admin-qa.css','/assets/css/ad-health-alerts.css'];
$page_scripts = ['/assets/js/ad-health-alerts.js','/assets/js/admin-ad-diagnostics.js'];
$page_manifest = [
    'id' => 'admin-ad-diagnostics',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => $page_body_class,
    'onboarding' => ['enabled' => false, 'page' => 'admin-ad-diagnostics', 'sections' => []],
];
$schema = mg_ads_schema_status($pdo);
$canAdminAds = mg_ads_user_can_admin($user);

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <main class="mg-ads-shell mg-ad-diagnostics" data-admin-ad-diagnostics>
      <section class="mg-ads-hero">
        <article class="mg-ads-hero-card">
          <a class="mg-system-health-back" href="/admin/ad-placements.php">Back to Ad placements</a>
          <br><br><span class="mg-ads-eyebrow">Campaign Ads QA</span>
          <h1>Campaign Ads diagnostics.</h1>
          <p>Review schema readiness, placement setup, assignments, render output, tracking events, creative readiness, and attribution health.</p>
        </article>
        <aside class="mg-ads-hero-card">
          <span class="mg-ads-eyebrow">Read-only</span>
          <h2>Placement health checks</h2>
          <p class="mg-ads-muted">Use this panel to confirm which ad surfaces are ready and which campaigns need creative, image, destination, or placement attention.</p>
          <div class="mg-ads-actions"><button class="mg-btn mg-btn-primary" type="button" data-diagnostics-refresh>Refresh diagnostics</button></div>
        </aside>
      </section>

      <?php if (!$canAdminAds): ?>
        <section class="mg-ads-panel"><div class="mg-ads-alert">Admin ad permission is required.</div></section>
      <?php else: ?>
        <?php if (!$schema['ready']): ?>
          <section class="mg-ads-panel"><div class="mg-ads-alert">SQL migration required: run <strong>database/microgifter_ads_manager_phase1.sql</strong> before Campaign Ads diagnostics can fully load.</div></section>
        <?php endif; ?>
        <section class="mg-ad-health-alerts" data-ad-health-alerts data-health-scope="admin" aria-live="polite"></section>
        <section class="mg-ads-panel">
          <div class="mg-ads-row-head">
            <div>
              <h2>Diagnostics summary</h2>
              <p class="mg-ads-muted" data-diagnostics-summary>Loading Campaign Ads diagnostics...</p>
            </div>
            <p class="mg-ads-status" data-diagnostics-status role="status"></p>
          </div>
          <div class="mg-ad-diagnostics-kpis" data-diagnostics-kpis></div>
        </section>

        <section class="mg-ad-diagnostics-grid">
          <article class="mg-ads-panel">
            <div class="mg-ads-row-head"><div><h2>Schema and attribution readiness</h2><p class="mg-ads-muted">Required tables, optional wallet tables, and direct-attribution columns.</p></div></div>
            <div class="mg-ad-diagnostics-checks" data-diagnostics-schema></div>
          </article>
          <article class="mg-ads-panel">
            <div class="mg-ads-row-head"><div><h2>Notes</h2><p class="mg-ads-muted">Operational notes for the current deploy.</p></div></div>
            <div class="mg-ad-diagnostics-notes" data-diagnostics-notes></div>
          </article>
        </section>

        <section class="mg-ads-panel">
          <div class="mg-ads-row-head">
            <div><h2>Creative and placement gaps</h2><p class="mg-ads-muted">Approved or active campaigns that need a creative field, image URL, destination URL, CTA label, or active placement assignment.</p></div>
          </div>
          <div class="mg-ad-diagnostics-gaps" data-diagnostics-gaps><div class="mg-ads-empty">Loading campaign gaps...</div></div>
        </section>

        <section class="mg-ads-panel">
          <div class="mg-ads-row-head">
            <div><h2>Placement diagnostics</h2><p class="mg-ads-muted">Each card shows configuration, assignment, render, creative, and tracking health.</p></div>
          </div>
        </section>
        <section class="mg-ad-diagnostics-list" data-diagnostics-placements>
          <div class="mg-ads-empty">Loading placement diagnostics...</div>
        </section>
      <?php endif; ?>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>