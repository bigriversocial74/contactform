<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Creator Participation | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_body_class = 'mg-creator-participation-page';
$page_styles = [
    '/assets/css/merchant-workspace.css',
    '/assets/css/merchant-creator-campaigns.css?v=2.0.0',
    '/assets/css/creator-campaign-participation.css?v=3.0.1',
];
$page_scripts = ['/assets/js/merchant-creator-campaign-participation.js?v=3.0.1'];
$merchantView = 'creator_campaigns';
require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/merchant-navigation.php';

$user = mg_current_user();
$mg_package_context = is_array($mg_package_context ?? null)
    ? $mg_package_context
    : mg_user_package_context(null, $user);
$canMerchantAccess = (bool) ($can_merchant_nav ?? !empty($mg_package_context['merchant_access']));

if ($canMerchantAccess) {
    $appSidebarNav = mg_merchant_navigation_sidebar($merchantView);
} else {
    $appSidebarNav = [
        'inbox' => ['section' => 'Workspace', 'label' => 'Inbox', 'detail' => 'Gift inbox', 'href' => '/inbox.php', 'visible' => true],
        'sent' => ['label' => 'Sent', 'detail' => 'Outbound gifts', 'href' => '/sent.php', 'visible' => true],
        'claimed' => ['label' => 'Claimed', 'detail' => 'Redeemed gifts', 'href' => '/claimed.php', 'visible' => true],
        'subscriptions' => ['section' => 'Merchant', 'label' => 'Upgrade', 'detail' => 'Unlock merchant tools', 'href' => '/pricing.php', 'visible' => true],
    ];
}
$appSidebarBeforeNav = '';
$appSidebarAfterNav = '';
$appSidebarFooter = '';
$appSidebarVariant = $canMerchantAccess ? 'merchant' : 'utility';
$appSidebarLabel = $canMerchantAccess ? 'Merchant' : 'Workspace';
$appSidebarActive = $canMerchantAccess ? mg_merchant_navigation_active_key($merchantView) : 'subscriptions';
$appSidebarCompact = true;
?>
<?php if ($canMerchantAccess): ?>
<link rel="stylesheet" href="/assets/css/merchant-module-limits.css">
<?php endif; ?>
<section class="mg-app-shell mg-merchant-app" data-merchant-app data-merchant-view="creator_campaigns" data-sidebar-contract="mg-app-sidebar" data-merchant-access="<?= $canMerchantAccess ? 'true' : 'false' ?>">
  <?php require __DIR__ . '/includes/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
    <?php if (!$user): ?>
      <section class="mg-app-panel">
        <div class="mg-app-panel-head"><div><h2>Merchant access</h2><p>Sign in to open your merchant workspace.</p></div></div>
        <div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div>
      </section>
    <?php elseif (!$canMerchantAccess): ?>
      <section class="mg-app-panel">
        <div class="mg-app-panel-head"><div><span class="mg-eyebrow">Package access</span><h2>Merchant workspace is not active.</h2><p>Upgrade to unlock merchant creator campaigns, participation, applications, invitations, agreements, and participant management.</p></div></div>
        <div class="mg-app-panel-body"><div class="mg-action-row"><a class="mg-btn mg-btn-primary" href="/pricing.php">View packages</a><a class="mg-btn mg-btn-ghost" href="/account-subscriptions.php">My subscription</a><a class="mg-btn mg-btn-soft" href="/inbox.php">Back to inbox</a></div></div>
      </section>
    <?php else: ?>
      <?php require __DIR__ . '/includes/merchant-creator-campaign-participation-view.php'; ?>
    <?php endif; ?>
  </main>
</section>
<?php if ($canMerchantAccess): ?>
<script src="/assets/js/merchant-module-limits.js" defer></script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
