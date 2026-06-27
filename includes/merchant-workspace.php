<?php
declare(strict_types=1);
$merchantView = $merchantView ?? 'overview';
$merchantNav = [
 'overview'=>['Overview','Workspace health','/merchant.php','Overview'],
 'notifications'=>['Notifications','Tips, voucher messages, alerts','/merchant-notifications.php','Overview'],
 'onboarding'=>['Onboarding','Activation steps','/merchant-onboarding.php','Overview'],
 'products'=>['Products','Catalog and builder','/merchant-products.php','Commerce'],
 'reward_templates'=>['Reward Templates','Wallet-ready offers','/merchant-reward-templates.php','Commerce'],
 'campaigns'=>['Campaigns','Forms, contests, QR drops','/merchant-campaigns.php','Engage'],
 'merchant_crm'=>['Merchant CRM','Customers and campaign history','/merchant-crm.php','Engage'],
 'campaign_stamps'=>['Campaign Stamps','Distribution usage','/merchant-campaign-stamps.php','Engage'],
 'stamps'=>['Stamp Ledger','Sends and balance','/merchant-stamps.php','Finance'],
 'storefront'=>['Storefront','Public merchant page','/merchant-storefront.php','Presence'],
 'pppm'=>['Orders and PPPM','Items and lifecycle','/merchant-pppm.php','Commerce'],
 'distribution'=>['Distribution','Programs and inputs','/merchant-distribution.php','Engage'],
 'developer_api'=>['Developer API','Apps and access','/merchant-distribution.php?developer_api=1','Build'],
 'claims'=>['Claims','Verification and redemption','/merchant-claims.php','Commerce'],
 'media'=>['Media','Assets and processing','/merchant-media.php','Presence'],
 'intelligence'=>['Intelligence','Forecasts and analytics','/merchant-intelligence.php','Insights'],
 'locations'=>['Locations','Stores and claim scope','/merchant-locations.php','Manage'],
 'team'=>['Team','Roles and access','/merchant-team.php','Manage'],
 'payments'=>['Payments','Checkout and reconciliation','/merchant-payments.php','Finance'],
 'settings'=>['Settings','Business configuration','/merchant-settings.php','Manage'],
];
$user = mg_current_user();
$appSidebarNav = [];
foreach ($merchantNav as $key => $item) {
    $appSidebarNav[$key] = [
        'section' => $item[3] ?? '',
        'label' => $item[0],
        'detail' => $item[1],
        'href' => $item[2],
        'visible' => true,
        'active' => $merchantView === $key,
    ];
}
$appSidebarBeforeNav = '';
$appSidebarAfterNav = '';
$appSidebarFooter = '';
$appSidebarVariant = 'merchant';
$appSidebarLabel = 'Merchant';
$appSidebarActive = $merchantView;
$appSidebarCompact = true;
?>
<section class="mg-app-shell mg-merchant-app" data-merchant-app data-merchant-view="<?= mg_e($merchantView) ?>" data-sidebar-contract="mg-app-sidebar">
  <?php require __DIR__ . '/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
   <?php if(!$user): ?><section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Merchant access</h2><p>Sign in to open your merchant workspace.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div></section>
   <?php else: require __DIR__ . '/merchant-view.php'; endif; ?>
  </main>
</section>
