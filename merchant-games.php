<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';
$page_title = 'Hosted Games | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = [
    '/assets/css/merchant-workspace.css',
    '/assets/css/hosted-games-management.css?v=1.0.0',
    '/assets/css/hosted-games-runtime-toggle.css?v=1.0.0',
    '/assets/css/hosted-games-cover-upload.css?v=1.0.0',
];
$page_scripts = [
    '/assets/js/merchant-workspace.js',
    '/assets/js/merchant-hosted-games.js?v=1.0.0',
    '/assets/js/merchant-hosted-games-program-only.js?v=1.0.0',
    '/assets/js/merchant-hosted-games-runtime-toggle.js?v=1.0.0',
    '/assets/js/hosted-games-cover-upload.js?v=1.0.0',
    '/assets/js/hosted-games-analytics-links.js?v=1.0.0',
    '/assets/js/hosted-games-release-links.js?v=1.0.0',
];
$merchantView = 'hosted_games';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';
