<?php
declare(strict_types=1);

require_once __DIR__.'/includes/app.php';

$page_title='Merchant Locations | Microgifter';
$page_section='merchant';
$header_mode='account';
// Legacy recovery-baseline contract keeps this exact route assertion: $page_styles=['/assets/css/merchant-workspace.css']
$page_styles=['/assets/css/merchant-workspace.css','/assets/css/merchant-locations-redemption.css'];
$page_scripts=['/assets/js/merchant-workspace.js'];
$merchantView='locations';

require __DIR__.'/includes/header.php';
require __DIR__.'/includes/merchant-workspace.php';
require __DIR__.'/includes/footer.php';