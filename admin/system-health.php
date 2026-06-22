<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';

$user = mg_require_auth();
$canViewSystemHealth = mg_has_permission('admin.health.view');
$page_title = 'System Health | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-system-health-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-system-health.css'];
$page_scripts = $canViewSystemHealth ? ['/assets/js/admin-system-health.js'] : [];
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
          <p>Persistent media, notification delivery, database migrations, and recent operational warnings.</p>
        </div>
        <?php if ($canViewSystemHealth): ?>
          <div class="mg-system-health-hero-actions">
            <span class="mg-system-health-updated">Last checked <strong data-system-health-updated>—</strong></span>
            <button class="mg-btn mg-btn-ghost" type="button" data-system-health-refresh disabled>Refresh</button>
          </div>
        <?php endif; ?>
      </header>

      <?php if (!$canViewSystemHealth): ?>
        <section class="mg-system-health-access mg-app-panel">
          <h2>System health access is not active.</h2>
          <p>This page requires the <code>admin.health.view</code> permission or an administrator role.</p>
          <a class="mg-btn mg-btn-soft" href="/account-admin.php">Back to admin</a>
        </section>
      <?php else: ?>
        <section class="mg-system-health-banner is-loading" data-system-health-banner aria-live="polite">
          <span class="mg-system-health-indicator" aria-hidden="true"></span>
          <div><strong>Checking system health</strong><p>Preparing the current operational snapshot.</p></div>
        </section>

        <div class="mg-system-health-grid" data-system-health-services>
          <?php foreach ([
            ['storage','Persistent media','Storage location, write access, capacity, and file integrity.'],
            ['notifications','Notification delivery','Queued, delivered, failed, and retrying delivery jobs.'],
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

        <section class="mg-system-health-section">
          <header><div><h2>Operational totals</h2><p>Current storage and notification workload.</p></div></header>
          <div class="mg-system-health-metrics" data-system-health-metrics>
            <?php foreach (['Media files','Storage used','Unattached uploads','Missing files','Queued notifications','Failed notifications'] as $label): ?>
              <article><span><?= mg_e($label) ?></span><strong>—</strong><small>Waiting for health data</small></article>
            <?php endforeach; ?>
          </div>
        </section>

        <div class="mg-system-health-columns">
          <section class="mg-system-health-section">
            <header><div><h2>Recent warnings</h2><p>Operational and security events that may require attention.</p></div></header>
            <div class="mg-system-health-list" data-system-health-warnings><p class="mg-muted">Loading recent warnings…</p></div>
          </section>

          <section class="mg-system-health-section">
            <header><div><h2>Recovery tools</h2><p>Protected actions for safe operational recovery.</p></div></header>
            <div class="mg-system-health-actions" data-system-health-actions>
              <button class="mg-btn mg-btn-soft" type="button" data-health-action="verify_storage" disabled>Verify storage</button>
              <button class="mg-btn mg-btn-soft" type="button" data-health-action="retry_notifications" disabled>Retry failed notifications</button>
              <button class="mg-btn mg-btn-soft" type="button" data-health-action="clean_uploads" disabled>Clean abandoned uploads</button>
              <button class="mg-btn mg-btn-soft" type="button" data-health-action="migration_plan" disabled>Prepare migration recovery</button>
              <p>Actions remain disabled until the secure health API confirms access and readiness.</p>
            </div>
          </section>
        </div>
      <?php endif; ?>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
