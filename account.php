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
      <?php
        require_once __DIR__ . '/includes/pricing-packages.php';
        $plans = mg_public_pricing_packages();
        $packageSummary = mg_pricing_package_summary();
        $currentPackageId = 'starter';
        $currentPlan = $plans[0] ?? [];
        foreach ($plans as $candidatePlan) {
          if (($candidatePlan['id'] ?? '') === $currentPackageId) {
            $currentPlan = $candidatePlan;
            break;
          }
        }
        $currentLimits = is_array($currentPlan['limits'] ?? null) ? $currentPlan['limits'] : [];
        $currentPromotionsLimit = (int)($currentLimits['max_active_campaigns'] ?? 50);
        $currentPromotionsLimit = max($currentPromotionsLimit, 50);
        $currentMonthlyStamps = is_numeric($currentLimits['monthly_stamps_included'] ?? null) ? (int)$currentLimits['monthly_stamps_included'] : 0;
        $currentPlanPrice = (string)($currentPlan['price_label'] ?? '$0');
        $currentBillingLabel = (string)($currentPlan['billing_label'] ?? '/mo');
        $totalRewardLimit = is_numeric($currentLimits['max_rewards'] ?? null) ? (int)$currentLimits['max_rewards'] : 0;
        $metricRewardsDistributed = max(245, $totalRewardLimit * 49);
        $metricCustomerEngagements = max(1200, $currentMonthlyStamps + 200);
        $metricCustomerEngagementsLabel = $metricCustomerEngagements >= 1000 ? rtrim(rtrim(number_format($metricCustomerEngagements / 1000, 1), '0'), '.') . 'K' : number_format($metricCustomerEngagements);
      ?>
      <style>
        .mg-subscription-redesign,
        .mg-subscription-redesign *{box-sizing:border-box}
        .mg-subscription-redesign{overflow:hidden;background:#fff;border-color:#dbe7f8;box-shadow:0 22px 55px rgba(13,38,76,.08)}
        .mg-subscription-redesign .mg-app-panel-head{align-items:flex-start;padding:24px 26px 20px;background:linear-gradient(180deg,#fff,#fbfdff);border-bottom:1px solid #dbe7f8}
        .mg-subscription-redesign .mg-app-panel-head h2{margin:0;color:#03132e;font-size:24px;line-height:1.1;font-weight:950;letter-spacing:-.05em}
        .mg-subscription-redesign .mg-app-panel-head p{margin-top:8px;color:#536789;font-size:14px;font-weight:550}
        .mg-subscription-redesign .mg-app-panel-body{padding:24px 26px 28px;background:radial-gradient(circle at 88% 0,rgba(47,93,245,.08),transparent 30%),linear-gradient(180deg,#fff 0%,#fbfdff 100%)}
        .mg-sub-hero{display:grid;grid-template-columns:minmax(300px,.9fr) minmax(460px,1.28fr);gap:22px;align-items:stretch;margin-bottom:26px;padding:24px;border-radius:20px;background:radial-gradient(circle at 34% 12%,rgba(51,91,255,.4),transparent 20%),radial-gradient(circle at 88% 86%,rgba(136,63,255,.38),transparent 26%),linear-gradient(135deg,#09194a 0%,#111266 52%,#12072c 100%);box-shadow:0 22px 48px rgba(10,20,82,.28);position:relative;overflow:hidden}
        .mg-sub-hero:before{content:"";position:absolute;inset:-80px -120px auto auto;width:420px;height:280px;border-radius:999px;border:2px solid rgba(78,109,255,.4);transform:rotate(28deg);opacity:.65}
        .mg-sub-hero:after{content:"";position:absolute;inset:auto auto -90px 19%;width:280px;height:240px;border-radius:999px;background:radial-gradient(circle,rgba(58,83,255,.2),transparent 68%);opacity:.8}
        .mg-sub-current,.mg-sub-metrics{position:relative;z-index:2}.mg-sub-current{color:#fff;padding:4px 0}.mg-sub-kicker{display:inline-flex;align-items:center;gap:8px;margin-bottom:9px;color:rgba(255,255,255,.76);font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.mg-sub-kicker:before{content:"";width:7px;height:7px;border-radius:999px;background:#30d49b;box-shadow:0 0 0 6px rgba(48,212,155,.15)}
        .mg-sub-plan-title{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.mg-sub-plan-title h3{margin:0;color:#fff;font-size:30px;line-height:1.05;font-weight:950;letter-spacing:-.05em}.mg-sub-status{display:inline-flex;align-items:center;min-height:26px;padding:0 10px;border-radius:999px;background:#19a873;color:#fff;font-size:12px;font-weight:950}.mg-sub-current-copy{max-width:560px;margin:15px 0 18px;color:rgba(255,255,255,.82);font-size:14px;line-height:1.55;font-weight:520}.mg-sub-current-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:0;margin-top:20px;padding-top:18px;border-top:1px solid rgba(255,255,255,.16)}.mg-sub-current-meta div{padding-right:18px}.mg-sub-current-meta div+div{padding-left:18px;border-left:1px solid rgba(255,255,255,.13)}.mg-sub-current-meta span{display:block;margin-bottom:4px;color:rgba(255,255,255,.64);font-size:12px;font-weight:750}.mg-sub-current-meta strong{display:block;color:#fff;font-size:15px;line-height:1.3;font-weight:950}
        .mg-sub-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:0;align-self:center;min-height:150px;border-radius:14px;background:rgba(255,255,255,.96);box-shadow:0 18px 45px rgba(0,0,0,.14);overflow:hidden}.mg-sub-metric{display:flex;flex-direction:column;justify-content:center;min-height:150px;padding:18px;text-align:center;border-right:1px solid #e2e9f5}.mg-sub-metric:last-child{border-right:0}.mg-sub-metric-icon{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;margin:0 auto 14px;border-radius:14px;background:#eef3ff;color:#3159ff;font-size:18px;font-weight:950}.mg-sub-metric:nth-child(2) .mg-sub-metric-icon{background:#f6eaff;color:#a53dff}.mg-sub-metric:nth-child(3) .mg-sub-metric-icon{background:#fff0e8;color:#ff7628}.mg-sub-metric:nth-child(4) .mg-sub-metric-icon{background:#fff7de;color:#d69a00}.mg-sub-metric span{margin-bottom:8px;color:#536789;font-size:12px;line-height:1.35;font-weight:750}.mg-sub-metric strong{color:#071a44;font-size:clamp(19px,1.5vw,25px);line-height:1.1;font-weight:950;letter-spacing:-.045em}.mg-sub-metric small{margin-top:8px;color:#7788a3;font-size:12px;font-weight:750}
        .mg-sub-section-top{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin:0 0 20px}.mg-sub-section-title h3{margin:0;color:#061735;font-size:22px;line-height:1.15;font-weight:950;letter-spacing:-.04em}.mg-sub-section-title p{max-width:920px;margin:9px 0 0;color:#536789;font-size:15px;line-height:1.55;font-weight:520}.mg-sub-toggle{display:inline-grid;grid-template-columns:1fr 1fr;min-width:310px;padding:5px;gap:4px;border-radius:16px;border:1px solid #e0e8f5;background:#f1f5fb;box-shadow:inset 0 0 0 1px rgba(255,255,255,.7)}.mg-sub-toggle span{display:flex;align-items:center;justify-content:center;min-height:36px;padding:0 16px;border-radius:12px;color:#435b80;font-size:13px;font-weight:950;white-space:nowrap}.mg-sub-toggle span:first-child{background:#fff;color:#3159ff;box-shadow:0 8px 20px rgba(13,38,76,.1)}
        .mg-sub-plans{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px}.mg-sub-plan-card{position:relative;display:flex;flex-direction:column;min-height:440px;border:1px solid #dbe7f8;border-radius:17px;background:radial-gradient(circle at 100% 0,rgba(47,93,245,.06),transparent 28%),#fff;box-shadow:0 16px 38px rgba(13,38,76,.06);overflow:hidden}.mg-sub-plan-card.is-featured{border-color:#3b5bff;box-shadow:0 20px 48px rgba(47,93,245,.16)}.mg-sub-ribbon{height:34px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#3159ff 0%,#5d6cff 100%);color:#fff;font-size:12px;font-weight:950}.mg-sub-plan-inner{display:flex;flex-direction:column;height:100%;padding:24px 22px 22px}.mg-sub-plan-card.is-featured .mg-sub-plan-inner{padding-top:22px}.mg-sub-plan-card h4{margin:0;color:#071a44;font-size:20px;line-height:1.1;font-weight:950;letter-spacing:-.035em}.mg-sub-plan-desc{min-height:72px;margin:10px 0 18px;color:#536789;font-size:13px;line-height:1.5;font-weight:520}.mg-sub-price{display:flex;align-items:flex-end;gap:6px;margin:0 0 4px;color:#061735}.mg-sub-price strong{font-size:34px;line-height:.95;font-weight:950;letter-spacing:-.065em}.mg-sub-price span{padding-bottom:2px;color:#52688b;font-size:13px;font-weight:850}.mg-sub-billed{min-height:20px;margin:4px 0 16px;color:#7788a3;font-size:12px;font-weight:750}.mg-sub-action{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:42px;margin:0 0 20px;border-radius:9px;border:1px solid #355cff;background:#fff;color:#3159ff!important;font-size:14px;font-weight:950;line-height:1;text-decoration:none;transition:.18s ease}.mg-sub-action.is-primary{background:linear-gradient(135deg,#3159ff 0%,#465dff 100%);color:#fff!important}.mg-sub-action.is-current{border-color:#d9e3f4;background:#f3f6fb;color:#a9b6cc!important;pointer-events:none}.mg-sub-features{display:grid;gap:11px;margin:0;padding:0;list-style:none}.mg-sub-features li{display:grid;grid-template-columns:18px 1fr;gap:9px;align-items:start;color:#334a6f;font-size:13px;line-height:1.45;font-weight:650}.mg-sub-features li:before{content:"✓";display:grid;place-items:center;width:16px;height:16px;margin-top:1px;border-radius:50%;background:#071a44;color:#fff;font-size:10px;font-weight:950}.mg-sub-features li.is-muted{color:#9aa8bf}.mg-sub-features li.is-muted:before{content:"–";background:#edf2fa;color:#8b9ab3}.mg-sub-fit{margin:auto 0 0;padding-top:18px;color:#71829f;font-size:12px;line-height:1.45;font-weight:750}
        .mg-sub-bottom{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(300px,.65fr);gap:28px;margin-top:28px}.mg-sub-why{padding:22px;border:1px solid #dbe7f8;border-radius:16px;background:#fff;box-shadow:0 16px 38px rgba(13,38,76,.05)}.mg-sub-why h3{margin:0 0 16px;color:#061735;font-size:18px;line-height:1.2;font-weight:950;letter-spacing:-.03em}.mg-sub-reasons{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.mg-sub-reason{display:grid;grid-template-columns:38px 1fr;gap:12px;align-items:start}.mg-sub-reason-icon{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:13px;background:#eef3ff;color:#3159ff;font-size:18px;font-weight:950}.mg-sub-reason:nth-child(2) .mg-sub-reason-icon{background:#f6eaff;color:#a53dff}.mg-sub-reason:nth-child(3) .mg-sub-reason-icon{background:#e9fff1;color:#17a562}.mg-sub-reason:nth-child(4) .mg-sub-reason-icon{background:#fff0f5;color:#f2457b}.mg-sub-reason strong{display:block;margin-bottom:4px;color:#071a44;font-size:14px;line-height:1.25;font-weight:950}.mg-sub-reason span{display:block;color:#536789;font-size:13px;line-height:1.4;font-weight:520}.mg-sub-custom{display:flex;align-items:center;gap:18px;min-height:100%;padding:24px;border:1px solid #e7e8ff;border-radius:16px;background:radial-gradient(circle at 0 0,rgba(167,104,255,.18),transparent 38%),linear-gradient(135deg,#fbfaff 0%,#f0f4ff 100%);box-shadow:0 16px 38px rgba(13,38,76,.05)}.mg-sub-custom-icon{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:16px;background:#fff;color:#9747ff;font-size:25px;font-weight:950;box-shadow:0 10px 22px rgba(91,53,181,.12)}.mg-sub-custom h3{margin:0 0 6px;color:#071a44;font-size:18px;line-height:1.2;font-weight:950;letter-spacing:-.03em}.mg-sub-custom p{margin:0 0 14px;color:#536789;font-size:14px;line-height:1.45;font-weight:520}.mg-sub-custom a{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 20px;border-radius:9px;border:1px solid #cbd8ff;background:#fff;color:#3159ff!important;font-size:13px;font-weight:950;text-decoration:none}
        @media(max-width:1280px){.mg-sub-hero{grid-template-columns:1fr}.mg-sub-plans{grid-template-columns:repeat(2,minmax(0,1fr))}.mg-sub-bottom{grid-template-columns:1fr}.mg-sub-metrics{min-height:auto}.mg-sub-metric{min-height:132px}}
        @media(max-width:920px){.mg-subscription-redesign .mg-app-panel-body{padding:20px}.mg-sub-current-meta{grid-template-columns:1fr;gap:12px}.mg-sub-current-meta div,.mg-sub-current-meta div+div{padding:0;border-left:0}.mg-sub-metrics{grid-template-columns:repeat(2,1fr)}.mg-sub-metric:nth-child(2){border-right:0}.mg-sub-metric:nth-child(1),.mg-sub-metric:nth-child(2){border-bottom:1px solid #e2e9f5}.mg-sub-section-top{align-items:stretch;flex-direction:column}.mg-sub-toggle{width:100%;min-width:0}.mg-sub-reasons{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:640px){.mg-sub-hero{padding:18px}.mg-sub-plans{grid-template-columns:1fr}.mg-sub-metrics{grid-template-columns:1fr}.mg-sub-metric{border-right:0;border-bottom:1px solid #e2e9f5}.mg-sub-metric:last-child{border-bottom:0}.mg-sub-reasons{grid-template-columns:1fr}.mg-sub-custom{align-items:flex-start;flex-direction:column}}
      </style>
      <section class="mg-app-panel mg-account-pane mg-subscription-redesign is-active" data-account-pane="subscriptions">
        <div class="mg-app-panel-head"><div><h2>My Subscription</h2><p>The Rewards Layer for Local Commerce.</p></div></div>
        <div class="mg-app-panel-body">
          <section class="mg-sub-hero" aria-label="Current subscription">
            <div class="mg-sub-current">
              <div class="mg-sub-kicker">Current Plan</div>
              <div class="mg-sub-plan-title"><h3><?= mg_e((string)($currentPlan['name'] ?? 'Starter')) ?></h3><span class="mg-sub-status">Active</span></div>
              <p class="mg-sub-current-copy">Your platform access is active. Manage Promotional CRM, Rewards Layer, pre-sale commerce, customer engagement, and tracked revenue from one workspace.</p>
              <div class="mg-sub-current-meta">
                <div><span>Renews on</span><strong><?= mg_e(date('M j, Y', strtotime('+30 days'))) ?></strong></div>
                <div><span>Billing</span><strong>Monthly</strong></div>
                <div><span>Next charge</span><strong><?= mg_e($currentPlanPrice . ($currentBillingLabel === '/mo' ? '.00' : '')) ?></strong></div>
              </div>
            </div>
            <div class="mg-sub-metrics" aria-label="Subscription usage">
              <div class="mg-sub-metric"><div class="mg-sub-metric-icon">◎</div><span>Total Promotions</span><strong>12 / <?= mg_e(number_format($currentPromotionsLimit)) ?></strong><small>This billing cycle</small></div>
              <div class="mg-sub-metric"><div class="mg-sub-metric-icon">♡</div><span>Total Rewards Distributed</span><strong><?= mg_e(number_format($metricRewardsDistributed)) ?></strong><small>All time</small></div>
              <div class="mg-sub-metric"><div class="mg-sub-metric-icon">♙</div><span>Customer Engagements</span><strong><?= mg_e($metricCustomerEngagementsLabel) ?></strong><small>All time</small></div>
              <div class="mg-sub-metric"><div class="mg-sub-metric-icon">↗</div><span>Revenue Tracked</span><strong>$6,340</strong><small>All time</small></div>
            </div>
          </section>

          <section aria-label="Plans and pricing">
            <div class="mg-sub-section-top">
              <div class="mg-sub-section-title">
                <h3>Plans &amp; Pricing</h3>
                <p>Compare plans for Promotional CRM, direct feed distribution, engagement campaigns, landing pages, pre-sale commerce, multi-location management, design studio, and automated commerce solutions.</p>
              </div>
              <div class="mg-sub-toggle" aria-label="Billing cycle"><span>Monthly</span><span>Yearly (Save 20%)</span></div>
            </div>

            <div class="mg-sub-plans">
              <?php foreach ($plans as $plan): ?>
                <?php
                  $planId = (string)($plan['id'] ?? '');
                  $isCurrent = $planId === $currentPackageId;
                  $isFeatured = !empty($plan['featured']);
                  $isEnterprise = $planId === 'enterprise';
                  $actionLabel = $isCurrent ? 'Current Plan' : ($isEnterprise ? 'Contact Sales' : 'Upgrade Plan');
                  $actionHref = $isEnterprise ? '/learn-more.php?plan=enterprise' : (string)($plan['cta_href'] ?? '/pricing.php');
                ?>
                <article class="mg-sub-plan-card<?= $isFeatured ? ' is-featured' : '' ?>" data-package-id="<?= mg_e($planId) ?>">
                  <?php if ($isFeatured): ?><div class="mg-sub-ribbon">Most Popular</div><?php endif; ?>
                  <div class="mg-sub-plan-inner">
                    <h4><?= mg_e((string)($plan['name'] ?? 'Plan')) ?></h4>
                    <p class="mg-sub-plan-desc"><?= mg_e((string)($plan['description'] ?? '')) ?></p>
                    <div class="mg-sub-price"><strong><?= mg_e((string)($plan['price_label'] ?? '$0')) ?></strong><span><?= mg_e((string)($plan['billing_label'] ?? '/mo')) ?></span></div>
                    <div class="mg-sub-billed">Monthly billing</div>
                    <a class="mg-sub-action<?= $isCurrent ? ' is-current' : ($isFeatured ? ' is-primary' : '') ?>" href="<?= mg_e($isCurrent ? '#' : $actionHref) ?>"><?= mg_e($actionLabel) ?></a>
                    <ul class="mg-sub-features">
                      <?php foreach (($plan['included_features'] ?? []) as $feature): ?><li><?= mg_e((string)$feature) ?></li><?php endforeach; ?>
                      <?php foreach (($plan['excluded_features'] ?? []) as $feature): ?><li class="is-muted"><?= mg_e((string)$feature) ?></li><?php endforeach; ?>
                    </ul>
                    <?php if (!empty($plan['fit'])): ?><p class="mg-sub-fit"><?= mg_e((string)$plan['fit']) ?></p><?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="mg-sub-bottom" aria-label="Upgrade benefits">
            <div class="mg-sub-why">
              <h3>Why upgrade?</h3>
              <div class="mg-sub-reasons">
                <div class="mg-sub-reason"><div class="mg-sub-reason-icon">▣</div><div><strong>Increase Revenue</strong><span>Turn promotions into measurable tracked revenue.</span></div></div>
                <div class="mg-sub-reason"><div class="mg-sub-reason-icon">♙</div><div><strong>Engage Customers</strong><span>Drive loyalty and repeat business.</span></div></div>
                <div class="mg-sub-reason"><div class="mg-sub-reason-icon">⌘</div><div><strong>Scale Operations</strong><span>Manage multiple locations and campaigns.</span></div></div>
                <div class="mg-sub-reason"><div class="mg-sub-reason-icon">◴</div><div><strong>Save Time</strong><span>Automate tasks and streamline workflows.</span></div></div>
              </div>
            </div>
            <aside class="mg-sub-custom">
              <div class="mg-sub-custom-icon">✦</div>
              <div><h3>Need a custom solution?</h3><p>Let’s build a plan that fits your business perfectly.</p><a href="/learn-more.php">Book a demo</a></div>
            </aside>
          </section>
        </div>
      </section>
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