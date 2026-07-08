<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'analytics API' => ['api/merchant/campaign-embed-analytics.php', 'campaign_embed_events'],
    'analytics totals' => ['api/merchant/campaign-embed-analytics.php', 'conversion_rate'],
    'analytics origins' => ['api/merchant/campaign-embed-analytics.php', 'origin_rows'],
    'analytics timeline' => ['api/merchant/campaign-embed-analytics.php', 'timeline'],
    'analytics export API' => ['api/merchant/campaign-embed-analytics-export.php', 'mg_embed_export_stream'],
    'analytics export datasets' => ['api/merchant/campaign-embed-analytics-export.php', "['campaigns', 'domains', 'events']"],
    'analytics page app shell' => ['merchant-campaign-embed-analytics.php', 'mg-app-shell'],
    'analytics page sidebar' => ['merchant-campaign-embed-analytics.php', 'includes/app-sidebar.php'],
    'analytics page root' => ['merchant-campaign-embed-analytics.php', 'data-campaign-embed-analytics'],
    'analytics page export controls' => ['merchant-campaign-embed-analytics.php', 'data-export-analytics'],
    'analytics page copy link' => ['merchant-campaign-embed-analytics.php', 'data-copy-analytics-link'],
    'analytics JS API call' => ['assets/js/campaign-embed-analytics.js', 'campaign-embed-analytics.php'],
    'analytics JS export call' => ['assets/js/campaign-embed-analytics.js', 'campaign-embed-analytics-export.php'],
    'analytics JS copy link' => ['assets/js/campaign-embed-analytics.js', 'copyAnalyticsLink'],
    'analytics JS campaign table' => ['assets/js/campaign-embed-analytics.js', 'data-embed-analytics-campaign-table'],
    'campaign row analytics links' => ['assets/js/campaign-embed-analytics-links.js', 'data-campaign-analytics-id'],
    'campaign page analytics script' => ['merchant-campaigns.php', 'campaign-embed-analytics-links.js'],
    'merchant sidebar analytics nav' => ['includes/merchant-workspace.php', 'campaign_embed_analytics'],
    'analytics CSS shell' => ['assets/css/campaign-embed-analytics.css', '.mg-embed-analytics-shell'],
    'analytics CSS export actions' => ['assets/css/campaign-embed-analytics.css', '.mg-embed-export-actions'],
    'analytics CSS empty actions' => ['assets/css/campaign-embed-analytics.css', '.mg-empty-actions'],
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
    fwrite(STDERR, "Campaign embed analytics v3.1 validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Campaign embed analytics v3.1 validation passed.\n";
