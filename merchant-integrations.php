<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
$page_title = 'Connected Apps | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = [
    '/assets/css/merchant-workspace.css',
    '/assets/css/merchant-integrations.css?v=1.0.0',
    '/assets/css/merchant-integrations-woocommerce.css?v=1.0.0',
    '/assets/css/merchant-integrations-shopify.css?v=1.0.0',
    '/assets/css/merchant-integrations-square.css?v=1.0.0',
    '/assets/css/merchant-integrations-hubspot.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/merchant-workspace.js',
    '/assets/js/merchant-integrations.js?v=1.0.0',
    '/assets/js/merchant-integrations-woocommerce.js?v=1.0.0',
    '/assets/js/merchant-integrations-shopify.js?v=1.0.0',
    '/assets/js/merchant-integrations-square.js?v=1.0.0',
    '/assets/js/merchant-integrations-hubspot.js?v=1.0.0',
];
$merchantView = 'integrations';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';
