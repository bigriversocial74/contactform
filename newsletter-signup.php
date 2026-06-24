<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Newsletter Signup | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/public-campaign-pages.css'];
$page_scripts = ['/assets/js/public-campaign.js'];
$mgCampaignExpectedType = 'newsletter_signup';
$mgCampaignPageLabel = 'Newsletter signup';
$mgCampaignPageIntro = 'Join a merchant newsletter campaign and claim the attached Microgifter reward when available.';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/public-campaign-page.php';
require __DIR__ . '/includes/footer.php';
