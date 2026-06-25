<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Contest Entry | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/public-campaign-pages.css'];
$page_scripts = ['/assets/js/public-campaign.js'];
$mgCampaignExpectedType = 'contest_giveaway';
$mgCampaignPageLabel = 'Contest entry';
$mgCampaignPageIntro = 'Enter a merchant contest and join the related Microgifter campaign.';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/public-campaign-page.php';
require __DIR__ . '/includes/footer.php';
