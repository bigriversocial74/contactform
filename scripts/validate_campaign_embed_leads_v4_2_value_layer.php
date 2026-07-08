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

$assert('Merchant leads API exists', $api !== '');
$assert('API supports CSV export', str_contains($api, 'mg_embed_leads_csv') && str_contains($api, "format === 'csv'"));
$assert('API returns campaign summaries', str_contains($api, 'campaign_summaries') && str_contains($api, 'campaignSummaryRows'));
$assert('API returns top pages', str_contains($api, 'top_pages') && str_contains($api, 'mg_embed_leads_page_path'));
$assert('API returns lead timeline detail data', str_contains($api, "'timeline'") && str_contains($api, "'value_summary'"));
$assert('API keeps no-SQL contract', str_contains($api, "'sql_required' => null"));

$assert('Embed Leads page labels v4.2 value layer', str_contains($page, 'Campaign Embed Leads v4.2') && str_contains($page, 'Merchant Value Layer'));
$assert('Embed Leads page includes CSV export action', str_contains($page, 'data-embed-leads-export'));
$assert('Embed Leads page includes campaign summary target', str_contains($page, 'data-embed-leads-campaign-summaries'));
$assert('Embed Leads page includes top pages target', str_contains($page, 'data-embed-leads-pages'));
$assert('Embed Leads page includes lead detail drawer', str_contains($page, 'data-embed-leads-drawer') && str_contains($page, 'data-embed-leads-drawer-content'));

$assert('JS renders campaign summaries', str_contains($js, 'function renderCampaignSummaries'));
$assert('JS renders top pages', str_contains($js, 'function renderPages'));
$assert('JS opens lead detail drawer', str_contains($js, 'function openDrawer') && str_contains($js, 'data-lead-detail'));
$assert('JS exports filtered CSV', str_contains($js, 'function exportCsv') && str_contains($js, "format: 'csv'"));
$assert('JS handles Escape close', str_contains($js, "event.key === 'Escape'"));

$assert('CSS styles value grid', str_contains($css, '.mg-embed-leads-value-grid'));
$assert('CSS styles drawer', str_contains($css, '.mg-embed-leads-drawer'));
$assert('CSS styles campaign/page cards', str_contains($css, '.mg-embed-leads-campaign-card') && str_contains($css, '.mg-embed-leads-page-card'));

foreach ($checks as [$label, $passed]) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
}

if ($failures) {
    echo PHP_EOL . 'Campaign Embed Leads v4.2 value-layer validation failed:' . PHP_EOL;
    foreach ($failures as $failure) echo ' - ' . $failure . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Campaign Embed Leads v4.2 value-layer validation passed.' . PHP_EOL;
