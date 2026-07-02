<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_key('admin.system_health');
$canViewSystemHealth = true;
$page_title = 'System Health | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-system-health-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-system-health.css','/assets/css/admin-pwa-health.css'];
$page_scripts = ['/assets/js/admin-system-health.js','/assets/js/admin-pwa-health.js','/assets/js/admin-health-warning-filter.js'];
$adminActive = 'system-health';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-system-health-shell" data-admin-system-health>
      <header class="mg-system-health-hero">
        <div>
          <a class="mg-system-health-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Platform operations</span>
          <h1>System health</h1>
          <p>Persistent media, notification delivery, PWA browser push, database migrations, admin ops deployment readiness, SQL diagnostics, and recent operational warnings.</p>
        </div>
        <div class="mg-system-health-hero-actions">
          <span class="mg-system-health-updated">Last checked <strong data-system-health-updated>—</strong></span>
          <a class="mg-btn mg-btn-soft" href="/admin/pwa-branding.php">PWA branding</a>
          <button class="mg-btn mg-btn-ghost" type="button" data-system-health-refresh disabled>Refresh</button>
        </div>
      </header>

      <section class="mg-system-health-banner is-loading" data-system-health-banner aria-live="polite">
        <span class="mg-system-health-indicator" aria-hidden="true"></span>
        <div><strong>Checking system health</strong><p>Preparing the current operational snapshot.</p></div>
      </section>

      <div class="mg-system-health-grid" data-system-health-services>
        <?php foreach ([
          ['storage','Persistent media','Storage location, write access, capacity, and file integrity.'],
          ['notifications','Notification delivery','Queued, delivered, failed, and retrying delivery jobs.'],
          ['pwa_notifications','PWA notifications','Browser service worker, permission support, subscriptions, provider, and test delivery status.'],
          ['migrations','Database migrations','Canonical migration status and the latest applied schema change.'],
          ['runtime','Application runtime','Database connectivity, environment, and release readiness.'],
        ] as [$key,$label,$description]): ?>
          <article class="mg-system-health-card is-loading" data-health-service="<?= mg_e($key) ?>">
            <header><span class="mg-system-health-dot" aria-hidden="true"></span><strong><?= mg_e($label) ?></strong><span data-health-status>Checking</span></header>
            <p><?= mg_e($description) ?></p>
            <dl data-health-details><div><dt>Status</dt><dd>Loading…</dd></div></dl>
          </article>
        <?php endforeach; ?>
      </div>

      <section class="mg-system-health-section mg-pwa-health-section" data-pwa-notification-health>
        <header>
          <div><h2>PWA notification health</h2><p>Browser push is a permission-gated delivery channel for existing Microgifter notification events.</p></div>
          <button class="mg-btn mg-btn-soft" type="button" data-health-action="test_pwa_notification" data-health-action-enabled="false" disabled>Send test notification to myself</button>
        </header>
        <div class="mg-system-health-pwa-grid" data-pwa-health-grid>
          <?php foreach (['Service worker','Permission support','Push endpoint','Active subscriptions','Failed delivery','Last test'] as $label): ?>
            <article><span><?= mg_e($label) ?></span><strong>—</strong><small>Waiting for health data</small></article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="mg-system-health-section">
        <header><div><h2>Operational totals</h2><p>Current storage and notification workload.</p></div></header>
        <div class="mg-system-health-metrics" data-system-health-metrics>
          <?php foreach (['Media files','Storage used','Unattached uploads','Missing files','Queued notifications','Failed notifications'] as $label): ?>
            <article><span><?= mg_e($label) ?></span><strong>—</strong><small>Waiting for health data</small></article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="mg-system-health-section mg-system-health-readiness" data-system-health-readiness>
        <header><div><h2>Admin ops deployment readiness</h2><p>Validates required tables, columns, enum values, permissions, APIs, and command center assets.</p></div><span data-readiness-status>Checking</span></header>
        <div class="mg-system-health-readiness-summary" data-readiness-summary>Loading admin ops readiness…</div>
        <div class="mg-system-health-readiness-grid" data-readiness-grid></div>
      </section>

      <section class="mg-system-health-section mg-system-sql-diagnostics" data-system-sql-diagnostics>
        <header>
          <div>
            <h2>System SQL diagnostics</h2>
            <p>Scans core modules for missing tables, columns, enum drift, recent SQL failures, and safe endpoint schema readiness.</p>
          </div>
          <div class="mg-system-sql-actions">
            <button class="mg-btn mg-btn-ghost" type="button" data-sql-diagnostics-refresh disabled>Run diagnostics</button>
            <button class="mg-btn mg-btn-soft" type="button" data-sql-diagnostics-download disabled>Download repair SQL</button>
          </div>
        </header>
        <div class="mg-system-sql-summary is-loading" data-sql-diagnostics-summary>Loading system SQL diagnostics…</div>
        <div class="mg-system-sql-metrics" data-sql-diagnostics-metrics>
          <?php foreach (['Modules','Critical modules','Findings','Recent SQL errors','Repairable'] as $label): ?>
            <article><span><?= mg_e($label) ?></span><strong>—</strong><small>Waiting for diagnostics</small></article>
          <?php endforeach; ?>
        </div>
        <div class="mg-system-sql-panels">
          <div>
            <h3>Module readiness</h3>
            <div class="mg-system-sql-list" data-sql-diagnostics-modules><p class="mg-muted">Loading module checks…</p></div>
          </div>
          <div>
            <h3>Top findings</h3>
            <div class="mg-system-sql-list" data-sql-diagnostics-findings><p class="mg-muted">Loading findings…</p></div>
          </div>
        </div>
      </section>

      <div class="mg-system-health-columns">
        <section class="mg-system-health-section"><header><div><h2>Recent warnings</h2><p>Operational and security events that may require attention.</p></div></header><div class="mg-system-health-list" data-system-health-warnings><p class="mg-muted">Loading recent warnings…</p></div></section>
        <section class="mg-system-health-section"><header><div><h2>Recovery tools</h2><p>Protected actions for safe operational recovery.</p></div></header><div class="mg-system-health-list" data-system-health-recovery><p class="mg-muted">Loading recovery tools…</p></div></section>
      </div>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>