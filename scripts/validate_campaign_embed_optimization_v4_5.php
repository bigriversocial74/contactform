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
$sqlFiles = glob($root . '/database/*campaign*embed*optimization*v4*5*.sql') ?: [];

$assert('Embed Leads API exists', $api !== '');
$assert('API defines placement page labels', str_contains($api, 'function mg_embed_leads_page_label'));
$assert('API defines next page suggestions', str_contains($api, 'function mg_embed_leads_next_page_suggestion'));
$assert('API defines per-campaign placement actions', str_contains($api, 'function mg_embed_leads_placement_action'));
$assert('API defines placement intelligence payload', str_contains($api, 'function mg_embed_leads_placement_intelligence'));
$assert('API returns placement_intelligence', str_contains($api, "'placement_intelligence' => $placementIntelligence"));
$assert('API includes recommended next action', str_contains($api, 'recommended_next_action'));
$assert('API includes campaign placement actions', str_contains($api, 'campaign_actions'));
$assert('API includes experiment queue', str_contains($api, 'experiments'));
$assert('API keeps no-SQL contract', str_contains($api, "'sql_required' => null"));

$assert('Embed Leads page labels v4.5 placement intelligence', str_contains($page, 'Campaign Embed Optimization v4.5') && str_contains($page, 'Placement Intelligence'));
$assert('Embed Leads page includes placement next-action target', str_contains($page, 'data-embed-placement-next'));
$assert('Embed Leads page includes placement cards target', str_contains($page, 'data-embed-placement-cards'));
$assert('Embed Leads page includes placement actions target', str_contains($page, 'data-embed-placement-actions'));
$assert('Embed Leads page includes placement experiments target', str_contains($page, 'data-embed-placement-experiments'));

$assert('JS renders placement intelligence', str_contains($js, 'function renderPlacementIntelligence'));
$assert('JS renders priority pills', str_contains($js, 'function priorityPill'));
$assert('JS calls placement renderer on load', str_contains($js, 'renderPlacementIntelligence(data.placement_intelligence || {})'));
$assert('JS renders recommended next action', str_contains($js, 'Recommended Next Action'));
$assert('JS renders campaign placement action cards', str_contains($js, 'mg-placement-action-card'));
$assert('JS renders placement experiments', str_contains($js, 'mg-placement-experiment'));

$assert('CSS styles placement panel', str_contains($css, '.mg-embed-placement-panel'));
$assert('CSS styles placement cards', str_contains($css, '.mg-embed-placement-cards'));
$assert('CSS styles placement actions', str_contains($css, '.mg-placement-action-card'));
$assert('CSS styles priority pills', str_contains($css, '.mg-placement-priority'));
$assert('No v4.5 SQL migration added', count($sqlFiles) === 0);

foreach ($checks as [$label, $passed]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}

if ($failures) {
    echo PHP_EOL . 'Campaign Embed Optimization v4.5 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Campaign Embed Optimization v4.5 validation passed.' . PHP_EOL;
