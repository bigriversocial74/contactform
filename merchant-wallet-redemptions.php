<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Wallet Completion | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css'];
$page_scripts = ['/assets/js/merchant-workspace.js','/assets/js/stage12-redemptions.js'];
$merchantView = 'wallet_redemptions';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';
