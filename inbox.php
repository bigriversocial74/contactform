<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Gift Inbox | Microgifter';
$page_section = 'agent';
$header_mode = 'agent';
$agent_tab = 'inbox';
$giftCenterFolder = 'inbox';
$page_styles = ['/assets/css/agent-workspace-layout.css','/assets/css/gift-action-center.css','/assets/css/gift-action-center-cleanup.css'];
$page_scripts = ['/assets/js/gift-action-center.js'];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/gift-action-center.php';
require __DIR__ . '/includes/footer.php';