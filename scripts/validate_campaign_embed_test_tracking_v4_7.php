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

$sql = $read('database/campaign_embed_placement_tests_v4_7.sql');
$api = $read('api/merchant/campaign-embed-placement-tests.php');
$page = $read('merchant-campaign-embed-leads.php');
$js = $read('assets/js/campaign-embed-leads.js');
$css = $read('assets/css/campaign-embed-leads.css');

$assert('v4.7 SQL migration exists', $sql !== '');
$assert('SQL creates campaign_embed_placement_tests table', str_contains($sql, 'CREATE TABLE IF NOT EXISTS campaign_embed_placement_tests'));
$assert('SQL includes status enum', str_contains($sql, "ENUM('planned','running','completed','paused')"));
$assert('SQL stores campaign/domain/page/source/mode fields', str_contains($sql, 'campaign_public_id') && str_contains($sql, 'origin_host') && str_contains($sql, 'page_url') && str_contains($sql, 'source') && str_contains($sql, 'embed_mode'));
$assert('SQL stores start/end/pause/compare timestamps', str_contains($sql, 'started_at') && str_contains($sql, 'ended_at') && str_contains($sql, 'paused_at') && str_contains($sql, 'compared_at'));

$assert('Placement tests API exists', $api !== '');
$assert('API uses merchant workspace guard', str_contains($api, 'mg_merchant_ensure_workspace'));
$assert('API requires CSRF for writes', str_contains($api, 'mg_require_csrf_for_write'));
$assert('API checks table readiness and SQL migration name', str_contains($api, 'mg_embed_test_table_ready') && str_contains($api, 'campaign_embed_placement_tests_v4_7.sql'));
$assert('API supports GET history', str_contains($api, "if ($method === 'GET')"));
$assert('API supports start action', str_contains($api, "$action === 'start'"));
$assert('API supports pause/resume/complete/compare actions', str_contains($api, "$action === 'pause'") && str_contains($api, "$action === 'resume'") && str_contains($api, "$action === 'complete'") && str_contains($api, "$action === 'compare'"));
$assert('API returns compare_url', str_contains($api, 'compare_url'));

$assert('Embed Leads page labels v4.7', str_contains($page, 'Campaign Embed v4.7') && str_contains($page, 'Persistent Test Tracking'));
$assert('Page includes persistent test history targets', str_contains($page, 'data-embed-test-summary') && str_contains($page, 'data-embed-test-history'));

$assert('JS uses placement tests API', str_contains($js, 'campaign-embed-placement-tests.php'));
$assert('JS renders persistent test history', str_contains($js, 'function renderPlacementTests'));
$assert('JS loads persistent tests', str_contains($js, 'function loadPlacementTests'));
$assert('JS starts persistent tests', str_contains($js, 'function startPersistentTest'));
$assert('JS updates persistent tests', str_contains($js, 'function updatePersistentTest'));
$assert('JS sends start payload with campaign/domain/page/source/mode', str_contains($js, 'campaign_ref') && str_contains($js, 'origin_host') && str_contains($js, 'page_url') && str_contains($js, 'source') && str_contains($js, 'embed_mode'));
$assert('JS handles persistent action buttons', str_contains($js, 'data-embed-test-action') && str_contains($js, 'data-embed-action-start-test'));
$assert('JS keeps v4.6 action center rendering', str_contains($js, 'renderActionCenter'));

$assert('CSS styles persistent test panel', str_contains($css, '.mg-embed-test-history-panel'));
$assert('CSS styles persistent test summary', str_contains($css, '.mg-embed-test-summary'));
$assert('CSS styles persistent test cards', str_contains($css, '.mg-embed-test-card'));
$assert('CSS styles persistent test statuses', str_contains($css, '.mg-test-status'));

foreach ($checks as [$label, $passed]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}

if ($failures) {
    echo PHP_EOL . 'Campaign Embed Test Tracking v4.7 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Campaign Embed Test Tracking v4.7 validation passed.' . PHP_EOL;
