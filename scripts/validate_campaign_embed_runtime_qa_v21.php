<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'runtime health API' => ['api/merchant/campaign-embed-runtime-health.php', 'campaign_embed_events'],
    'runtime SQL readiness response' => ['api/merchant/campaign-embed-runtime-health.php', 'migration_ready'],
    'runtime smoke checks' => ['api/merchant/campaign-embed-runtime-health.php', 'smoke_checks'],
    'merchant modal runtime health call' => ['assets/js/stage12-campaign-embed-tools.js', 'campaign-embed-runtime-health.php'],
    'merchant modal refresh activity' => ['assets/js/stage12-campaign-embed-tools.js', 'data-refresh-embed-activity'],
    'merchant modal QA link' => ['assets/js/stage12-campaign-embed-tools.js', 'merchant-campaign-embed-qa.php'],
    'runtime recent events UI' => ['assets/css/stage12-campaign-embed-tools.css', '.mg-campaign-embed-recent'],
    'QA page' => ['merchant-campaign-embed-qa.php', 'Campaign Embed Runtime QA'],
    'QA inline mode' => ['merchant-campaign-embed-qa.php', 'data-microgifter-display="inline"'],
    'QA button mode' => ['merchant-campaign-embed-qa.php', 'data-microgifter-display="button"'],
    'QA compact mode' => ['merchant-campaign-embed-qa.php', 'data-microgifter-display="compact"'],
    'QA page CSS' => ['assets/css/campaign-embed-qa.css', '.mg-embed-qa-host'],
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
    fwrite(STDERR, "Campaign embed runtime QA v2.1 validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Campaign embed runtime QA v2.1 validation passed.\n";
