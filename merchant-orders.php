<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
$page_title = 'Orders and Delivery Recovery | Microgifter';
$page_section = 'merchant';
$header_mode = 'account';
$page_styles = ['/assets/css/merchant-workspace.css','/assets/css/merchant-orders.css?v=2.0.0'];
$page_scripts = ['/assets/js/merchant-orders.js?v=2.0.0'];
$merchantView = 'orders';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/merchant-workspace.php';
require __DIR__ . '/includes/footer.php';
