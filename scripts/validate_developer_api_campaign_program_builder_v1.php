<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'view' => $root . '/includes/merchant-developer-api-view.php',
    'tabs' => $root . '/assets/js/merchant-developer-api-tabs.js',
    'builder' => $root . '/assets/js/merchant-developer-api-campaign-builder.js',
    'styles' => $root . '/assets/css/merchant-developer-api-campaign-builder.css',
    'page' => $root . '/merchant-distribution.php',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label} file: {$path}\n");
        exit(1);
    }
}

$view = file_get_contents($files['view']) ?: '';
$tabs = file_get_contents($files['tabs']) ?: '';
$builder = file_get_contents($files['builder']) ?: '';
$styles = file_get_contents($files['styles']) ?: '';
$page = file_get_contents($files['page']) ?: '';

$checks = [
    'Program Builder navigation tab exists' => str_contains($view, 'data-dev-tab="builder"'),
    'Program Builder has a dedicated panel' => str_contains($view, 'data-dev-tab-panel="builder"'),
    'Program form is inside the builder panel' => str_contains($view, 'data-dev-campaign-program-form'),
    'Campaign picker replaces product picker' => str_contains($view, 'data-program-campaign-picker') && !str_contains($view, 'data-program-product-picker'),
    'Campaign language replaces product language' => str_contains($view, 'Campaigns included') && str_contains($view, 'Select campaigns') && !str_contains($view, 'Products included'),
    'Program results use the full-width contract' => str_contains($view, 'mg-dev-program-results'),
    'Source and issuance health panel is removed' => !str_contains($view, 'Source and issuance health'),
    'App connection helper panel is removed' => !str_contains($view, 'How this connects to apps'),
    'Top create action opens Program Builder' => str_contains($view, 'data-dev-tab-trigger="builder" data-dev-new-plan'),
    'Distribution action retains shared new-program hook' => str_contains($view, 'data-program-new data-dev-program-builder-open'),
    'Builder loads merchant campaigns' => str_contains($builder, "Microgifter.get('/api/merchant/campaigns.php')"),
    'Builder stores campaign IDs in program metadata' => str_contains($builder, 'campaign_ids:campaigns'),
    'Builder preserves existing metadata' => str_contains($builder, 'Object.assign({},currentMetadata'),
    'Builder intercepts shared product submit handler' => str_contains($builder, 'stopImmediatePropagation') && str_contains($builder, '},true);'),
    'Builder requires at least one campaign' => str_contains($builder, 'Select at least one merchant campaign'),
    'Builder restores campaign selections while editing' => str_contains($builder, 'currentMetadata.campaign_ids'),
    'Removed dashboard panels retain hidden runtime contracts' => str_contains($builder, "['sources','queue']") && str_contains($builder, "node.hidden=true"),
    'Tabs route new and edit actions into builder' => str_contains($tabs, "activate('builder',true)") && str_contains($tabs, 'mg:developer-program-edit'),
    'Legacy product loading is absent from tab controller' => !str_contains($tabs, 'loadProgramProducts') && !str_contains($tabs, 'renderProductPicker'),
    'Campaign builder stylesheet is loaded' => str_contains($page, '/assets/css/merchant-developer-api-campaign-builder.css'),
    'Campaign builder script is loaded' => str_contains($page, '/assets/js/merchant-developer-api-campaign-builder.js'),
    'Campaign picker has responsive styling' => str_contains($styles, '.mg-dev-campaign-picker') && str_contains($styles, '@media(max-width:680px)'),
    'Results layout is explicitly full width' => str_contains($styles, '.mg-dev-program-results{width:100%'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, PHP_EOL . count($failed) . " validation check(s) failed.\n");
    exit(1);
}

echo PHP_EOL . count($checks) . " Developer API campaign program builder checks passed.\n";
