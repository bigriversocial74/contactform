<?php
declare(strict_types=1);
require_once __DIR__.'/includes/app.php';
$user=mg_require_auth('/signin.php','/creator-campaign-tracking.php');
$page_title='Campaign Tracking | Microgifter';$page_section='account';$header_mode='account';$accountView='creator-campaigns';
$page_body_class='mg-creator-tracking-account-page';$page_styles=['/assets/css/creator-campaign-participation.css?v=3.0.1','/assets/css/creator-campaign-tracking.css?v=5.0.0'];
$page_scripts=['/assets/js/account-sidebar.js','/assets/js/creator-campaign-tracking.js?v=5.0.0'];
require __DIR__.'/includes/header.php';
?>
<section class="mg-account-page"><div class="mg-account-layout"><?php require __DIR__.'/includes/account-sidebar.php';?><section class="mg-account-shell"><?php require __DIR__.'/includes/creator-campaign-tracking-view.php';?></section></div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
