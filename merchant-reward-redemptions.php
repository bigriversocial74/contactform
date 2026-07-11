<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Reward Redemptions | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/merchant-wallet-redemptions.css'];
$page_scripts = ['/assets/js/merchant-wallet-redemptions.js'];
require __DIR__ . '/includes/header.php';
$appSidebarNav = [
  'overview'=>['section'=>'Merchant','label'=>'Overview','detail'=>'Merchant dashboard','href'=>'/merchant.php','visible'=>true],
  'wallet_redemptions'=>['label'=>'Reward Redemptions','detail'=>'Redeem customer codes','href'=>'/merchant-reward-redemptions.php','visible'=>true,'active'=>true],
  'claims'=>['label'=>'Claims','detail'=>'Claim operations','href'=>'/merchant-claims.php','visible'=>true],
  'loyalty_quests'=>['label'=>'Loyalty Quests','detail'=>'Quest campaigns','href'=>'/merchant-loyalty-quests.php','visible'=>true],
  'crm'=>['label'=>'Merchant CRM','detail'=>'Customer history','href'=>'/merchant-crm.php','visible'=>true],
];
$appSidebarVariant='merchant';$appSidebarLabel='Merchant';$appSidebarActive='wallet_redemptions';$appSidebarCompact=true;$appSidebarBeforeNav='';$appSidebarAfterNav='';$appSidebarFooter='';
?>
<section class="mg-app-shell mg-merchant-app" data-merchant-app data-merchant-view="reward_redemptions">
  <?php require __DIR__ . '/includes/app-sidebar.php'; ?>
  <main class="mg-app-workspace mg-merchant-main">
    <?php require __DIR__ . '/includes/account/merchant-wallet-redemptions-view.php'; ?>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php';
