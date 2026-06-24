<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Campaign | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/public-campaign-pages.css'];
$page_scripts = ['/assets/js/public-campaign.js'];
$mgCampaignExpectedType = null;
$mgCampaignPageLabel = 'Microgifter campaign';
$mgCampaignPageIntro = 'Open a merchant campaign link or scan a QR code to claim, join, or enter a Microgifter reward campaign.';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/public-campaign-page.php';
require __DIR__ . '/includes/footer.php';
