<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$failures = [];

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    return is_file($full) ? (string)file_get_contents($full) : '';
};

$assert = static function (string $label, bool $passed) use (&$checks, &$failures): void {
    $checks[] = [$label, $passed];
    if (!$passed) $failures[] = $label;
};

$api = $read('api/merchant/campaign-embed-leads.php');
$page = $read('merchant-campaign-embed-leads.php');
$js = $read('assets/js/campaign-embed-leads.js');
$css = $read('assets/css/campaign-embed-leads.css');
$sqlFiles = glob($root . '/database/*campaign*embed*performance*v4*4*.sql') ?: [];

$assert('Embed Leads API exists', $api !== '');
$assert('API defines lead quality scoring', str_contains($api, 'function mg_embed_leads_quality'));
$assert('API returns lead_quality per row', str_contains($api, "'lead_quality' => $quality"));
$assert('API calculates follow-up readiness', str_contains($api, 'ready_for_follow_up') && str_contains($api, '$readyForFollowUp'));
$assert('API returns average quality score', str_contains($api, 'average_quality_score'));
$assert('API returns top sources and modes', str_contains($api, 'top_sources') && str_contains($api, 'top_modes'));
$assert('API returns performance insight cards', str_contains($api, "'insight_cards'"));
$assert('API returns quality breakdown', str_contains($api, 'quality_breakdown'));
$assert('API returns merchant recommendations', str_contains($api, 'mg_embed_leads_performance_recommendations'));
$assert('API keeps no-SQL contract', str_contains($api, "'sql_required' => null"));
$assert('CSV export includes quality fields', str_contains($api, 'Lead Quality') && str_contains($api, 'Quality Score') && str_contains($api, 'Ready For Follow-Up'));

$assert('Embed Leads page labels v4.4 performance layer', str_contains($page, 'Campaign Embed Performance v4.4') && str_contains($page, 'Conversion Quality Layer'));
$assert('Embed Leads page includes performance insight target', str_contains($page, 'data-embed-performance-insights'));
$assert('Embed Leads page includes quality breakdown target', str_contains($page, 'data-embed-quality-breakdown'));
$assert('Embed Leads page includes recommendations target', str_contains($page, 'data-embed-recommendations'));

$assert('JS renders performance layer', str_contains($js, 'function renderPerformance'));
$assert('JS renders lead quality badge', str_contains($js, 'function qualityBadge'));
$assert('JS adds Quality table column', str_contains($js, '<th>Quality</th>'));
$assert('JS renders drawer quality signals', str_contains($js, 'Quality signals') && str_contains($js, 'mg-embed-quality-lists'));
$assert('JS calls renderPerformance on load', str_contains($js, 'renderPerformance(data.performance || {})'));

$assert('CSS styles performance panel', str_contains($css, '.mg-embed-performance-panel'));
$assert('CSS styles lead quality badge', str_contains($css, '.mg-lead-quality-badge'));
$assert('CSS styles quality lists', str_contains($css, '.mg-embed-quality-lists'));
$assert('No v4.4 SQL migration added', count($sqlFiles) === 0);

foreach ($checks as [$label, $passed]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}

if ($failures) {
    echo PHP_EOL . 'Campaign Embed Performance v4.4 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Campaign Embed Performance v4.4 validation passed.' . PHP_EOL;
