<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Creator Campaigns | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = [
    '/assets/css/merchant-workspace.css',
    '/assets/css/merchant-creator-campaigns.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/merchant-workspace.js',
    '/assets/js/merchant-creator-campaigns.js?v=1.0.0',
];
$merchantView = 'creator_campaigns';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';
