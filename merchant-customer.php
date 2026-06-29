<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title='Customer Profile | Microgifter';
$page_section='merchant';
$header_mode='account';
$page_styles=['/assets/css/merchant-workspace.css','/assets/css/merchant-customer-profile.css','/assets/css/merchant-followup-tasks.css','/assets/css/merchant-crm-retention-playbooks.css','/assets/css/merchant-customer-agent-timeline.css'];
$page_scripts=['/assets/js/merchant-customer-profile.js','/assets/js/merchant-customer-profile-fallback.js','/assets/js/merchant-customer-profile-actions-fix.js','/assets/js/merchant-customer-retention-recommendations.js','/assets/js/merchant-customer-agent-timeline.js','/assets/js/merchant-customer-profile-timeout.js','/assets/js/store-health-completion-events.js'];
$user=mg_current_user();
$merchantNav=[
 'overview'=>['Overview','Workspace health','/merchant.php','Overview'],
 'notifications'=>['Notifications','Tips, voucher messages, alerts','/merchant-notifications.php','Overview'],
 'campaigns'=>['Campaigns','Forms, contests, QR drops','/merchant-campaigns.php','Engage'],
 'merchant_crm'=>['Merchant CRM','Customers and campaign history','/merchant-crm.php','Engage'],
 'customer_profile'=>['Customer Profile','Expanded CRM profile','/merchant-customer.php','Engage'],
 'followups'=>['Follow-ups','Customer task queue','/merchant-followups.php','Engage'],
 'claims'=>['Claims','Verification and redemption','/merchant-claims.php','Commerce'],
 'stamps'=>['Stamp Ledger','Sends and balance','/merchant-stamps.php','Finance'],
 'locations'=>['Locations','Stores and claim scope','/merchant-locations.php','Manage'],
 'settings'=>['Settings','Business configuration','/merchant-settings.php','Manage'],
];
$appSidebarNav=[];
foreach($merchantNav as $key=>$item){$appSidebarNav[$key]=['section'=>$item[3]??'','label'=>$item[0],'detail'=>$item[1],'href'=>$item[2],'visible'=>true,'active'=>$key==='customer_profile'];}
$appSidebarVariant='merchant';
$appSidebarLabel='Merchant';
$appSidebarActive='customer_profile';
$appSidebarCompact=true;
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-merchant-app mg-customer-profile-app" data-merchant-app data-merchant-view="customer_profile" data-sidebar-contract="mg-app-sidebar">
  <?php require __DIR__ . '/includes/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main mg-customer-profile-main">
    <?php if(!$user): ?>
      <section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>Merchant access</h2><p>Sign in to open this customer profile.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div></section>
    <?php else: require __DIR__ . '/includes/merchant-customer-profile-view.php'; endif; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>