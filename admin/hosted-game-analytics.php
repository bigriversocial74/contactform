<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/includes/admin-permission-matrix.php';
$page_title = 'Hosted Game Analytics | Microgifter Admin';
$page_section = 'account';
$header_mode = 'account';
$page_styles = ['/assets/css/admin-dashboard.css','/assets/css/hosted-game-analytics.css?v=1.0.0'];
$page_scripts = ['/assets/js/hosted-game-analytics.js?v=1.0.0'];
$user = mg_current_user();
$hasAccess = is_array($user) && (
    mg_admin_permission_user_has($user,'admin.hosted_games.analytics.view')
    || mg_admin_permission_user_has($user,'admin.settings.manage')
);
$adminActive = 'hosted-games';
$analyticsGameId = trim((string)($_GET['game'] ?? ''));
$analyticsMode = 'admin';
$analyticsApiUrl = '/api/admin/hosted-game-analytics.php';
$analyticsExportUrl = '/api/admin/hosted-game-diagnostics-export.php';
$analyticsBackUrl = '/admin/hosted-games.php';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="mg-app-shell mg-account-app">
  <?php require dirname(__DIR__) . '/includes/admin-sidebar.php'; ?>
  <main class="mg-app-workspace">
    <?php if (!$user): ?>
      <section class="mg-app-panel"><div class="mg-app-panel-body"><h2>Admin access</h2><p>Sign in to view hosted game analytics.</p><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></div></section>
    <?php elseif (!$hasAccess): ?>
      <section class="mg-app-panel"><div class="mg-app-panel-body"><h2>Access unavailable</h2><p>This account does not have Hosted Games analytics permission.</p></div></section>
    <?php elseif ($analyticsGameId === ''): ?>
      <section class="mg-app-panel"><div class="mg-app-panel-body"><h2>Select a hosted game</h2><p>Open analytics from Hosted Games administration.</p><a class="mg-btn mg-btn-primary" href="/admin/hosted-games.php">Hosted Games</a></div></section>
    <?php else: ?>
      <?php require dirname(__DIR__) . '/includes/hosted-game-analytics-view.php'; ?>
    <?php endif; ?>
  </main>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
