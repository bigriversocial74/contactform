<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Creator Campaigns | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_body_class = 'mg-creator-campaigns-page';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/merchant-creator-campaigns.css?v=2.0.0'];
$page_scripts = ['/assets/js/merchant-creator-campaigns.js?v=2.0.0','/assets/js/merchant-creator-campaign-builder.js?v=2.0.0'];
$merchantView = 'creator_campaigns';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';
