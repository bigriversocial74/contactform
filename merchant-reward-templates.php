<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Reward Templates | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/merchant-reward-templates.css'];
$page_scripts = ['/assets/js/merchant-workspace.js'];
$merchantView = 'reward_templates';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';