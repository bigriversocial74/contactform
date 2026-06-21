<?php
declare(strict_types=1);
require_once __DIR__.'/includes/app.php';
$page_title='Corporate Gifting | Microgifter';
$page_section='public';
$header_mode='public';
$page_styles=['/assets/css/public-header-footer-fixes.css','/assets/css/public-program-pages.css'];
$page_manifest=[
  'id'=>'corporate',
  'title'=>$page_title,
  'section'=>$page_section,
  'header_mode'=>$header_mode,
  'styles'=>$page_styles,
  'public_header'=>['links'=>[
    ['label'=>'Corporate Gifting','href'=>'/corporate.php'],
    ['label'=>'Retail Subscriptions','href'=>'/retail.php'],
    ['label'=>'Locations','href'=>'/locations.php'],
    ['label'=>'Book A Demo','href'=>'/learn-more.php'],
  ]],
];
require __DIR__.'/includes/header.php';
?>
<section class="mg-program-page"><div class="mg-program-page__inner"><span class="mg-program-page__eyebrow">Corporate gifting</span><h1>Local gifts built for teams and relationships.</h1><p>Plan employee rewards, client appreciation, and recurring gifting programs through one guided local-gifting platform.</p><div class="mg-program-page__actions"><a href="/signup.php">Create an account</a><a href="/learn-more.php">Book a demo</a></div></div></section>
<?php require __DIR__.'/includes/footer.php'; ?>
