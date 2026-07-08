<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Merchant Campaigns | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/merchant-campaigns.css','/assets/css/stage12-campaign-embed-tools.css'];
$page_scripts = ['/assets/js/merchant-workspace.js','/assets/js/stage12-customer-refund-campaign-type.js','/assets/js/stage12-campaign-embed-tools.js','/assets/js/campaign-embed-analytics-links.js'];
$merchantView = 'campaigns';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';
