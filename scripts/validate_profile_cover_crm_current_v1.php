<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'profile' => $root . '/assets/css/public-profile-polish.css',
    'page' => $root . '/merchant-crm.php',
    'css' => $root . '/assets/css/merchant-crm-kpi-authoritative-v1.css',
];

$content = [];
foreach ($files as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n");
        exit(1);
    }
    $content[$key] = (string) file_get_contents($path);
}

$checks = [
    'public profile owner tools retain the established visibility cleanup' =>
        str_contains($content['profile'], '.mg-profile-owner-tools')
        && str_contains($content['profile'], 'display:none!important'),
    'Merchant CRM loads the authoritative KPI stylesheet' =>
        str_contains($content['page'], '/assets/css/merchant-crm-kpi-authoritative-v1.css?v=1.0.0'),
    'desktop KPI icon area is excluded from layout' =>
        str_contains($content['css'], '.mg-crm-kpi-icon')
        && str_contains($content['css'], 'display: none !important'),
    'desktop KPI cards use four stable vertical rows' =>
        str_contains($content['css'], 'grid-template-rows: minmax(28px, auto) 40px minmax(30px, auto) 30px')
        && str_contains($content['css'], 'min-height: 154px'),
    'desktop KPI text remains readable without clipping' =>
        str_contains($content['css'], 'white-space: normal !important')
        && str_contains($content['css'], 'overflow: visible !important'),
    'desktop KPI sparkline remains contained' =>
        str_contains($content['css'], '.mg-crm-kpi-chart')
        && str_contains($content['css'], 'max-width: 100% !important'),
    'mobile CRM remains outside the KPI compatibility layer' =>
        str_contains($content['css'], '@media (min-width: 981px)')
        && !str_contains($content['css'], '@media (max-width: 980px)'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) {
        $failed[] = $label;
    }
}

if ($failed !== []) {
    fwrite(STDERR, "Profile and CRM compatibility validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "Profile and CRM compatibility contract passed.\n";
