<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$user=mg_require_auth('/signin.php','/creator-campaign-deliverables.php');$page_title='My Creator Deliverables | Microgifter';$page_section='account';$header_mode='account';$accountView='creator-campaigns';$page_body_class='mg-creator-deliverables-account-page';$page_styles=['/assets/css/creator-campaign-deliverables.css?v=4.0.0'];$page_scripts=['/assets/js/account-sidebar.js','/assets/js/creator-campaign-deliverables.js?v=4.0.0'];$page_manifest=['id'=>'creator-campaign-deliverables','title'=>$page_title,'section'=>$page_section,'header_mode'=>$header_mode,'styles'=>$page_styles,'scripts'=>$page_scripts,'body_class'=>$page_body_class,'onboarding'=>['enabled'=>false,'page'=>'creator-campaign-deliverables','sections'=>[]]];require __DIR__.'/includes/header.php';
?>
<section class="mg-account-page"><div class="mg-account-layout"><?php require __DIR__.'/includes/account-sidebar.php';?><section class="mg-account-shell"><?php require __DIR__.'/includes/creator-campaign-deliverables-view.php';?></section></div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
