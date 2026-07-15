<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/merchant-navigation.php';
require_once __DIR__ . '/includes/hosted-game-releases.php';
$page_title='Game Releases | Microgifter';$page_section='merchant';$header_mode='account';
$page_styles=['/assets/css/merchant-workspace.css','/assets/css/hosted-game-releases.css?v=1.0.0'];
$page_scripts=['/assets/js/hosted-game-releases.js?v=1.0.0'];
$user=mg_current_user();$merchantView='hosted_games';$mg_package_context=mg_user_package_context(null,$user);$canMerchantAccess=!empty($mg_package_context['merchant_access']);
$appSidebarNav=$canMerchantAccess?mg_merchant_navigation_sidebar($merchantView):['inbox'=>['section'=>'Workspace','label'=>'Inbox','detail'=>'Gift inbox','href'=>'/inbox.php','visible'=>true],'subscriptions'=>['section'=>'Merchant','label'=>'Upgrade','detail'=>'Unlock merchant tools','href'=>'/pricing.php','visible'=>true]];
$appSidebarVariant=$canMerchantAccess?'merchant':'utility';$appSidebarLabel=$canMerchantAccess?'Merchant':'Workspace';$appSidebarActive=$canMerchantAccess?mg_merchant_navigation_active_key($merchantView):'subscriptions';$appSidebarCompact=true;
$gamePublicId=trim((string)($_GET['game']??''));$hgrGame=null;
if($user&&$canMerchantAccess&&$gamePublicId!==''){try{$hgrGame=mg_hosted_game_for_merchant(mg_db(),(int)$user['id'],$gamePublicId,false);}catch(Throwable){$hgrGame=null;}}
$hgrScope='merchant';$hgrCanManage=$user&&$canMerchantAccess;$hgrApi='/api/merchant/hosted-game-releases.php';$hgrUploadApi='/api/merchant/hosted-game-upload.php';$hgrDownloadApi='/api/merchant/hosted-game-release-download.php';$hgrBack='/merchant-games.php';
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-merchant-app" data-merchant-app data-merchant-view="hosted_games" data-sidebar-contract="mg-app-sidebar">
<?php require __DIR__ . '/includes/app-sidebar.php'; ?>
<main class="mg-app-workspace mg-merchant-main">
<?php if(!$user): ?><section class="mg-app-panel"><div class="mg-app-panel-body"><h2>Merchant access</h2><p>Sign in to manage game releases.</p><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div></section>
<?php elseif(!$canMerchantAccess): ?><section class="mg-app-panel"><div class="mg-app-panel-body"><h2>Merchant workspace is not active.</h2><p>Upgrade your account to manage Hosted Games releases.</p><a class="mg-btn mg-btn-primary" href="/pricing.php">View packages</a></div></section>
<?php else: require __DIR__ . '/includes/hosted-game-releases-view.php'; endif; ?>
</main></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
