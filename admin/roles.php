<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.roles.manage');
$page_title = 'Roles & Permissions | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-roles-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-roles.css'];
$page_scripts = ['/assets/js/admin-roles.js'];
$adminActive = 'roles';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-roles-shell" data-admin-roles>
      <header class="mg-admin-roles-hero">
        <div>
          <a class="mg-admin-roles-back" href="/admin/users.php">← User center</a>
          <span class="mg-eyebrow">Access control</span>
          <h1>Roles & permissions</h1>
          <p>Review platform roles, group permissions by system area, and manage which permissions each role grants.</p>
        </div>
        <div class="mg-admin-roles-hero-actions">
          <span>Score target <strong>10/10</strong></span>
          <button class="mg-btn mg-btn-ghost" type="button" data-roles-refresh disabled>Refresh</button>
        </div>
      </header>

      <section class="mg-admin-roles-panel">
        <aside class="mg-admin-roles-list" aria-label="Roles">
          <header>
            <h2>Roles</h2>
            <p data-roles-summary>Loading role inventory…</p>
          </header>
          <div class="mg-admin-roles-items" data-roles-list></div>
        </aside>

        <section class="mg-admin-role-detail">
          <header>
            <div>
              <span class="mg-eyebrow">Role detail</span>
              <h2 data-role-title>Select a role</h2>
              <p data-role-description>Choose a role to inspect its assigned permissions.</p>
            </div>
            <span class="mg-admin-roles-score" data-role-score>Score —</span>
          </header>

          <form class="mg-admin-role-reason" data-role-reason-wrap>
            <label>Required reason for permission changes
              <textarea data-role-reason maxlength="240" rows="3" placeholder="Explain why this role permission change is required."></textarea>
            </label>
          </form>

          <div class="mg-admin-roles-status" data-roles-status role="status" aria-live="polite"></div>
          <div class="mg-admin-roles-state" data-roles-loading><strong>Loading roles</strong><span>Preparing role and permission matrix.</span></div>
          <div class="mg-admin-roles-state mg-hidden" data-roles-error><strong>Unable to load roles</strong><span data-roles-error-message>The permission matrix could not be loaded.</span></div>
          <div class="mg-admin-permission-groups mg-hidden" data-permission-groups></div>
        </section>
      </section>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
