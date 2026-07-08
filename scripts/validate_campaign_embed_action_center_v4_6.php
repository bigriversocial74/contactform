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

$page = $read('merchant-campaign-embed-leads.php');
$js = $read('assets/js/campaign-embed-leads.js');
$css = $read('assets/css/campaign-embed-leads.css');
$sqlFiles = glob($root . '/database/*campaign*embed*action*center*v4*6*.sql') ?: [];

$assert('Embed Leads page exists', $page !== '');
$assert('Embed Leads page labels v4.6 Action Center', str_contains($page, 'Campaign Embed v4.6') && str_contains($page, 'Merchant Action Center'));
$assert('Embed Leads page includes action center panel', str_contains($page, 'mg-embed-action-center-panel'));
$assert('Embed Leads page includes primary action target', str_contains($page, 'data-embed-action-primary'));
$assert('Embed Leads page includes action status target', str_contains($page, 'data-embed-action-status'));
$assert('Embed Leads page includes campaign action target', str_contains($page, 'data-embed-action-campaigns'));
$assert('Embed Leads page includes follow-up shortcut target', str_contains($page, 'data-embed-action-followups'));

$assert('JS renders Action Center', str_contains($js, 'function renderActionCenter'));
$assert('JS renders copy filtered lead view action', str_contains($js, 'Copy filtered lead view'));
$assert('JS renders export placement report action', str_contains($js, 'Export placement report'));
$assert('JS renders campaign action cards', str_contains($js, 'mg-action-campaign-card'));
$assert('JS renders follow-up shortcuts', str_contains($js, 'mg-action-followup-card'));
$assert('JS supports copy-to-clipboard', str_contains($js, 'function copyText') && str_contains($js, 'navigator.clipboard'));
$assert('JS supports clipboard fallback', str_contains($js, 'function fallbackCopy'));
$assert('JS supports local test-start tracking', str_contains($js, 'function markTestStarted') && str_contains($js, 'localStorage'));
$assert('JS calls Action Center on load', str_contains($js, 'renderActionCenter(data)'));
$assert('JS handles action center copy buttons', str_contains($js, 'data-embed-action-copy-url'));
$assert('JS handles action center export buttons', str_contains($js, 'data-embed-action-export'));
$assert('JS handles mark test started buttons', str_contains($js, 'data-embed-action-mark-test'));
$assert('JS keeps v4.5 placement rendering', str_contains($js, 'renderPlacementIntelligence(data.placement_intelligence || {})'));

$assert('CSS styles action center panel', str_contains($css, '.mg-embed-action-center-panel'));
$assert('CSS styles action status messages', str_contains($css, '.mg-embed-action-status'));
$assert('CSS styles primary action buttons', str_contains($css, '.mg-embed-action-primary'));
$assert('CSS styles campaign action cards', str_contains($css, '.mg-action-campaign-card'));
$assert('CSS styles follow-up cards', str_contains($css, '.mg-action-followup-card'));
$assert('CSS styles action button rows', str_contains($css, '.mg-action-button-row'));
$assert('No v4.6 SQL migration added', count($sqlFiles) === 0);

foreach ($checks as [$label, $passed]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}

if ($failures) {
    echo PHP_EOL . 'Campaign Embed Action Center v4.6 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Campaign Embed Action Center v4.6 validation passed.' . PHP_EOL;
