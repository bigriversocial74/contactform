<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-permission-matrix.php';
require_once dirname(__DIR__) . '/includes/hosted-game-releases.php';
$page_title='Hosted Game Releases | Microgifter Admin';$page_section='account';$header_mode='account';
$page_styles=['/assets/css/admin-dashboard.css','/assets/css/hosted-game-releases.css?v=1.0.0'];$page_scripts=['/assets/js/hosted-game-releases.js?v=1.0.0'];
$user=mg_current_user();
$hasAccess=is_array($user)&&(mg_admin_permission_user_has($user,'admin.hosted_games.view')||mg_admin_permission_user_has($user,'admin.hosted_games.manage')||mg_admin_permission_user_has($user,'admin.hosted_games.releases.manage')||mg_admin_permission_user_has($user,'admin.settings.manage'));
$canManage=is_array($user)&&(mg_admin_permission_user_has($user,'admin.hosted_games.releases.manage')||mg_admin_permission_user_has($user,'admin.hosted_games.manage')||mg_admin_permission_user_has($user,'admin.settings.manage'));
$adminActive='hosted-games';$gamePublicId=trim((string)($_GET['game']??''));$hgrGame=null;
if($hasAccess&&$gamePublicId!==''){try{$hgrGame=mg_hosted_game_by_public_id(mg_db(),$gamePublicId,false);}catch(Throwable){$hgrGame=null;}}
$hgrScope='admin';$hgrCanManage=$canManage;$hgrApi='/api/admin/hosted-game-releases.php';$hgrUploadApi='/api/admin/hosted-game-upload.php';$hgrDownloadApi='/api/admin/hosted-game-release-download.php';$hgrBack='/admin/hosted-games.php';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app">
<?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
<main class="mg-app-workspace">
<?php if(!$user): ?><section class="mg-app-panel"><div class="mg-app-panel-body"><h2>Admin access</h2><p>Sign in to manage hosted game releases.</p><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div></section>
<?php elseif(!$hasAccess): ?><section class="mg-app-panel"><div class="mg-app-panel-body"><h2>Access unavailable</h2><p>This account does not have Hosted Games release permission.</p></div></section>
<?php else: require dirname(__DIR__) . '/includes/hosted-game-releases-view.php'; endif; ?>
</main></section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
