<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Agent Offer | Microgifter';
$page_section = 'campaign';
$header_mode = 'public';
$page_styles = ['/assets/css/public-campaign-pages.css'];
$page_scripts = ['/assets/js/public-campaign.js'];
$mgCampaignExpectedType = 'agent_offer';
$mgCampaignPageLabel = 'Agent offer';
$mgCampaignPageIntro = 'Register interest in a merchant agent-discoverable offer powered by Microgifter.';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/public-campaign-page.php';
require __DIR__ . '/includes/footer.php';
