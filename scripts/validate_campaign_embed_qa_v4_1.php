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

$leadsPage = $read('merchant-campaign-embed-leads.php');
$leadsJs = $read('assets/js/campaign-embed-leads.js');
$leadsCss = $read('assets/css/campaign-embed-leads.css');
$qaPage = $read('merchant-campaign-embed-qa.php');
$qaCss = $read('assets/css/campaign-embed-qa.css');

$assert('Embed Leads page exists', $leadsPage !== '');
$assert('Embed Leads page is labeled QA v4.1', str_contains($leadsPage, 'Campaign Embed QA v4.1'));
$assert('Embed Leads page links to Embed QA', str_contains($leadsPage, '/merchant-campaign-embed-qa.php'));
$assert('Embed Leads page links to Embed Analytics', str_contains($leadsPage, '/merchant-campaign-embed-analytics.php'));
$assert('Embed Leads page includes clear-filter control', str_contains($leadsPage, 'data-embed-leads-reset'));
$assert('Embed Leads page includes filter summary target', str_contains($leadsPage, 'data-embed-leads-filter-summary'));

$assert('Embed Leads JS supports resetFilters()', str_contains($leadsJs, 'function resetFilters()'));
$assert('Embed Leads JS renders filter summary', str_contains($leadsJs, 'function renderFilterSummary'));
$assert('Embed Leads JS preserves empty-state QA path', str_contains($leadsJs, '/merchant-campaign-embed-qa.php'));
$assert('Embed Leads JS links no-SQL wording to v4.1', str_contains($leadsJs, 'No new SQL is required by v4.1'));
$assert('Embed Leads CSS styles filter summary', str_contains($leadsCss, '.mg-embed-leads-filter-note'));
$assert('Embed Leads CSS includes small-screen polish', str_contains($leadsCss, '@media(max-width:640px)'));

$assert('Embed QA page exists', $qaPage !== '');
$assert('Embed QA page is labeled QA v4.1', str_contains($qaPage, 'Campaign Embed QA v4.1'));
$assert('Embed QA page says no new SQL import required', str_contains($qaPage, 'does not require a new SQL import'));
$assert('Embed QA page links to Embed Leads', str_contains($qaPage, '/merchant-campaign-embed-leads.php'));
$assert('Embed QA page includes lead attribution checklist', str_contains($qaPage, 'Lead attribution checklist'));
$assert('Embed QA page documents expected attribution fields', str_contains($qaPage, 'origin_host') && str_contains($qaPage, 'page_url') && str_contains($qaPage, 'embed_mode'));
$assert('Embed QA CSS styles hero actions', str_contains($qaCss, '.mg-embed-qa-actions'));

foreach ($checks as [$label, $passed]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}

if ($failures) {
    echo PHP_EOL . 'Campaign Embed QA v4.1 validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Campaign Embed QA v4.1 validation passed.' . PHP_EOL;
