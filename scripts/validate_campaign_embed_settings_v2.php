<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'settings SQL table' => ['database/campaign_embed_settings_v2.sql', 'CREATE TABLE IF NOT EXISTS campaign_embed_settings'],
    'events SQL table' => ['database/campaign_embed_settings_v2.sql', 'CREATE TABLE IF NOT EXISTS campaign_embed_events'],
    'merchant settings API' => ['api/merchant/campaign-embed-settings.php', 'campaign_embed_settings'],
    'merchant stats API' => ['api/merchant/campaign-embed-settings.php', 'campaign_embed_events'],
    'public event API' => ['api/public/campaigns/embed-event.php', 'Campaign embed event recorded'],
    'public payload settings' => ['api/public/campaigns/embed.php', "'settings' =>"],
    'public payload domain guard' => ['api/public/campaigns/embed.php', 'mg_campaign_embed_domain_allowed'],
    'public widget event tracking' => ['assets/js/microgifter-campaign-embed.js', 'embed-event.php'],
    'public widget compact mode' => ['assets/js/microgifter-campaign-embed.js', 'is-compact'],
    'merchant modal settings UI' => ['assets/js/stage12-campaign-embed-tools.js', 'data-save-embed-settings'],
    'merchant modal analytics UI' => ['assets/js/stage12-campaign-embed-tools.js', 'data-campaign-embed-analytics'],
    'settings CSS' => ['assets/css/stage12-campaign-embed-tools.css', '.mg-campaign-embed-settings'],
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
    fwrite(STDERR, "Campaign embed settings v2 validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Campaign embed settings v2 validation passed.\n";
