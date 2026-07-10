<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Loyalty Quests | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/merchant-campaigns.css','/assets/css/merchant-loyalty-quests.css'];
$page_scripts = ['/assets/js/merchant-workspace.js','/assets/js/merchant-loyalty-quests.js'];
$merchantView = 'loyalty_quests';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';
