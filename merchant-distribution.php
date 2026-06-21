<?php
declare(strict_types=1);
require_once __DIR__.'/includes/app.php';
$merchantView = isset($_GET['developer_api']) ? 'developer_api' : 'distribution';
$page_title = $merchantView === 'developer_api' ? 'Developer API | Microgifter' : 'Merchant Distribution | Microgifter';
$page_section='merchant';
$header_mode='account';
$page_styles=['/assets/css/merchant-workspace.css','/assets/css/merchant-distribution.css'];
$page_scripts=['/assets/js/merchant-workspace.js','/assets/js/merchant-distribution.js'];
require __DIR__.'/includes/header.php';
require __DIR__.'/includes/merchant-workspace.php';
require __DIR__.'/includes/footer.php';
