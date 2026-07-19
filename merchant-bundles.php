<?php
declare(strict_types=1);
require_once __DIR__.'/includes/app.php';
$page_title='Product Bundles | Microgifter';
$page_section='merchant';
$header_mode='account';
$page_styles=['/assets/css/merchant-workspace.css','/assets/css/merchant-bundles.css'];
$page_scripts=['/assets/js/merchant-bundles.js'];
$merchantView='bundles';
require __DIR__.'/includes/header.php';
require __DIR__.'/includes/merchant-workspace.php';
require __DIR__.'/includes/footer.php';
