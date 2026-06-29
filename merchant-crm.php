<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Merchant CRM | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/merchant-crm.css','/assets/css/communications.css','/assets/css/merchant-crm-retention-playbooks.css','/assets/css/merchant-crm-contact-stats.css'];
$page_scripts = ['/assets/js/merchant-workspace.js'];
$merchantView = 'merchant_crm';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
echo '<script src="/assets/js/merchant-crm.js" defer></script>';
echo '<script src="/assets/js/merchant-crm-contact-stats.js" defer></script>';
echo '<script src="/assets/js/merchant-crm-messages.js" defer></script>';
echo '<script src="/assets/js/merchant-crm-retention-playbooks.js" defer></script>';
require __DIR__ . '/includes/footer.php';