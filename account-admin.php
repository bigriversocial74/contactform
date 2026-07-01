<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Admin Dashboard | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_styles = ['/assets/css/admin-dashboard.css'];
$page_scripts = ['/assets/js/account.js', '/assets/js/admin-dashboard.js'];

$user = mg_current_user();
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
$isSuperAdmin = in_array('super_admin', $roles, true);
$adminPermissionSet = [
  'admin.users.view', 'admin.users.manage', 'admin.audit.view', 'admin.health.view',
  'admin.profiles.moderation.view', 'admin.profiles.moderation.manage',
  'security.logs.view', 'admin.security_logs.view', 'admin.sessions.view',
  'operational.alerts.view', 'demand.dashboard.view', 'intelligence.dashboard.view',
  'merchant.payments.view', 'subscriptions.admin', 'microgift.operations.view', 'tips.reverse', 'share_market.admin',
  'admin.pwa_branding.view', 'admin.pwa_branding.manage', 'admin.pwa_notifications.test',
];
$hasAdminAccess = $isSuperAdmin || count(array_intersect($adminPermissionSet, $permissions)) > 0;
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
            <p>This account does not have an administrative permission.</p>
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
