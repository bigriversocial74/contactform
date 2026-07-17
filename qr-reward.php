<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/campaign-landing-foundation.php';

$page_title = 'QR Reward | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = [
    '/assets/css/watch-listen-standalone-page.css',
    '/assets/css/campaign-landing-foundation.css',
    '/assets/css/public-campaign-rl-landing-v1.css',
    '/assets/css/public-campaign-compact-layout-v2.css?v=1.0.0',
];
$page_scripts = ['/assets/js/public-campaign.js'];
$mgCampaignExpectedType = 'qr_reward_drop';
$mgCampaignPageLabel = 'QR reward';
$mgCampaignPageIntro = 'Scan, claim, and redeem a local reward powered by Microgifter.';

$mgCampaignBootstrap = mg_campaign_landing_bootstrap($mgCampaignExpectedType, $page_title);
$mgCampaign = $mgCampaignBootstrap['campaign'];
$mgCampaignRef = (string)$mgCampaignBootstrap['campaign_ref'];
$mgCampaignToken = (string)$mgCampaignBootstrap['campaign_token'];
$mgCampaignPreviewMode = (bool)$mgCampaignBootstrap['preview'];
$mgCampaignLoadAttempted = true;
$page_title = (string)$mgCampaignBootstrap['page_title'];
$page_meta = is_array($mgCampaignBootstrap['page_meta']) ? $mgCampaignBootstrap['page_meta'] : [];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/public-campaign-page.php';
require __DIR__ . '/includes/footer.php';