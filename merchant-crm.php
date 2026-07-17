<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$page_title = 'Merchant CRM | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$merchantView = 'merchant_crm';

$page_styles = [
    '/assets/css/merchant-workspace.css',
    '/assets/css/merchant-crm.css',
    '/assets/css/communications.css',
    '/assets/css/merchant-crm-command-center.css',
    '/assets/css/merchant-crm-drawer-stack.css',
    '/assets/css/merchant-crm-contact-stats.css',
    '/assets/css/merchant-crm-contact-threads.css',
    '/assets/css/merchant-crm-action-center.css',
    '/assets/css/merchant-crm-contacts-clean.css',
    '/assets/css/merchant-crm-contact-link-polish.css?v=3.0.0',
    '/assets/css/merchant-crm-layout-stability.css?v=1.0.0',
    '/assets/css/merchant-crm-contacts-only.css?v=1.1.0',
    '/assets/css/merchant-crm-identity-duplicates.css?v=1.0.0',
    '/assets/css/merchant-crm-desktop-analytics.css?v=1.0.0',
    '/assets/css/merchant-crm-mobile-dashboard.css?v=1.0.0',
    '/assets/css/merchant-crm-mobile-dashboard-contract.css?v=1.0.0',
    '/assets/css/merchant-crm-mobile-card-regression-fix.css?v=1.0.0',
    '/assets/css/merchant-crm-desktop-layout-fix.css?v=1.0.0',
    '/assets/css/merchant-crm-kpi-authoritative-v1.css?v=1.0.0',
];

$page_scripts = [
    '/assets/js/merchant-workspace.js',
    '/assets/js/merchant-crm-contact-rollup.js?v=1.0.0',
    '/assets/js/merchant-crm.js',
    '/assets/js/merchant-crm-contact-action-modal.js',
    '/assets/js/merchant-crm-action-scheduler.js',
    '/assets/js/merchant-crm-contact-threads.js',
    '/assets/js/merchant-crm-realtime-message.js',
    '/assets/js/merchant-crm-reward-picker.js',
    '/assets/js/merchant-crm-reward-invite-bridge.js',
    '/assets/js/merchant-crm-reward-invite-operations.js',
    '/assets/js/merchant-crm-contact-stats.js?v=2.0.0',
    '/assets/js/merchant-crm-desktop-analytics.js?v=1.0.0',
    '/assets/js/merchant-crm-contact-link-polish.js?v=3.0.0',
    '/assets/js/merchant-crm-identity-duplicates.js?v=1.1.0',
    '/assets/js/merchant-crm-mobile-dashboard.js?v=1.0.0',
    '/assets/js/merchant-crm-desktop-search.js?v=1.0.0',
];

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';
