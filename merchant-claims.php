<?php
declare(strict_types=1);
require_once __DIR__.'/includes/app.php';
$page_title='Merchant Claims | Microgifter';
$page_section='merchant';
$header_mode='account';
$page_styles=['/assets/css/merchant-workspace.css','/assets/css/merchant-claims.css'];
$page_scripts=['/assets/js/merchant-workspace.js','/assets/js/merchant-claims.js','/assets/js/store-health-completion-events.js'];
$merchantView='claims';
require __DIR__.'/includes/header.php';
require __DIR__.'/includes/merchant-workspace.php';
require __DIR__.'/includes/footer.php';