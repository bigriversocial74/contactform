<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Merchant CRM | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/merchant-crm.css','/assets/css/communications.css','/assets/css/merchant-crm-retention-playbooks.css','/assets/css/merchant-crm-contact-stats.css'];
$page_scripts = ['/assets/js/merchant-workspace.js','/assets/js/store-health-completion-events.js'];
$merchantView = 'merchant_crm';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
echo '<scr' . 'ipt src="/assets/js/merchant-crm.js" defer></scr' . 'ipt>';
echo '<scr' . 'ipt src="/assets/js/merchant-crm-contact-stats.js" defer></scr' . 'ipt>';
echo '<scr' . 'ipt src="/assets/js/merchant-crm-messages.js" defer></scr' . 'ipt>';
echo '<scr' . 'ipt src="/assets/js/merchant-crm-retention-playbooks.js" defer></scr' . 'ipt>';
require __DIR__ . '/includes/footer.php';