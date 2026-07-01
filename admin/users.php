<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-auth.php';

$user = mg_require_admin_page_permission('admin.users.view');
$canViewUsers = true;
$canCreateUsers = mg_admin_page_user_has_permission($user, 'admin.users.manage');
$canManageAiLimits = mg_admin_page_user_has_permission($user, 'admin.settings.manage');
$page_title = 'User Center | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-admin-users-page';
$page_styles = ['/assets/css/admin-shell.css','/assets/css/admin-users.css'];
$page_scripts = [
    '/assets/js/admin-users.js',
    '/assets/js/admin-user-detail-drawer.js',
    '/assets/js/admin-user-ops-detail.js',
    '/assets/js/admin-user-management.js',
    '/assets/js/admin-ai-user-limits.js',
    '/assets/js/admin-create-user.js',
];
$adminActive = 'users';

require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-admin-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <div class="mg-app-workspace mg-admin-workspace">
    <section class="mg-admin-users-shell" data-admin-users data-admin-users-can-create="<?= $canCreateUsers ? '1' : '0' ?>" data-admin-users-can-manage-ai-limits="<?= $canManageAiLimits ? '1' : '0' ?>">
      <header class="mg-admin-users-hero">
        <div>
          <a class="mg-admin-users-back" href="/account-admin.php">← Admin dashboard</a>
          <span class="mg-eyebrow">Identity operations</span>
          <h1>User center</h1>
          <p>Search platform identities, create admin-managed users, and manage account, role, verification, model, and session state from one protected workspace.</p>
        </div>
        <?php if ($canViewUsers): ?>
          <div class="mg-admin-users-hero-actions">
            <span>Last updated <strong data-users-updated>—</strong></span>
            <?php if ($canCreateUsers): ?>
              <button class="mg-btn mg-btn-primary" type="button" data-user-create-open>Create user</button>
            <?php endif; ?>
            <button class="mg-btn mg-btn-ghost" type="button" data-users-refresh disabled>Refresh</button>
          </div>
        <?php endif; ?>
      </header>

      <?php if (!$canViewUsers): ?>
        <section class="mg-admin-users-access mg-app-panel">
          <h2>User directory access is not active.</h2>
          <p>This page requires the <code>admin.users.view</code> permission or a super-administrator role.</p>
          <a class="mg-btn mg-btn-soft" href="/account-admin.php">Back to admin</a>
        </section>
      <?php else: ?>
        <form class="mg-admin-users-filters" data-users-filters role="search">
          <label class="is-search">Search
            <input type="search" name="q" maxlength="160" autocomplete="off" placeholder="Email, name, profile, or slug">
          </label>
          <label>Account status
            <select name="status">
              <option value="">All statuses</option>
              <option value="active">Active</option>
              <option value="pending">Pending</option>
              <option value="disabled">Disabled</option>
            </select>
          </label>
          <label>Role
            <select name="role">
              <option value="">All roles</option>
              <option value="customer">Customer</option>
              <option value="merchant">Merchant</option>
              <option value="admin">Admin</option>
              <option value="super_admin">Super admin</option>
            </select>
          </label>
          <label>Email verification
            <select name="verification">
              <option value="">Any verification</option>
              <option value="verified">Verified</option>
              <option value="unverified">Unverified</option>
            </select>
          </label>
          <div class="mg-admin-users-filter-actions">
            <button class="mg-btn mg-btn-primary" type="submit">Apply filters</button>
            <button class="mg-btn mg-btn-ghost" type="reset" data-users-reset>Reset</button>
          </div>
        </form>

        <section class="mg-admin-users-panel">
          <header class="mg-admin-users-panel-head">
            <div>
              <h2>Platform identities</h2>
              <p data-users-summary>Loading user directory…</p>
            </div>
            <span class="mg-admin-users-readonly">Read only directory</span>
          </header>

          <div class="mg-admin-users-status" data-users-status role="status" aria-live="polite"></div>

          <div class="mg-admin-users-state" data-users-loading aria-busy="true">
            <strong>Loading users</strong>
            <span>Preparing the protected identity directory.</span>
          </div>

          <div class="mg-admin-users-state mg-hidden" data-users-error role="alert">
            <strong>Unable to load users</strong>
            <span data-users-error-message>The directory could not be loaded.</span>
            <button class="mg-btn mg-btn-soft" type="button" data-users-retry>Try again</button>
          </div>

          <div class="mg-admin-users-state mg-hidden" data-users-empty>
            <strong>No matching accounts</strong>
            <span>Try a broader name, email, role, status, or verification filter.</span>
          </div>

          <div class="mg-admin-users-table-wrap mg-hidden" data-users-content>
            <table class="mg-admin-users-table">
              <thead>
                <tr>
                  <th scope="col">Identity</th>
                  <th scope="col">Account</th>
                  <th scope="col">Roles</th>
                  <th scope="col">Public profile</th>
                  <th scope="col">Joined</th>
                </tr>
              </thead>
              <tbody data-users-list></tbody>
            </table>
          </div>

          <footer class="mg-admin-users-pagination mg-hidden" data-users-pagination>
            <span data-users-page-label></span>
            <button class="mg-btn mg-btn-soft" type="button" data-users-more>Load more users</button>
          </footer>
        </section>

        <?php if ($canCreateUsers): ?>
          <div class="mg-admin-user-create-layer mg-hidden" data-user-create-layer>
            <button class="mg-admin-user-create-backdrop" type="button" data-user-create-close aria-label="Close create user"></button>
            <aside class="mg-admin-user-create-modal" role="dialog" aria-modal="true" aria-labelledby="mg-admin-user-create-title">
              <header>
                <div>
                  <span class="mg-eyebrow">Admin action</span>
                  <h2 id="mg-admin-user-create-title">Create user</h2>
                  <p>Create a user, assign initial roles, set account status, and record the reason.</p>
                </div>
                <button class="mg-admin-user-drawer-close" type="button" data-user-create-close aria-label="Close create user">×</button>
              </header>
              <form data-user-create-form>
                <div class="mg-admin-user-create-grid">
                  <label>Full name
                    <input name="full_name" type="text" maxlength="160" required autocomplete="off">
                  </label>
                  <label>Display name
                    <input name="display_name" type="text" maxlength="160" autocomplete="off">
                  </label>
                  <label>Email
                    <input name="email" type="email" maxlength="255" required autocomplete="off">
                  </label>
                  <label>Temporary password
                    <input name="password" type="text" minlength="12" maxlength="120" placeholder="Leave blank to auto-generate">
                  </label>
                  <label>Account status
                    <select name="status">
                      <option value="active">Active</option>
                      <option value="pending">Pending</option>
                      <option value="disabled">Disabled</option>
                    </select>
                  </label>
                  <label>Email verification
                    <select name="email_verified">
                      <option value="0">Unverified</option>
                      <option value="1">Verified</option>
                    </select>
                  </label>
                </div>
                <fieldset class="mg-admin-user-create-roles">
                  <legend>Initial roles</legend>
                  <label><input type="checkbox" name="roles[]" value="customer" checked> Customer</label>
                  <label><input type="checkbox" name="roles[]" value="merchant"> Merchant</label>
                  <label><input type="checkbox" name="roles[]" value="admin"> Admin</label>
                  <label><input type="checkbox" name="roles[]" value="super_admin"> Super admin</label>
                </fieldset>
                <label class="mg-admin-management-reason"><span>Required action reason</span>
                  <textarea name="reason" rows="3" maxlength="240" required placeholder="Explain why this admin-created account is needed."></textarea>
                </label>
                <div class="mg-admin-user-create-notice" data-user-create-notice role="status" aria-live="polite"></div>
                <footer>
                  <button class="mg-btn mg-btn-ghost" type="button" data-user-create-close>Cancel</button>
                  <button class="mg-btn mg-btn-primary" type="submit">Create user</button>
                </footer>
              </form>
            </aside>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
