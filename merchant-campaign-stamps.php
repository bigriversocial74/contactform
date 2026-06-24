<?php
declare(strict_types=1);
require_once __DIR__.'/includes/app.php';
$page_title='Campaign Stamps | Microgifter';
$page_section='merchant';
$header_mode='account';
$page_styles=['/assets/css/merchant-workspace.css','/assets/css/stamp-ledger.css'];
$page_scripts=['/assets/js/merchant-workspace.js'];
$merchantView='campaign_stamps';
require __DIR__.'/includes/header.php';
require __DIR__.'/includes/merchant-workspace.php';
require __DIR__.'/includes/footer.php';
