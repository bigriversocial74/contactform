<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Community Support | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_body_class = 'mg-community-support-page';
$page_styles = [
    '/assets/css/merchant-workspace.css',
    '/assets/css/merchant-community-support.css?v=1.0.0',
];
$page_scripts = ['/assets/js/merchant-community-support.js?v=1.0.0'];
$merchantView = 'community_support';
require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/merchant-navigation.php';
$user = mg_current_user();
$mg_package_context = is_array($mg_package_context ?? null) ? $mg_package_context : mg_user_package_context(null, $user);
$canMerchantAccess = (bool)($can_merchant_nav ?? !empty($mg_package_context['merchant_access']));
$canCommunitySupport = $canMerchantAccess && mg_merchant_navigation_public_donations_visible();
$appSidebarNav = $canMerchantAccess
    ? mg_merchant_navigation_sidebar($merchantView)
    : ['subscriptions' => ['section' => 'Merchant', 'label' => 'Upgrade', 'detail' => 'Unlock merchant tools', 'href' => '/pricing.php', 'visible' => true]];
$appSidebarBeforeNav = '';
$appSidebarAfterNav = '';
$appSidebarFooter = '';
$appSidebarVariant = $canMerchantAccess ? 'merchant' : 'utility';
$appSidebarLabel = $canMerchantAccess ? 'Merchant' : 'Workspace';
$appSidebarActive = $canMerchantAccess ? mg_merchant_navigation_active_key($merchantView) : 'subscriptions';
$appSidebarCompact = true;
?>
<section class="mg-app-shell mg-merchant-app" data-merchant-app data-merchant-view="community_support">
  <?php require __DIR__ . '/includes/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
    <?php if (!$user): ?>
      <section class="mg-app-panel"><div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div></section>
    <?php elseif (!$canMerchantAccess): ?>
      <section class="mg-app-panel"><div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/pricing.php">View packages</a></div></section>
    <?php elseif (!$canCommunitySupport): ?>
      <section class="mg-app-panel">
        <div class="mg-app-panel-body">
          <span class="mg-kicker">Public Donations</span>
          <h1>Community Support is not available</h1>
          <p>This workspace is outside the current rollout or your team role does not include Public Donations reporting.</p>
          <a class="mg-btn" href="/merchant-campaigns.php">Back to Campaigns</a>
        </div>
      </section>
    <?php else: ?>
      <?php require __DIR__ . '/includes/merchant-community-support-view.php'; ?>
    <?php endif; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
