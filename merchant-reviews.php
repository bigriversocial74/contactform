<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Merchant Reviews | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$merchantView = 'reviews';
$page_styles = [
    '/assets/css/merchant-workspace.css',
    '/assets/css/reviews-case-studies-management.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/merchant-workspace.js',
    '/assets/js/merchant-reviews.js?v=1.0.0',
];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';
