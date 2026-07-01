<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';

$page_title = 'Gift Inbox | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'inbox';
$giftCenterFolder = 'inbox';

$page_styles = [
    '/assets/css/agent-workspace-layout.css',
    '/assets/css/gift-action-center.css',
    '/assets/css/gift-action-center-cleanup.css',
    '/assets/css/gift-action-center-send-modal.css',
    '/assets/css/gift-product-media.css',
    '/assets/css/gift-action-center-claim-modal.css',
    '/assets/css/sponsored-campaign-card.css',
];

$page_scripts = [
    '/assets/js/gift-action-center.js',
    '/assets/js/gift-product-media-view.js',
    '/assets/js/gift-action-center-load-envelope.js',
    '/assets/js/sponsored-campaign-card.js',
    '/assets/js/gift-action-center-claim-modal.js',
    '/assets/js/gift-action-center-claim-click.js',
    '/assets/js/gift-action-center-send-modal.js',
    '/assets/js/gift-source-metadata.js',
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/gift-action-center.php';
require __DIR__ . '/includes/footer.php';
