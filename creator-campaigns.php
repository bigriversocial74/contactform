<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$user = mg_require_auth('/signin.php', '/creator-campaigns.php');
$page_title = 'Creator Campaigns | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$accountView = 'creator-campaigns';
$page_body_class = 'mg-creator-campaign-workspace-page mg-creator-campaign-ui-v11';
$page_styles = [
    '/assets/css/creator-campaign-participation.css?v=3.0.1',
    '/assets/css/creator-campaign-ui-v11.css?v=11.0.0',
    '/assets/css/creator-campaign-ui-v11-components.css?v=11.0.0',
];
$page_scripts = ['/assets/js/account-sidebar.js','/assets/js/creator-campaign-participation.js?v=3.0.1'];
$page_manifest = [
    'id' => 'creator-campaigns',
    'title' => $page_title,
    'section' => $page_section,
    'header_mode' => $header_mode,
    'styles' => $page_styles,
    'scripts' => $page_scripts,
    'body_class' => $page_body_class,
    'onboarding' => ['enabled' => false, 'page' => 'creator-campaigns', 'sections' => []],
];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-account-page mg-creator-campaign-account-page">
  <div class="mg-account-layout">
    <?php require __DIR__ . '/includes/account-sidebar.php'; ?>
    <section class="mg-account-shell mg-creator-campaign-account-shell">
      <?php require __DIR__ . '/includes/creator-campaigns-participation-view.php'; ?>
    </section>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>