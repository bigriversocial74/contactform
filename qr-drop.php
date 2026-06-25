<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Reward Drop | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/public-campaign-pages.css'];
$page_scripts = ['/assets/js/public-campaign.js'];
$mgCampaignExpectedType = 'qr_reward_drop';
$mgCampaignPageLabel = 'Reward drop';
$mgCampaignPageIntro = 'Join this merchant reward drop through Microgifter.';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/public-campaign-page.php';
require __DIR__ . '/includes/footer.php';
