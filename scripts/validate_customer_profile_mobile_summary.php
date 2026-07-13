<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'page' => 'merchant-customer.php',
    'css' => 'assets/css/merchant-customer-profile-mobile-summary.css',
    'js' => 'assets/js/merchant-customer-profile-mobile-summary.js',
    'core_js' => 'assets/js/merchant-customer-profile.js',
];

$files = [];
foreach ($paths as $key => $path) {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content) || trim($content) === '') {
        fwrite(STDERR, "Missing validation target: {$path}\n");
        exit(1);
    }
    $files[$key] = $content;
}

$checks = [
    'mobile summary stylesheet is loaded after base profile styles' => strpos($files['page'], 'merchant-customer-profile.css') < strpos($files['page'], 'merchant-customer-profile-mobile-summary.css?v=1.0.0'),
    'mobile summary runtime is loaded after core profile runtime' => strpos($files['page'], 'merchant-customer-profile.js') < strpos($files['page'], 'merchant-customer-profile-mobile-summary.js?v=1.0.0'),
    'shared mobile header offset is removed from nested customer shell' => str_contains($files['css'], '.mg-app-shell.mg-customer-profile-app')
        && str_contains($files['css'], 'padding-top:0!important'),
    'customer workspace receives compact mobile padding' => str_contains($files['css'], '.mg-customer-profile-main')
        && str_contains($files['css'], 'padding:8px 8px 22px!important'),
    'mobile metrics use an accessible accordion' => str_contains($files['js'], 'data-cp-mobile-metrics-toggle')
        && str_contains($files['js'], "setAttribute('aria-expanded'")
        && str_contains($files['js'], 'aria-controls'),
    'metrics accordion opens by default' => str_contains($files['js'], 'setMetricsOpen(toggle, body, true)'),
    'six profile metrics render three columns wide on mobile' => str_contains($files['css'], 'grid-template-columns:repeat(3,minmax(0,1fr))!important'),
    'mobile KPI cards are compact' => str_contains($files['css'], 'min-height:91px!important')
        && str_contains($files['css'], 'font-size:17px!important'),
    'all eight profile tabs receive icon definitions' => substr_count($files['js'], ': \'<svg viewBox="0 0 24 24">') === 8,
    'mobile tabs hide text labels and show icon controls' => str_contains($files['css'], '.mg-cp-tab-label')
        && str_contains($files['css'], 'display:none!important')
        && str_contains($files['css'], '.mg-cp-tab-icon'),
    'tab accessibility labels remain available' => str_contains($files['js'], "setAttribute('aria-label', label)")
        && str_contains($files['js'], "setAttribute('title', label)"),
    'existing data-profile-tab contract remains the tab source' => str_contains($files['js'], "getAttribute('data-profile-tab')")
        && str_contains($files['core_js'], "closest('[data-profile-tab]')"),
    'profile mini statistics remain two columns on mobile' => str_contains($files['css'], '.mg-cp-mini-stats')
        && str_contains($files['css'], 'grid-template-columns:repeat(2,minmax(0,1fr))!important'),
    'profile detail rows are compacted into two columns' => str_contains($files['css'], '.mg-cp-details')
        && str_contains($files['css'], 'grid-template-columns:repeat(2,minmax(0,1fr))!important'),
    'desktop stays on the existing profile layout' => str_contains($files['css'], 'Desktop remains owned by merchant-customer-profile.css')
        && str_contains($files['css'], '@media (max-width:720px)'),
];

$failures = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failures[] = $label;
}

if ($failures !== []) {
    fwrite(STDERR, 'Customer Profile mobile summary validation failed: ' . implode(', ', $failures) . PHP_EOL);
    exit(1);
}

echo 'Customer Profile mobile summary contract: ' . count($checks) . '/' . count($checks) . " checks passed.\n";
