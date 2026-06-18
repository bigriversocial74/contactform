<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title='System Health | Microgifter';
$page_section='account';
$header_mode='account';
$page_styles=['/assets/css/admin-dashboard.css','/assets/css/admin-system-health.css'];
$page_scripts=['/assets/js/admin-system-health.js'];
$page_body_class='mg-system-health-page';
require __DIR__ . '/includes/header.php';

$roles=is_array($user['roles']??null)?$user['roles']:[];
$permissions=is_array($user['permissions']??null)?$user['permissions']:[];
$canView=in_array('super_admin',$roles,true)
    ||in_array('admin.health.view',$permissions,true)
    ||in_array('operations.readiness.view',$permissions,true);
?>
<section class="mg-system-health-shell">
  <nav class="mg-system-health-breadcrumb" aria-label="Admin navigation"><a href="/account-admin.php">Admin dashboard</a><span>/</span><strong>System health</strong></nav>
  <?php if($canView): ?>
    <?php require __DIR__ . '/includes/account/system-health.php'; ?>
  <?php else: ?>
    <section class="mg-app-panel"><div class="mg-app-panel-head"><div><h2>System health access is not active.</h2><p>This account does not have permission to inspect platform health.</p></div></div><div class="mg-app-panel-body"><a class="mg-btn mg-btn-ghost" href="/account.php">Back to account</a></div></section>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
