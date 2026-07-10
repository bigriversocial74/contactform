<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Contest | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/watch-listen-standalone-page.css', '/assets/css/public-campaign-rl-landing-v1.css'];
$page_scripts = ['/assets/js/public-campaign.js'];
$mgCampaignExpectedType = 'contest_giveaway';
$mgCampaignPageLabel = 'Contest giveaway';
$mgCampaignPageIntro = 'Enter a merchant contest or giveaway powered by Microgifter.';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/public-campaign-page.php';
require __DIR__ . '/includes/footer.php';
