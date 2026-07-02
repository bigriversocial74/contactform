<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';
require_once dirname(__DIR__) . '/includes/admin-screen-recordings.php';

$user = mg_require_admin_page_key('admin.screen_recordings');
$canManageRecordings = mg_screen_recordings_user_can_manage($user);
$schema = mg_screen_recordings_schema_ready(mg_db());
$csrfToken = mg_csrf_token();
$page_title = 'Screen Recordings | Microgifter Admin';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-screen-recordings-page';
$page_styles = ['/assets/css/admin-shell.css', '/assets/css/admin-screen-recordings.css'];
$page_scripts = ['/assets/js/admin-screen-recorder.js', '/assets/js/admin-screen-recordings.js'];
$adminActive = 'screen-recordings';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <main class="mg-screen-recordings-shell" data-screen-recordings data-csrf-token="<?= mg_e($csrfToken) ?>" data-can-manage="<?= $canManageRecordings ? '1' : '0' ?>">
      <header class="mg-screen-recordings-hero">
        <div>
          <a class="mg-screen-recordings-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Admin media ops</span>
          <h1>Screen recordings</h1>
          <p>Record product walkthroughs, QA notes, training clips, and admin demos. Save clips to the protected admin library, then open the full-page editor for timeline trimming and text overlays.</p>
        </div>
        <div class="mg-screen-recordings-actions">
          <span>Last updated <strong data-recordings-updated>—</strong></span>
          <?php if ($canManageRecordings): ?>
            <button class="mg-btn mg-btn-primary" type="button" data-recorder-open>Start new recording</button>
          <?php endif; ?>
          <button class="mg-btn mg-btn-ghost" type="button" data-recordings-refresh disabled>Refresh</button>
        </div>
      </header>

      <?php if (!$schema['ready']): ?>
        <section class="mg-screen-recordings-alert" role="alert">
          <strong>SQL migration required.</strong>
          <span>Run <code>database/admin_screen_recordings.sql</code> before saving recordings.</span>
        </section>
      <?php endif; ?>

      <section class="mg-recorder-panel" data-recorder-panel>
        <div>
          <span class="mg-eyebrow">Clean capture controller</span>
          <h2>Floating controller, not a recording badge</h2>
          <p>The recorder opens a compact controller window. For clean captures, choose a browser tab or app window that does not include the controller. Browser/OS privacy indicators may still appear outside Microgifter control.</p>
        </div>
        <div class="mg-recorder-options">
          <label><input type="checkbox" data-recorder-mic checked> Include microphone</label>
          <label><input type="checkbox" data-recorder-system-audio checked> Request system/tab audio</label>
          <label>Recording title <input type="text" data-recorder-title maxlength="180" placeholder="Admin walkthrough"></label>
        </div>
        <div class="mg-recorder-status" data-recorder-status role="status" aria-live="polite">Ready to record.</div>
      </section>

      <section class="mg-screen-recordings-filters">
        <label class="is-search">Search
          <input type="search" data-recordings-search maxlength="120" placeholder="Title, notes, or filename">
        </label>
        <label>Status
          <select data-recordings-status>
            <option value="">All statuses</option>
            <option value="saved">Saved</option>
            <option value="edited">Edited</option>
            <option value="export_pending">Export pending</option>
            <option value="exported">Exported</option>
            <option value="failed">Failed</option>
          </select>
        </label>
        <div class="mg-screen-recordings-filter-actions">
          <button class="mg-btn mg-btn-soft" type="button" data-recordings-apply>Apply</button>
          <button class="mg-btn mg-btn-ghost" type="button" data-recordings-reset>Reset</button>
        </div>
      </section>

      <section class="mg-screen-recordings-library">
        <header>
          <div>
            <h2>Saved recordings</h2>
            <p data-recordings-summary>Loading admin recordings…</p>
          </div>
        </header>
        <div class="mg-screen-recordings-state" data-recordings-loading>Loading recordings…</div>
        <div class="mg-screen-recordings-state is-error" data-recordings-error hidden></div>
        <div class="mg-screen-recordings-grid" data-recordings-grid hidden></div>
        <div class="mg-screen-recordings-empty" data-recordings-empty hidden>No recordings found.</div>
      </section>
    </main>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
