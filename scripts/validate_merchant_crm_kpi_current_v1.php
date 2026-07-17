<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'page' => $root . '/merchant-crm.php',
    'view' => $root . '/includes/merchant-crm-view.php',
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

$legacyAssets = [
    'merchant-crm-kpi-cleanup.css',
    'merchant-crm-kpi-hard-reset.css',
    'merchant-crm-kpi-no-icons.css',
    'merchant-crm-analytics-cleanup-v5.css',
    'merchant-crm-kpi-data-polish-v6.css',
    'merchant-crm-kpi-hard-reset.js',
    'merchant-crm-kpi-no-icons.js',
    'merchant-crm-analytics-cleanup-v5.js',
    'merchant-crm-kpi-data-polish-v6.js',
];

$legacyRemoved = true;
foreach ($legacyAssets as $asset) {
    if (str_contains($content['page'], $asset)) {
        $legacyRemoved = false;
        break;
    }
}

$kpiMarkers = [
    'data-crm-desktop-high',
    'data-crm-desktop-followup',
    'data-crm-desktop-claims',
    'data-crm-desktop-messages',
    'data-crm-desktop-active',
    'data-crm-desktop-verified',
    'data-crm-desktop-review',
];
$allKpisPresent = true;
foreach ($kpiMarkers as $marker) {
    if (!str_contains($content['view'], $marker)) {
        $allKpisPresent = false;
        break;
    }
}

$checks = [
    'one authoritative KPI stylesheet is loaded once' =>
        substr_count($content['page'], 'merchant-crm-kpi-authoritative-v1.css?v=1.0.0') === 1,
    'all legacy KPI repair styles and scripts are unloaded' => $legacyRemoved,
    'core CRM analytics search and mobile runtimes remain loaded' =>
        str_contains($content['page'], 'merchant-crm-desktop-analytics.js?v=1.0.0')
        && str_contains($content['page'], 'merchant-crm-desktop-search.js?v=1.0.0')
        && str_contains($content['page'], 'merchant-crm-mobile-dashboard.js?v=1.0.0'),
    'all seven live KPI bindings remain in the CRM view' => $allKpisPresent,
    'reporting window filter and export controls remain available' =>
        str_contains($content['view'], 'data-crm-desktop-range')
        && str_contains($content['view'], 'data-crm-desktop-filter')
        && str_contains($content['view'], 'data-crm-desktop-export'),
    'Audience Trends and View Contacts controls are preserved' =>
        str_contains($content['view'], 'class="mg-crm-trends"')
        && str_contains($content['view'], 'data-crm-desktop-pipeline'),
    'desktop KPI cards use four explicit content rows' =>
        str_contains($content['css'], 'grid-template-rows: minmax(28px, auto) 40px minmax(30px, auto) 30px')
        && str_contains($content['css'], '.mg-crm-kpi-label')
        && str_contains($content['css'], '.mg-crm-kpi-value')
        && str_contains($content['css'], '.mg-crm-kpi-meta')
        && str_contains($content['css'], '.mg-crm-kpi-chart'),
    'desktop layout uses four columns with a seven-column wide-screen mode' =>
        str_contains($content['css'], 'grid-template-columns: repeat(4, minmax(0, 1fr))')
        && str_contains($content['css'], '@media (min-width: 1450px)')
        && str_contains($content['css'], 'grid-template-columns: repeat(7, minmax(0, 1fr))'),
    'analytics and pipeline controls are explicitly visible' =>
        str_contains($content['css'], '.mg-crm-trends')
        && str_contains($content['css'], 'display: grid !important')
        && str_contains($content['css'], '.mg-crm-desktop-view-pipeline')
        && str_contains($content['css'], 'display: inline-flex !important'),
    'authoritative KPI rules are desktop-only and mobile contracts remain loaded' =>
        str_contains($content['css'], '@media (min-width: 981px)')
        && !str_contains($content['css'], '@media (max-width: 980px)')
        && str_contains($content['page'], 'merchant-crm-mobile-dashboard-contract.css?v=1.0.0')
        && str_contains($content['page'], 'merchant-crm-mobile-card-regression-fix.css?v=1.0.0'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) {
        $failed[] = $name;
    }
}

if ($failed !== []) {
    fwrite(STDERR, "\nMerchant CRM KPI current v1 validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "\nMerchant CRM KPI current v1 contract: 10/10.\n";
