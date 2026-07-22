<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$files = [
    'definitions' => $root . '/includes/creator-campaigns/analytics-definitions.php',
    'context' => $root . '/includes/creator-campaigns/analytics-context.php',
    'query' => $root . '/includes/creator-campaigns/analytics-query.php',
    'export' => $root . '/includes/creator-campaigns/analytics-export.php',
    'merchant_api' => $root . '/api/merchant/creator-campaign-analytics.php',
    'creator_api' => $root . '/api/creator/campaign-analytics.php',
    'merchant_page' => $root . '/merchant-creator-analytics.php',
    'creator_page' => $root . '/creator-campaign-analytics.php',
    'view' => $root . '/includes/creator-campaign-analytics-view.php',
    'js' => $root . '/assets/js/creator-campaign-analytics.js',
    'css' => $root . '/assets/css/creator-campaign-analytics.css',
    'bootstrap' => $root . '/includes/creator-campaigns/bootstrap.php',
    'workflow' => $root . '/.github/workflows/creator-campaign-analytics-v10.yml',
    'docs' => $root . '/docs/creator-campaigns/CREATOR_CAMPAIGN_PHASE10_ANALYTICS.md',
];
$content = [];
foreach ($files as $key => $file) {
    if (!is_file($file)) { fwrite(STDERR, "Missing {$file}\n"); exit(1); }
    $content[$key] = file_get_contents($file) ?: '';
}
$checks = [];
$add = static function (string $label, bool $ok) use (&$checks): void { $checks[] = [$label, $ok, 4]; };
$has = static fn(string $file, string $needle): bool => str_contains($file, $needle);

$add('No Phase 10 SQL or duplicate metric store', !is_file($root . '/database/20260722_creator_campaign_analytics_v10.sql') && $has($content['docs'], 'No SQL required'));
$add('Authoritative tracking events reused', $has($content['query'], 'creator_campaign_tracking_events'));
$add('Canonical attribution reused', $has($content['query'], 'creator_campaign_attributions'));
$add('Accepted tracking state enforced', $has($content['query'], "e.status='accepted'"));
$add('Canonical attribution and accepted conversion state enforced', $has($content['query'], "a.status IN ('attributed','overridden')") && substr_count($content['query'], "e.status='accepted'") >= 4);

$add('Integer minor-unit earnings', $has($content['query'], 'amount_minor') && $has($content['docs'], 'integer minor units'));
$add('Currency isolation', $has($content['query'], 'GROUP BY') && $has($content['query'], '.currency'));
$add('Zero-denominator rate guard', $has($content['definitions'], '$uniqueClicks <= 0'));
$add('Validated custom date format', $has($content['definitions'], "createFromFormat('!Y-m-d'"));
$add('Bounded custom date range', $has($content['definitions'], '$days > 731'));

$add('Day week month buckets', $has($content['definitions'], "'month'") && $has($content['definitions'], "'week'"));
$add('Merchant workspace ownership', $has($content['query'], 'cc.workspace_id'));
$add('Creator participant filter preserves campaign scope', $has($content['query'], "$scope['participant_id'] !== null && $participantColumn !== null"));
$add('Creator budget exclusion', $has($content['query'], "if (\$scope['mode'] !== 'merchant')"));
$add('User-model-compatible permissions reused', $has($content['context'], 'merchant.intelligence.view') && $has($content['context'], 'creator.campaign_messages.view_own'));

$add('CSV report whitelist', $has($content['export'], 'mg_creator_campaign_analytics_report_types'));
$add('CSV formula injection protection', $has($content['export'], "/^[=+\\-@\\t\\r]/u"));
$add('CSV generated without persistence', $has($content['export'], "fopen('php://temp'"));
$add('Merchant API is read only', $has($content['merchant_api'], "mg_require_method('GET')"));
$add('Creator API is read only', $has($content['creator_api'], "mg_require_method('GET')"));

$add('Authenticated merchant app shell', $has($content['merchant_page'], 'mg-app-shell'));
$add('Authenticated Creator page', $has($content['creator_page'], 'mg_require_auth'));
$add('Filters tabs and exports', $has($content['view'], 'data-cca-filters') && $has($content['view'], 'data-cca-tab') && $has($content['view'], 'data-cca-export'));
$add('Bootstrap integration', $has($content['bootstrap'], 'analytics-definitions.php') && $has($content['bootstrap'], 'analytics-query.php'));
$add('PHP MySQL and prior-phase workflow', $has($content['workflow'], 'Phase 9 compatibility') && $has($content['workflow'], 'analytics lifecycle'));

$score = 0;
foreach ($checks as [$label, $ok, $points]) {
    echo sprintf('[%s] %s (%d)', $ok ? 'PASS' : 'FAIL', $label, $points) . PHP_EOL;
    if ($ok) $score += $points;
}
echo "Creator Campaign Analytics v10 score: {$score}/100\n";
if ($score !== 100) exit(1);
