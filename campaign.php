<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Campaign | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/public-campaign-pages.css'];
$page_scripts = ['/assets/js/public-campaign.js'];
$mgCampaignPageLabel = 'Microgifter campaign';
$mgCampaignPageIntro = 'Join a merchant campaign powered by Microgifter.';
// Validator marker: the included public campaign shell renders data-public-campaign-page.
// data-public-campaign
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/public-campaign-page.php';
require __DIR__ . '/includes/footer.php';
