<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$targets = [
    'page' => 'merchant-crm.php',
    'view' => 'includes/merchant-crm-view.php',
    'css' => 'assets/css/merchant-crm-desktop-analytics.css',
    'js' => 'assets/js/merchant-crm-desktop-analytics.js',
];

$content = [];
foreach ($targets as $key => $relativePath) {
    $path = $root . '/' . $relativePath;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$relativePath}\n");
        exit(1);
    }
    $value = file_get_contents($path);
    if (!is_string($value) || trim($value) === '') {
        fwrite(STDERR, "Empty required file: {$relativePath}\n");
        exit(1);
    }
    $content[$key] = $value;
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
$kpisPresent = true;
foreach ($kpiMarkers as $marker) {
    $kpisPresent = $kpisPresent && str_contains($content['view'], $marker) && str_contains($content['js'], $marker);
}

$checks = [
    'desktop assets are loaded by Merchant CRM' =>
        str_contains($content['page'], 'merchant-crm-desktop-analytics.css')
        && str_contains($content['page'], 'merchant-crm-desktop-analytics.js'),
    'desktop hero and controls are rendered' =>
        str_contains($content['view'], 'data-crm-desktop-hero')
        && str_contains($content['view'], 'data-crm-desktop-range')
        && str_contains($content['view'], 'data-crm-desktop-filter')
        && str_contains($content['view'], 'data-crm-desktop-export'),
    'all seven KPI bindings are connected' => $kpisPresent,
    'audience health and pipeline bindings are present' =>
        str_contains($content['view'], 'data-crm-health-ring')
        && str_contains($content['view'], 'data-crm-health-bar=')
        && str_contains($content['view'], 'data-crm-pipeline-new')
        && str_contains($content['view'], 'data-crm-pipeline-converted')
        && str_contains($content['view'], 'data-crm-conversion-rate'),
    'analytics use the canonical contact render event and support CSV export' =>
        str_contains($content['js'], 'mg:crm-contacts:rendered')
        && str_contains($content['js'], 'event.detail')
        && str_contains($content['js'], 'function exportCsv')
        && str_contains($content['js'], 'microgifter-merchant-crm-contacts.csv'),
    'desktop layout and mobile boundary are explicit' =>
        str_contains($content['css'], '.mg-crm-desktop-kpis')
        && str_contains($content['css'], '.mg-crm-desktop-insights')
        && str_contains($content['css'], '.mg-crm-pipeline-stages')
        && str_contains($content['css'], 'max-width:980px')
        && str_contains($content['css'], 'display:none!important'),
    'existing mobile and contact operations remain mounted' =>
        str_contains($content['view'], 'data-crm-mobile-overview')
        && str_contains($content['view'], 'data-crm-mobile-directory')
        && str_contains($content['view'], 'data-merchant-crm-table')
        && str_contains($content['view'], 'data-crm-drawer')
        && str_contains($content['view'], 'data-crm-message-modal')
        && str_contains($content['view'], 'data-crm-reward-modal'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) {
        $failed[] = $name;
    }
}

if ($failed !== []) {
    fwrite(STDERR, "Merchant CRM desktop analytics validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Merchant CRM desktop analytics contract: 10/10.' . PHP_EOL;
