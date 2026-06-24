<?php
declare(strict_types=1);
$merchantView = $merchantView ?? 'overview';
$merchantNav = [
 'overview'=>['Overview','Workspace health','/merchant.php'],
 'onboarding'=>['Onboarding','Activation steps','/merchant-onboarding.php'],
 'products'=>['Products','Catalog and builder','/merchant-products.php'],
 'reward_templates'=>['Reward Templates','Wallet-ready offers','/merchant-reward-templates.php'],
 'campaigns'=>['Campaigns','Forms, contests, QR drops','/merchant-campaigns.php'],
 'campaign_stamps'=>['Campaign Stamps','Distribution usage','/merchant-campaign-stamps.php'],
 'stamps'=>['Stamp Ledger','Sends and balance','/merchant-stamps.php'],
 'storefront'=>['Storefront','Public merchant page','/merchant-storefront.php'],
 'pppm'=>['Orders & PPPM','Items and lifecycle','/merchant-pppm.php'],
 'distribution'=>['Distribution','Programs and inputs','/merchant-distribution.php'],
 'developer_api'=>['Developer API','Apps and access','/merchant-distribution.php?developer_api=1'],
 'claims'=>['Claims','Verification and redemption','/merchant-claims.php'],
 'media'=>['Media','Assets and processing','/merchant-media.php'],
 'intelligence'=>['Intelligence','Forecasts and analytics','/merchant-intelligence.php'],
 'locations'=>['Locations','Stores and claim scope','/merchant-locations.php'],
 'team'=>['Team','Roles and access','/merchant-team.php'],
 'payments'=>['Payments','Checkout and reconciliation','/merchant-payments.php'],
 'settings'=>['Settings','Business configuration','/merchant-settings.php'],
];
$user = mg_current_user();
$appSidebarNav = [];
foreach ($merchantNav as $key => $item) {
    $appSidebarNav[$key] = [
        'label' => $item[0],
        'detail' => $item[1],
        'href' => $item[2],
        'visible' => true,
        'active' => $merchantView === $key,
    ];
}
ob_start(); ?>
<div class="mg-merchant-progress-card"><div><span>Workspace setup</span><strong data-merchant-progress>0%</strong></div><div class="mg-merchant-progress"><i data-merchant-progress-bar></i></div><small data-merchant-status>Loading activation status…</small></div>
<?php $appSidebarBeforeNav = ob_get_clean();
$appSidebarFooter = '<div class="mg-merchant-sidebar-footer"><span class="mg-save-state" data-merchant-save-state>All changes saved</span><a class="mg-btn mg-btn-soft" href="/merchant-campaigns.php">Create campaign</a></div>';
$appSidebarVariant = 'merchant';
$appSidebarLabel = 'Merchant';
$appSidebarActive = $merchantView;
?>
<section class="mg-app-shell mg-merchant-app" data-merchant-app data-merchant-view="<?= mg_e($merchantView) ?>" data-sidebar-contract="mg-app-sidebar">
  <?php require __DIR__ . '/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
   <?php if(!$user): ?><section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Merchant access</h2><p>Sign in to open your merchant workspace.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div></section>
   <?php else: require __DIR__ . '/merchant-view.php'; endif; ?>
  </main>
</section>