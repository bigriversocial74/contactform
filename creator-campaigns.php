<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Creator Campaigns | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$page_body_class = 'mg-creator-campaign-workspace-page';
$page_styles = ['/assets/css/creator-campaign-participation.css?v=3.0.0'];
$page_scripts = ['/assets/js/creator-campaign-participation.js?v=3.0.0'];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/creator-campaigns-participation-view.php';
require __DIR__ . '/includes/footer.php';
