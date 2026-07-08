<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'analytics API' => ['api/merchant/campaign-embed-analytics.php', 'campaign_embed_events'],
    'analytics totals' => ['api/merchant/campaign-embed-analytics.php', 'conversion_rate'],
    'analytics origins' => ['api/merchant/campaign-embed-analytics.php', 'origin_rows'],
    'analytics timeline' => ['api/merchant/campaign-embed-analytics.php', 'timeline'],
    'analytics page app shell' => ['merchant-campaign-embed-analytics.php', 'mg-app-shell'],
    'analytics page sidebar' => ['merchant-campaign-embed-analytics.php', 'includes/app-sidebar.php'],
    'analytics page root' => ['merchant-campaign-embed-analytics.php', 'data-campaign-embed-analytics'],
    'analytics JS API call' => ['assets/js/campaign-embed-analytics.js', 'campaign-embed-analytics.php'],
    'analytics JS campaign table' => ['assets/js/campaign-embed-analytics.js', 'data-embed-analytics-campaign-table'],
    'analytics CSS shell' => ['assets/css/campaign-embed-analytics.css', '.mg-embed-analytics-shell'],
    'analytics CSS stats' => ['assets/css/campaign-embed-analytics.css', '.mg-embed-analytics-stats'],
];

$failures = [];
foreach ($checks as $label => [$path, $needle]) {
    $fullPath = $root . '/' . $path;
    if (!is_file($fullPath)) {
        $failures[] = $label . ': missing file ' . $path;
        continue;
    }
    $contents = (string)file_get_contents($fullPath);
    if (!str_contains($contents, $needle)) {
        $failures[] = $label . ': missing marker ' . $needle;
    }
}

if ($failures) {
    fwrite(STDERR, "Campaign embed analytics v3 validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Campaign embed analytics v3 validation passed.\n";
