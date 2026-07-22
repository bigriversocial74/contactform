<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Creator Participation | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_body_class = 'mg-creator-participation-page';
$page_styles = [
    '/assets/css/merchant-workspace.css',
    '/assets/css/merchant-creator-campaigns.css?v=2.0.0',
    '/assets/css/creator-campaign-participation.css?v=3.0.0',
];
$page_scripts = ['/assets/js/merchant-creator-campaign-participation.js?v=3.0.0'];
$merchantView = 'creator_campaigns';
$merchantCreatorParticipation = true;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';
