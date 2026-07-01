<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title='Sent Gifts | Microgifter';
$page_section='agent';
$header_mode='agent';
$agent_tab='sent';
$giftCenterFolder='sent';
$page_styles=['/assets/css/agent-workspace-layout.css','/assets/css/gift-action-center.css','/assets/css/gift-action-center-cleanup.css','/assets/css/gift-product-media.css'];
$page_scripts=['/assets/js/gift-action-center.js','/assets/js/gift-product-media-view.js','/assets/js/gift-action-center-load-envelope.js','/assets/js/gift-source-metadata.js'];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/gift-action-center.php';
require __DIR__ . '/includes/footer.php';
