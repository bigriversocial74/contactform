<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Referral Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/public-campaign-pages.css'];
$page_scripts = ['/assets/js/public-campaign.js'];
$mgCampaignExpectedType = 'referral_reward';
$mgCampaignPageLabel = 'Referral reward';
$mgCampaignPageIntro = 'Share a local reward campaign and help friends discover merchants on Microgifter.';
// Public campaign wallet result details are styled by assets/css/public-campaign-pages.css.
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/public-campaign-page.php';
require __DIR__ . '/includes/footer.php';