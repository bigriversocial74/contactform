<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Merchant Campaigns | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/merchant-campaigns.css','/assets/css/stage12-campaign-embed-tools.css','/assets/css/campaign-editor-reward-levels.css','/assets/css/predictive-campaign-studio.css','/assets/css/merchant-campaigns-cleanup.css?v=1.0.0','/assets/css/public-donations-community-assignments.css?v=1.0.0','/assets/css/public-donations-allocation.css?v=1.0.0','/assets/css/public-donations-recall.css?v=1.0.0'];
$page_scripts = ['/assets/js/merchant-workspace.js','/assets/js/stage12-customer-review.js?v=1.0.0','/assets/js/stage12-customer-refund-campaign-type.js','/assets/js/stage12-campaign-embed-tools.js','/assets/js/campaign-embed-analytics-links.js','/assets/js/stage12-survey-feedback-reward.js','/assets/js/stage12-check-in-reward.js','/assets/js/stage12-instant-win-reward.js','/assets/js/stage12-stamp-card-reward.js','/assets/js/stage12-rsvp-event-reward.js','/assets/js/stage12-campaign-media-artwork.js','/assets/js/stage12-media-reward-gates.js','/assets/js/stage12-campaign-participation-policy.js','/assets/js/stage12-listen-music-reward.js','/assets/js/predictive-campaign-studio.js','/assets/js/loyalty-quest-campaign-type.js','/assets/js/merchant-campaigns-cleanup.js?v=1.0.0','/assets/js/public-donations-community-assignments.js?v=1.0.0','/assets/js/public-donations-allocation.js?v=1.0.0','/assets/js/public-donations-recall.js?v=1.0.0'];
$merchantView = 'campaigns';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';
