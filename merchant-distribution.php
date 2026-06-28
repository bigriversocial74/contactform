<?php
declare(strict_types=1);
require_once __DIR__.'/includes/app.php';
$isDeveloperApi = isset($_GET['developer_api']);
$merchantView = $isDeveloperApi ? 'developer_api' : 'distribution';
$page_title = $merchantView === 'developer_api' ? 'Developer API | Microgifter' : 'Merchant Distribution | Microgifter';
$page_section='merchant';
$header_mode='account';
$page_styles=['/assets/css/merchant-workspace.css','/assets/css/merchant-distribution.css'];
if ($isDeveloperApi) {
    $page_styles[] = '/assets/css/merchant-developer-api-redesign.css';
}
$page_scripts=['/assets/js/merchant-workspace.js','/assets/js/merchant-distribution.js'];
if ($isDeveloperApi) {
    $page_scripts[] = '/assets/js/merchant-developer-api-tabs.js';
}
require __DIR__.'/includes/header.php';
require __DIR__.'/includes/merchant-workspace.php';
require __DIR__.'/includes/footer.php';
