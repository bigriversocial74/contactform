<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/api/ads/_ads.php';

$user = mg_require_auth('/signin.php', '/admin/ad-placements.php');
$pdo = mg_db();
$page_title = 'Ad Placement Controls | Microgifter Admin';
$page_section = 'account';
$header_mode = 'account';
$adminActive = 'ad-placements';
$page_body_class = 'mg-admin-ad-review-page mg-admin-ad-placements-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/merchant-ad-manager.css','/assets/css/sponsored-campaign-card.css','/assets/css/ad-health-alerts.css'];
$page_scripts = ['/assets/js/ad-health-alerts.js','/assets/js/admin-ad-placements.js'];
$page_manifest = [
    'id' => 'admin-ad-placements',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => $page_body_class,
    'onboarding' => ['enabled' => false, 'page' => 'admin-ad-placements', 'sections' => []],
];
$csrfToken = mg_csrf_token();
$schema = mg_ads_schema_status($pdo);
$canAdminAds = mg_ads_user_can_admin($user);

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <main class="mg-ads-shell" data-admin-ad-placements data-csrf-token="<?php echo mg_e($csrfToken); ?>">
      <section class="mg-ads-hero">
        <article class="mg-ads-hero-card">
          <a class="mg-system-health-back" href="/admin/ad-review.php">Back to Campaign Ads review</a>
          <br><br><span class="mg-ads-eyebrow">Placement Controls</span>
          <h1>Choose where approved ads appear.</h1>
          <p>Manage the active ad surfaces, max visible ad inventory, campaign assignments, placement priority, and pause/remove behavior across Feed, Sidebar, Agent Chat, World Canvas, and Target Zones.</p>
        </article>
        <aside class="mg-ads-hero-card">
          <span class="mg-ads-eyebrow">Phase 1 surfaces</span>
          <h2>Controlled ad inventory</h2>
          <p class="mg-ads-muted">Use this page to control which approved Campaign Ads Manager ads appear in the available placement containers. No SQL or billing changes are required.</p>
          <div class="mg-ads-actions"><button class="mg-btn mg-btn-primary" type="button" data-placement-refresh>Refresh placements</button></div>
        </aside>
      </section>

      <?php if (!$canAdminAds): ?>
        <section class="mg-ads-panel"><div class="mg-ads-alert">Admin ad placement permission is required.</div></section>
      <?php else: ?>
        <?php if (!$schema['ready']): ?>
          <section class="mg-ads-panel"><div class="mg-ads-alert">SQL migration required: run <strong>database/microgifter_ads_manager_phase1.sql</strong> before managing placements.</div></section>
        <?php endif; ?>
        <section class="mg-ad-health-alerts" data-ad-health-alerts data-health-scope="admin" aria-live="polite"></section>
        <section class="mg-ads-panel" style="margin-bottom:18px">
          <div class="mg-ads-row-head">
            <div>
              <h2>Placement assignment board</h2>
              <p class="mg-ads-muted" data-placement-summary>Loading placement assignments...</p>
            </div>
            <p class="mg-ads-status" data-placement-status role="status"></p>
          </div>
        </section>
        <section class="mg-ads-placement-board" data-placement-list>
          <div class="mg-ads-empty">Loading placement controls...</div>
        </section>
      <?php endif; ?>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
