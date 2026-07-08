<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'public CORS helper' => ['api/public/campaigns/_embed_cors.php', 'mg_public_campaign_embed_cors'],
    'public embed payload endpoint' => ['api/public/campaigns/embed.php', 'mg_campaign_embed_domain_allowed'],
    'public embed disabled fallback' => ['api/public/campaigns/embed.php', 'Campaign embed is disabled by the merchant.'],
    'public embed disallowed domain fallback' => ['api/public/campaigns/embed.php', 'This campaign embed is not enabled for this website domain.'],
    'public submit endpoint' => ['api/public/campaigns/embed-submit.php', 'mg_require_method'],
    'public event endpoint' => ['api/public/campaigns/embed-event.php', 'campaign_embed_events'],
    'event types include invalid' => ['api/public/campaigns/embed-event.php', 'invalid'],
    'merchant embed settings API' => ['api/merchant/campaign-embed-settings.php', 'campaign_embed_settings'],
    'merchant runtime health API' => ['api/merchant/campaign-embed-runtime-health.php', 'smoke_checks'],
    'merchant analytics API' => ['api/merchant/campaign-embed-analytics.php', 'conversion_rate'],
    'merchant export API' => ['api/merchant/campaign-embed-analytics-export.php', 'mg_embed_export_stream'],
    'export row count header' => ['api/merchant/campaign-embed-analytics-export.php', 'X-Microgifter-Export-Rows'],
    'export content type nosniff' => ['api/merchant/campaign-embed-analytics-export.php', 'X-Content-Type-Options: nosniff'],
    'export CSV injection guard' => ['api/merchant/campaign-embed-analytics-export.php', 'mg_embed_export_cell'],
    'export filtered campaign lookup' => ['api/merchant/campaign-embed-analytics-export.php', 'mg_embed_export_campaign'],
    'QA page app shell' => ['merchant-campaign-embed-qa.php', 'mg-app-shell'],
    'QA page sidebar' => ['merchant-campaign-embed-qa.php', 'includes/app-sidebar.php'],
    'analytics page app shell' => ['merchant-campaign-embed-analytics.php', 'mg-app-shell'],
    'analytics page sidebar' => ['merchant-campaign-embed-analytics.php', 'includes/app-sidebar.php'],
    'analytics export controls' => ['merchant-campaign-embed-analytics.php', 'data-export-analytics'],
    'analytics copy link action' => ['assets/js/campaign-embed-analytics.js', 'copyAnalyticsLink'],
    'analytics no-events empty state' => ['assets/js/campaign-embed-analytics.js', 'No recent embed events yet.'],
    'campaign dashboard analytics shortcuts' => ['assets/js/campaign-embed-analytics-links.js', 'data-campaign-analytics-id'],
    'campaign dashboard analytics script' => ['merchant-campaigns.php', 'campaign-embed-analytics-links.js'],
    'merchant sidebar analytics nav' => ['includes/merchant-workspace.php', 'campaign_embed_analytics'],
    'widget tracks loaded' => ['assets/js/microgifter-campaign-embed.js', "'loaded'"],
    'widget tracks opened' => ['assets/js/microgifter-campaign-embed.js', "'opened'"],
    'widget tracks submitted' => ['assets/js/microgifter-campaign-embed.js', "'submitted'"],
    'widget tracks invalid' => ['assets/js/microgifter-campaign-embed.js', "'invalid'"],
    'widget tracks error' => ['assets/js/microgifter-campaign-embed.js', "'error'"],
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

$layoutFiles = ['merchant-campaign-embed-qa.php', 'merchant-campaign-embed-analytics.php'];
foreach ($layoutFiles as $path) {
    $contents = (string)file_get_contents($root . '/' . $path);
    foreach (['mg-app-shell', 'data-sidebar-contract="mg-app-sidebar"', 'mg-app-workspace'] as $marker) {
        if (!str_contains($contents, $marker)) $failures[] = $path . ': missing layout marker ' . $marker;
    }
}

if ($failures) {
    fwrite(STDERR, "Campaign embed production QA v3.2 validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Campaign embed production QA v3.2 validation passed.\n";
