<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title='Loyalty Quest Delivery | Microgifter';
$page_section='merchant';
$header_mode='account';
$page_styles=['/assets/css/merchant-workspace.css','/assets/css/merchant-campaigns.css','/assets/css/loyalty-quest-delivery.css'];
$page_scripts=['/assets/js/merchant-workspace.js','/assets/js/loyalty-quest-delivery.js'];
$merchantView='quest_delivery';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';
