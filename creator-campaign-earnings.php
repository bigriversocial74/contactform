<?php
declare(strict_types=1);
require_once __DIR__.'/includes/app.php';
$page_title='Campaign Earnings | Microgifter';$page_section='account';$header_mode='account';$page_body_class='mg-creator-earnings-page';$page_styles=['/assets/css/creator-campaign-compensation.css?v=6.0.0'];$page_scripts=['/assets/js/creator-campaign-earnings.js?v=6.0.0'];require __DIR__.'/includes/header.php';$user=mg_current_user();
?>
<main class="mg-cce-page"><?php if(!$user):?><section class="mg-cce-panel"><a class="mg-btn mg-btn-primary" href="/signin.php">Sign in</a></section><?php else:?><?php require __DIR__.'/includes/creator-campaign-earnings-view.php';?><?php endif;?></main>
<?php require __DIR__.'/includes/footer.php';?>
