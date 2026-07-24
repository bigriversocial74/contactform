<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/app.php';
mg_require_auth('/signin.php', '/admin/investment-wizard.php');
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: private, no-cache, must-revalidate');
foreach (['part-00','part-01','part-02','part-03','part-04','part-05','part-06','part-07','part-08'] as $part) {
    $path = __DIR__ . '/assets/js/investment-wizard-v1-parts/' . $part;
    if (!is_file($path)) {
        http_response_code(500);
        echo "throw new Error('Investment Wizard runtime is incomplete.');";
        exit;
    }
    readfile($path);
}
