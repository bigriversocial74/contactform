<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/admin-navigation-access.php';

$page_title = 'Admin Dashboard | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_styles = ['/assets/css/admin-dashboard.css'];
$page_scripts = ['/assets/js/account.js', '/assets/js/admin-dashboard.js', '/assets/js/reviews-management-nav.js?v=1.0.0'];

$user = mg_current_user();
$packageContext = $user ? mg_user_package_context(null, $user) : [];
$accountIsFree = !empty($packageContext['is_free']);
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
$isSuperAdmin = in_array('super_admin', $roles, true);
$hasAdminAccess = $user && !$accountIsFree && mg_admin_navigation_user_can_access($user);
$canPublicDonationsOperations = $hasAdminAccess && (
  $isSuperAdmin
  || in_array('admin.settings.manage', $permissions, true)
  || in_array('admin.public_donations_operations.view', $permissions, true)
  || in_array('admin.public_donations_operations.manage', $permissions, true)
);
if ($canPublicDonationsOperations) {
  $page_scripts[] = '/assets/js/admin-public-donations-nav.js?v=20260724-v1';
}
$adminActive = 'dashboard';

require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app">
  <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>

  <main class="mg-app-workspace mg-account-main">
    <?php if (!$user): ?>
      <section class="mg-account-guest mg-app-panel">
        <div class="mg-app-panel-head">
          <div>
            <h2>Admin access</h2>
            <p>Sign in to continue to the Microgifter admin dashboard.</p>
          </div>
        </div>
        <div class="mg-app-panel-body">
          <div class="mg-action-row">
            <a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a>
            <a class="mg-btn mg-btn-ghost" href="/signup.php">Create account</a>
          </div>
        </div>
      </section>
    <?php elseif ($hasAdminAccess): ?>
      <?php require __DIR__ . '/includes/account/admin-dashboard.php'; ?>
    <?php else: ?>
      <section class="mg-app-panel mg-account-pane is-active">
        <div class="mg-app-panel-head">
          <div>
            <h2>Admin access is not active.</h2>
            <p><?= $accountIsFree ? 'The Admin dashboard is not available on the Free package.' : 'This account does not have an administrative role or permission.' ?></p>
          </div>
        </div>
        <div class="mg-app-panel-body">
          <a class="mg-btn mg-btn-ghost" href="/account.php">Back to account</a>
        </div>
      </section>
    <?php endif; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
