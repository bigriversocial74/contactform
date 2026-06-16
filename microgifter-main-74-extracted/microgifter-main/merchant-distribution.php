<?php
declare(strict_types=1);
require_once __DIR__.'/includes/app.php';
$page_title='Merchant Distribution | Microgifter';
$page_section='merchant';
$header_mode='account';
$page_styles=['/assets/css/merchant-workspace.css','/assets/css/merchant-distribution.css'];
$page_scripts=['/assets/js/merchant-workspace.js','/assets/js/merchant-distribution.js'];
$merchantView='distribution';
require __DIR__.'/includes/header.php';
require __DIR__.'/includes/merchant-workspace.php';
require __DIR__.'/includes/footer.php';
