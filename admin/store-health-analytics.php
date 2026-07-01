<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_key('admin.system_health');
$page_title = 'Store Health Analytics | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-store-health-analytics-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-store-health-analytics.css'];
$page_scripts = ['/assets/js/admin-store-health-analytics.js'];
$adminActive = 'store-health-analytics';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-store-health-shell" data-admin-store-health-analytics>
      <header class="mg-admin-store-health-hero">
        <div>
          <a class="mg-admin-store-health-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Merchant action intelligence</span>
          <h1>Store Health Admin Analytics</h1>
          <p>Track recommendations, merchant follow-through, and the reward, claim, and redemption outcomes that happen after Store Health actions.</p>
        </div>
        <div class="mg-admin-store-health-actions">
          <span>Last updated <strong data-sha-updated>—</strong></span>
          <button class="mg-btn mg-btn-primary" type="button" data-sha-refresh>Refresh</button>
        </div>
      </header>

      <section class="mg-admin-store-health-banner is-loading" data-sha-banner>
        <span></span>
        <div><strong>Loading Store Health analytics</strong><p>Checking merchant action states and business impact attribution.</p></div>
      </section>

      <section class="mg-admin-store-health-metrics" data-sha-metrics>
        <?php foreach (['Recommended actions','Started','Completed','Snoozed','Dismissed','Active merchants','Completion rate','Dismiss rate'] as $label): ?>
          <article><span><?= mg_e($label) ?></span><strong>—</strong><small>Waiting for analytics</small></article>
        <?php endforeach; ?>
      </section>

      <section class="mg-admin-store-health-section">
        <header><div><h2>Business impact attribution</h2><p>Reward, claim, and redemption movement connected to Store Health action windows.</p></div></header>
        <div class="mg-admin-store-health-metrics mg-admin-store-health-impact-metrics" data-sha-impact-metrics>
          <?php foreach (['Rewards issued','Claims created','Redemptions','Claim rate','Redemption rate','Active invites','Expired invites','Impact status'] as $label): ?>
            <article><span><?= mg_e($label) ?></span><strong>—</strong><small>Waiting for impact data</small></article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="mg-admin-store-health-grid">
        <article class="mg-admin-store-health-card">
          <header><div><h2>Action type performance</h2><p>Which recommendations merchants engage with and complete.</p></div></header>
          <div class="mg-admin-store-health-table" data-sha-types></div>
        </article>

        <article class="mg-admin-store-health-card">
          <header><div><h2>Business impact by action</h2><p>Reward, claim, and redemption outcomes within 14 days of started/completed actions.</p></div></header>
          <div class="mg-admin-store-health-table" data-sha-impact-types></div>
        </article>
      </section>

      <section class="mg-admin-store-health-grid">
        <article class="mg-admin-store-health-card">
          <header><div><h2>Merchant completion leaderboard</h2><p>Stores acting on the highest number of Store Health recommendations.</p></div></header>
          <div class="mg-admin-store-health-table" data-sha-merchants></div>
        </article>

        <article class="mg-admin-store-health-card">
          <header><div><h2>Merchant impact leaderboard</h2><p>Stores converting Store Health action into reward, claim, and redemption movement.</p></div></header>
          <div class="mg-admin-store-health-table" data-sha-impact-merchants></div>
        </article>
      </section>

      <section class="mg-admin-store-health-grid">
        <article class="mg-admin-store-health-card">
          <header><div><h2>14-day action movement</h2><p>Daily started/completed/snoozed/dismissed action volume.</p></div></header>
          <div class="mg-admin-store-health-bars" data-sha-daily></div>
        </article>

        <article class="mg-admin-store-health-card">
          <header><div><h2>Action → outcome timeline</h2><p>Latest action windows with downstream reward, claim, and redemption counts.</p></div></header>
          <div class="mg-admin-store-health-feed" data-sha-impact-timeline></div>
        </article>
      </section>

      <section class="mg-admin-store-health-grid">
        <article class="mg-admin-store-health-card">
          <header><div><h2>Recent action history</h2><p>Latest merchant recommendation state changes.</p></div></header>
          <div class="mg-admin-store-health-feed" data-sha-recent></div>
        </article>
      </section>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
