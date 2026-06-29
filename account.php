<?php
require_once __DIR__ . '/includes/app.php';
$accountView = defined('MG_ACCOUNT_VIEW') ? MG_ACCOUNT_VIEW : 'profile';
$page_title = match ($accountView) {
  'admin' => 'Admin Dashboard | Microgifter',
  'share_market_admin' => 'Share Market Admin | Microgifter',
  'investment_tests' => 'Investment Tests | Microgifter',
  'marketplace_index' => 'Marketplace Index | Microgifter',
  'market' => 'Market Dashboard | Microgifter',
  'share_market' => 'Share Market Program | Microgifter',
  'profile_moderation' => 'Profile Moderation | Microgifter',
  'wallet' => 'My Wallet | Microgifter',
  'subscriptions' => 'My Subscription | Microgifter',
  'profile' => 'Profile Editor | Microgifter',
  default => 'Account | Microgifter',
};
$page_section = 'account';
$header_mode = 'account';
$page_styles = [];
$page_scripts = [];
if ($accountView === 'profile') {
  $page_styles[] = '/assets/css/profile-editor.css';
  $page_styles[] = '/assets/css/profile-moderation-owner.css';
  $page_scripts[] = '/assets/js/profile-editor.js';
  $page_scripts[] = '/assets/js/account-public-profile-link.js';
  $page_scripts[] = '/assets/js/profile-moderation-owner.js';
} elseif ($accountView === 'profile_moderation') {
  $page_styles[] = '/assets/css/profile-moderation.css';
  $page_scripts[] = '/assets/js/profile-moderation.js';
} elseif ($accountView === 'wallet') {
  $page_styles[] = '/assets/css/merchant-workspace.css';
  $page_scripts[] = '/assets/js/stage12-wallet.js';
} else {
  $page_scripts[] = '/assets/js/account.js';
}
if ($accountView === 'market' || $accountView === 'share_market') {
  $page_styles[] = '/assets/css/market-dashboard.css';
}
if ($accountView === 'marketplace_index') {
  $page_styles[] = '/assets/css/marketplace-dashboard.css';
}
if ($accountView === 'admin' || $accountView === 'share_market_admin' || $accountView === 'investment_tests' || $accountView === 'marketplace_index') {
  $page_styles[] = '/assets/css/admin-dashboard.css';
  if ($accountView === 'investment_tests') $page_styles[] = '/assets/css/investment-tests.css';
  if ($accountView === 'admin') $page_scripts[] = '/assets/js/admin-dashboard.js';
  if ($accountView === 'share_market_admin') $page_scripts[] = '/assets/js/share-market-admin.js';
}
$user = mg_current_user();
$roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
$permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
$isSuperAdmin = in_array('super_admin', $roles, true);
$canViewAdminSessions = in_array('admin.sessions.view', $permissions, true) || $isSuperAdmin;
$canRevokeAdminSessions = in_array('admin.sessions.revoke', $permissions, true) || $isSuperAdmin;
$canViewSecurityLogs = in_array('security.logs.view', $permissions, true) || in_array('admin.security_logs.view', $permissions, true) || $isSuperAdmin;
$canViewProfileModeration = in_array('admin.profiles.moderation.view', $permissions, true) || in_array('admin.profiles.moderation.manage', $permissions, true) || $isSuperAdmin;
$canManageProfileModeration = in_array('admin.profiles.moderation.manage', $permissions, true) || $isSuperAdmin;
$canMerchantCatalog = in_array('admin.merchants.view', $permissions, true) || in_array('admin.catalog.view', $permissions, true) || $isSuperAdmin;
$canCommerce = in_array('admin.commerce.view', $permissions, true) || in_array('merchant.payments.view', $permissions, true) || in_array('subscriptions.admin', $permissions, true) || in_array('microgift.operations.view', $permissions, true) || in_array('tips.reverse', $permissions, true) || $isSuperAdmin;
$canSubscriptionRequests = in_array('subscriptions.admin', $permissions, true) || $isSuperAdmin;
$canOpsQueue = in_array('ops.alerts.assign', $permissions, true) || in_array('ops.alerts.resolve', $permissions, true) || $isSuperAdmin;
$canAiSettings = in_array('admin.settings.manage', $permissions, true) || $isSuperAdmin;
$canInvestmentTests = in_array('admin.health.view', $permissions, true) || in_array('demand.dashboard.view', $permissions, true) || in_array('intelligence.dashboard.view', $permissions, true) || $isSuperAdmin;
$canMarketplaceIndex = $canInvestmentTests;
$adminPermissionSet = [
  'admin.users.view', 'admin.users.manage', 'admin.audit.view', 'admin.health.view',
  'admin.profiles.moderation.view', 'admin.profiles.moderation.manage',
  'security.logs.view', 'admin.security_logs.view', 'admin.sessions.view',
  'operational.alerts.view', 'demand.dashboard.view', 'intelligence.dashboard.view',
  'merchant.payments.view', 'subscriptions.admin', 'microgift.operations.view', 'tips.reverse', 'share_market.admin',
];
$hasAdminAccess = $isSuperAdmin || count(array_intersect($adminPermissionSet, $permissions)) > 0;
$canShareMarketAdmin = in_array('share_market.admin', $permissions, true) || $isSuperAdmin;
$accountNav = [
  'profile' => ['label' => 'Profile', 'href' => '/account.php', 'detail' => 'Public identity', 'visible' => true],
  'market' => ['label' => 'Market', 'href' => '/account-market.php', 'detail' => 'Ticker, score, funnel, and risk', 'visible' => true],
  'share_market' => ['label' => 'Share Market', 'href' => '/account-share-market.php', 'detail' => 'Optional artist value program', 'visible' => true],
  'subscriptions' => ['label' => 'My Subscription', 'href' => '/account-subscriptions.php', 'detail' => 'Plan and upgrade', 'visible' => true],
  'wallet' => ['label' => 'Wallet', 'href' => '/wallet.php', 'detail' => 'Local rewards', 'visible' => true],
  'models' => ['label' => 'Models', 'href' => '/account-models.php', 'detail' => 'User model access', 'visible' => true],
  'security' => ['label' => 'Security', 'href' => '/account-security.php', 'detail' => 'Sessions', 'visible' => true],
  'access' => ['label' => 'Access', 'href' => '/account-access.php', 'detail' => 'Roles and permissions', 'visible' => true],
];
if ($hasAdminAccess) $accountNav['admin'] = ['label' => 'Admin', 'href' => '/account-admin.php', 'detail' => 'Platform controls', 'visible' => true];
if ($canViewProfileModeration) $accountNav['profile_moderation'] = ['label' => 'Moderation', 'href' => '/account-profile-moderation.php', 'detail' => 'Profile review queue', 'visible' => true];
$adminSidebarNav = [
  'admin' => ['label' => 'Admin dashboard', 'href' => '/account-admin.php', 'detail' => 'Platform overview', 'visible' => $hasAdminAccess],
  'share_market_admin' => ['label' => 'Share Market Admin', 'href' => '/account-share-market-admin.php', 'detail' => 'Pools, credits, series, risk', 'visible' => $canShareMarketAdmin],
  'marketplace_index' => ['label' => 'Marketplace Index', 'href' => '/account-marketplace.php', 'detail' => 'Aggregate value, scores, movers', 'visible' => $canMarketplaceIndex],
  'investment_tests' => ['label' => 'Investment Tests', 'href' => '/account-investment-tests.php', 'detail' => 'Market scores and snapshots', 'visible' => $canInvestmentTests],
  'profile_moderation' => ['label' => 'Moderation', 'href' => '/account-profile-moderation.php', 'detail' => 'Profile review queue', 'visible' => $canViewProfileModeration],
  'admin_users' => ['label' => 'Users', 'href' => '/admin/users.php', 'detail' => 'Accounts and access', 'visible' => in_array('admin.users.view', $permissions, true) || $isSuperAdmin],
  'pending_models' => ['label' => 'Pending models', 'href' => '/admin/pending-models.php', 'detail' => 'Model approval queue', 'visible' => in_array('admin.users.view', $permissions, true) || $isSuperAdmin],
  'merchant_catalog' => ['label' => 'Merchants & catalog', 'href' => '/merchant-catalog-operations.php', 'detail' => 'Stores, products, media', 'visible' => $canMerchantCatalog],
  'commerce' => ['label' => 'Commerce operations', 'href' => '/commerce-operations.php', 'detail' => 'Orders and lifecycle', 'visible' => $canCommerce],
  'subscription_requests' => ['label' => 'Subscription requests', 'href' => '/admin/subscription-requests.php', 'detail' => 'Package upgrades', 'visible' => $canSubscriptionRequests],
  'audit_logs' => ['label' => 'Audit logs', 'href' => '/admin/audit-logs.php', 'detail' => 'Administrative activity', 'visible' => in_array('admin.audit.view', $permissions, true) || $isSuperAdmin],
  'security_logs' => ['label' => 'Security logs', 'href' => '/admin/security-logs.php', 'detail' => 'Security events', 'visible' => $canViewSecurityLogs],
  'sessions' => ['label' => 'Sessions', 'href' => '/admin/sessions.php', 'detail' => 'Active user sessions', 'visible' => $canViewAdminSessions],
  'system_health' => ['label' => 'System health', 'href' => '/admin/system-health.php', 'detail' => 'Runtime and delivery', 'visible' => in_array('admin.health.view', $permissions, true) || $isSuperAdmin],
  'lifecycle_health' => ['label' => 'Lifecycle health', 'href' => '/admin/lifecycle-health.php', 'detail' => 'Checkout to redemption', 'visible' => in_array('admin.health.view', $permissions, true) || $isSuperAdmin],
  'ops_queue' => ['label' => 'Ops queue', 'href' => '/admin/ops-queue.php', 'detail' => 'Alerts and incidents', 'visible' => $canOpsQueue],
  'payments' => ['label' => 'Stripe payments', 'href' => '/admin-payments.php', 'detail' => 'Credentials and readiness', 'visible' => $canAiSettings],
  'ai_settings' => ['label' => 'AI settings', 'href' => '/admin-ai.php', 'detail' => 'Models and providers', 'visible' => $canAiSettings],
];
$sidebarNav = in_array($accountView, ['admin', 'share_market_admin', 'investment_tests', 'marketplace_index'], true) ? $adminSidebarNav : $accountNav;
$knownViews = ['profile', 'market', 'share_market', 'subscriptions', 'wallet', 'models', 'security', 'access', 'admin', 'share_market_admin', 'investment_tests', 'marketplace_index', 'profile_moderation'];
if (!in_array($accountView, $knownViews, true)) $accountView = 'profile';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app">
  <aside class="mg-app-sidebar mg-account-left">
    <div class="mg-app-sidebar-brand">
      <a class="mg-brand mg-sidebar-logo" href="/index.php" aria-label="Microgifter home"><img src="/images/logo_main_drk.png" alt="Microgifter"><span class="mg-sidebar-logo-text">Microgifter</span></a>
    </div>
    <?php if ($user): ?>
      <nav class="mg-app-side-nav mg-account-nav" aria-label="<?= in_array($accountView, ['admin', 'share_market_admin', 'investment_tests', 'marketplace_index'], true) ? 'Admin pages' : 'Account pages' ?>">
        <?php foreach ($sidebarNav as $key => $item): ?>
          <?php if (array_key_exists('visible', $item) && !$item['visible']) { continue; } ?>
          <a class="<?= $accountView === $key ? 'is-active' : '' ?>" href="<?= mg_e($item['href']) ?>"><strong><?= mg_e($item['label']) ?></strong><span><?= mg_e($item['detail']) ?></span></a>
        <?php endforeach; ?>
      </nav>
    <?php else: ?>
      <div class="mg-app-sidebar-card"><h2>Account access</h2><p>Sign in or create an account to manage your Microgifter workspace.</p></div>
      <nav class="mg-app-side-nav mg-account-nav" aria-label="Guest account actions"><a href="/signin.php"><strong>Sign in</strong><span>Continue to your account</span></a><a href="/signup.php"><strong>Create account</strong><span>Start a new workspace</span></a></nav>
    <?php endif; ?>
  </aside>

  <main class="mg-app-workspace mg-account-main">
    <?php if (!$user): ?>
      <section class="mg-account-guest mg-app-panel"><div class="mg-app-panel-head"><div><h2>Account access</h2><p>Sign in to continue to your profile, wallet, models, security, and settings.</p></div></div><div class="mg-app-panel-body"><div class="mg-action-row"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a><a class="mg-btn mg-btn-ghost" href="/signup.php">Create account</a></div></div></section>
    <?php elseif ($accountView === 'profile'): ?>
      <?php require __DIR__ . '/includes/account/profile-moderation-owner.php'; ?>
      <?php require __DIR__ . '/includes/account/profile-editor.php'; ?>
    <?php elseif ($accountView === 'market'): ?>
      <?php require __DIR__ . '/includes/account/market-dashboard.php'; ?>
    <?php elseif ($accountView === 'share_market'): ?>
      <?php require __DIR__ . '/includes/account/share-market-program.php'; ?>
    <?php elseif ($accountView === 'subscriptions'): ?>
      <?php require __DIR__ . '/includes/account/subscriptions-view.php'; ?>
    <?php elseif ($accountView === 'wallet'): ?>
      <?php require __DIR__ . '/includes/account/wallet-view.php'; ?>
    <?php elseif ($accountView === 'models'): ?>
      <section class="mg-app-panel mg-account-pane is-active" data-account-pane="models"><div class="mg-app-panel-head"><div><h2>Identity onboarding</h2><p>Request the models you want to operate as. Approval-gated models keep the platform clean before commerce is added.</p></div></div><div class="mg-app-panel-body"><div class="mg-model-list" data-user-model-list><p class="mg-muted">Loading models…</p></div></div></section>
    <?php elseif ($accountView === 'security'): ?>
      <section class="mg-app-panel mg-account-pane is-active" data-account-pane="security"><div class="mg-app-panel-head"><div><h2>Security &amp; sessions</h2><p>Review active sessions and revoke access if a device is lost, shared, or suspicious.</p></div></div><div class="mg-app-panel-body"><div class="mg-action-row"><button class="mg-btn mg-btn-ghost" type="button" data-session-revoke="all_except_current">Sign out other devices</button><button class="mg-btn mg-btn-soft" type="button" data-session-revoke="current">Sign out this device</button><button class="mg-btn mg-btn-soft" type="button" data-session-revoke="all">Sign out everywhere</button></div><div class="mg-session-list" data-account-sessions><p class="mg-muted">Loading sessions…</p></div></div></section>
    <?php elseif ($accountView === 'access'): ?>
      <section class="mg-app-panel mg-account-pane is-active" data-account-pane="access"><div class="mg-app-panel-head"><div><h2>Access profile</h2><p>Your current session is hydrated from the Stage 1 auth and permission layer.</p></div></div><div class="mg-app-panel-body"><div class="mg-account-section"><h3>Roles</h3><?php if ($roles): ?><div class="mg-chip-list"><?php foreach ($roles as $role): ?><span class="mg-chip"><?= mg_e($role) ?></span><?php endforeach; ?></div><?php else: ?><p class="mg-muted">No roles are attached to this session yet.</p><?php endif; ?></div><div class="mg-account-section"><h3>Permissions</h3><?php if ($permissions): ?><div class="mg-permission-list"><?php foreach ($permissions as $permission): ?><span><?= mg_e($permission) ?></span><?php endforeach; ?></div><?php else: ?><p class="mg-muted">No explicit permissions are attached to this session yet.</p><?php endif; ?></div></div></section>
    <?php elseif ($accountView === 'profile_moderation' && $canViewProfileModeration): ?>
      <?php require __DIR__ . '/includes/account/profile-moderation.php'; ?>
    <?php elseif ($accountView === 'profile_moderation'): ?>
      <section class="mg-app-panel mg-account-pane is-active"><div class="mg-app-panel-head"><div><h2>Moderation access is not active.</h2><p>This account does not have profile moderation permission.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-ghost" href="/account.php">Back to account</a></div></section>
    <?php elseif ($accountView === 'investment_tests' && $canInvestmentTests): ?>
      <?php require __DIR__ . '/includes/account/investment-tests.php'; ?>
    <?php elseif ($accountView === 'investment_tests'): ?>
      <section class="mg-app-panel mg-account-pane is-active"><div class="mg-app-panel-head"><div><h2>Investment Tests access is not active.</h2><p>This account does not have permission to run market score and snapshot tests.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-ghost" href="/account-admin.php">Back to admin</a></div></section>
    <?php elseif ($accountView === 'marketplace_index' && $canMarketplaceIndex): ?>
      <?php require __DIR__ . '/includes/account/marketplace-dashboard.php'; ?>
    <?php elseif ($accountView === 'marketplace_index'): ?>
      <section class="mg-app-panel mg-account-pane is-active"><div class="mg-app-panel-head"><div><h2>Marketplace Index access is not active.</h2><p>This account does not have permission to view marketplace value and movement.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-ghost" href="/account-admin.php">Back to admin</a></div></section>
    <?php elseif ($accountView === 'share_market_admin' && $canShareMarketAdmin): ?>
      <?php require __DIR__ . '/includes/account/share-market-admin.php'; ?>
      <?php require __DIR__ . '/includes/account/share-market-admin-workflow.php'; ?>
    <?php elseif ($accountView === 'share_market_admin'): ?>
      <section class="mg-app-panel mg-account-pane is-active"><div class="mg-app-panel-head"><div><h2>Share Market Admin access is not active.</h2><p>This account requires the explicit share_market.admin permission or the super_admin role.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-ghost" href="/account-admin.php">Back to admin</a></div></section>
    <?php elseif ($hasAdminAccess): ?>
      <?php require __DIR__ . '/includes/account/admin-dashboard.php'; ?>
    <?php else: ?>
      <section class="mg-app-panel mg-account-pane is-active"><div class="mg-app-panel-head"><div><h2>Admin access is not active.</h2><p>This account does not have an administrative permission.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-ghost" href="/account.php">Back to account</a></div></section>
    <?php endif; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
