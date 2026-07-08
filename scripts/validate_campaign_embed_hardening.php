<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'merchant embed health UI' => ['assets/js/stage12-campaign-embed-tools.js', 'data-campaign-embed-health'],
    'merchant embed debug option' => ['assets/js/stage12-campaign-embed-tools.js', 'data-campaign-embed-debug'],
    'merchant embed source marker' => ['assets/js/stage12-campaign-embed-tools.js', 'data-microgifter-source="merchant_embed"'],
    'public embed debug logging' => ['assets/js/microgifter-campaign-embed.js', 'data-microgifter-debug'],
    'public embed loaded event' => ['assets/js/microgifter-campaign-embed.js', 'microgifter:campaignEmbedLoaded'],
    'public embed submit event' => ['assets/js/microgifter-campaign-embed.js', 'microgifter:campaignEmbedSubmitted'],
    'public embed client email validation' => ['assets/js/microgifter-campaign-embed.js', 'validEmail'],
    'public embed origin metadata' => ['assets/js/microgifter-campaign-embed.js', 'embed_origin'],
    'embed API health payload' => ['api/public/campaigns/embed.php', "'health' =>"],
    'embed API origin payload' => ['api/public/campaigns/embed.php', 'request_origin'],
    'embed CORS cross-origin resource policy' => ['api/public/campaigns/_embed_cors.php', 'Cross-Origin-Resource-Policy: cross-origin'],
    'embed submit CORS reapply' => ['api/public/campaigns/embed-submit.php', 'mg_public_campaign_embed_cors();'],
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

$submitContents = is_file($root . '/api/public/campaigns/embed-submit.php') ? (string)file_get_contents($root . '/api/public/campaigns/embed-submit.php') : '';
if (substr_count($submitContents, 'mg_public_campaign_embed_cors();') < 2) {
    $failures[] = 'embed submit CORS reapply: expected CORS helper before and after bootstrap';
}

$apiContents = is_file($root . '/api/public/campaigns/embed.php') ? (string)file_get_contents($root . '/api/public/campaigns/embed.php') : '';
if (substr_count($apiContents, 'mg_public_campaign_embed_cors();') < 2) {
    $failures[] = 'embed API CORS reapply: expected CORS helper before and after bootstrap';
}

if ($failures) {
    fwrite(STDERR, "Campaign embed hardening validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Campaign embed hardening validation passed.\n";
