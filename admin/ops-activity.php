<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.operations_command');
$page_title = 'Ops Activity Log | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-ops-activity-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-ops-activity.css'];
$page_scripts = ['/assets/js/admin-ops-activity.js'];
$adminActive = 'ops-activity';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-ops-activity-shell" data-ops-activity>
      <header class="mg-ops-activity-hero">
        <div>
          <a class="mg-ops-activity-back" href="/admin/operations-command.php">← Command center</a>
          <span class="mg-eyebrow">Admin operations</span>
          <h1>Ops activity log</h1>
          <p>Track readiness checks, generated SQL plans, incident mode activity, postmortems, analytics, risk forecasts, automation, notifications, and recovery-tool usage.</p>
        </div>
        <div class="mg-ops-activity-actions">
          <span>Activity score <strong>10/10</strong></span>
          <button class="mg-btn mg-btn-ghost" type="button" data-ops-activity-refresh disabled>Refresh</button>
        </div>
      </header>

      <form class="mg-ops-activity-filters" data-ops-activity-filters>
        <label>Search
          <input type="search" name="q" maxlength="160" placeholder="Action, metadata, actor, or entity">
        </label>
        <label>Category
          <select name="category">
            <option value="">All</option>
            <option value="readiness">Readiness</option>
            <option value="incident">Incident mode</option>
            <option value="postmortems">Postmortems</option>
            <option value="analytics">Analytics</option>
            <option value="forecast">Risk forecast</option>
            <option value="automation">Automation</option>
            <option value="notifications">Notifications</option>
          </select>
        </label>
        <label>Days
          <input type="number" name="days" min="1" max="365" value="30">
        </label>
        <label>Limit
          <input type="number" name="limit" min="25" max="200" value="100">
        </label>
        <div>
          <button class="mg-btn mg-btn-primary" type="submit">Apply</button>
          <button class="mg-btn mg-btn-ghost" type="reset" data-ops-activity-reset>Reset</button>
        </div>
      </form>

      <section class="mg-ops-activity-summary" data-ops-activity-summary></section>
      <div class="mg-ops-activity-status" data-ops-activity-status role="status" aria-live="polite">Loading ops activity…</div>
      <section class="mg-ops-activity-list" data-ops-activity-list></section>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
