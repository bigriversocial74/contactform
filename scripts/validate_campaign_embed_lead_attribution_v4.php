<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$failures = [];

function mg_v4_file(string $path): string
{
    global $root;
    return $root . '/' . ltrim($path, '/');
}

function mg_v4_text(string $path): string
{
    $file = mg_v4_file($path);
    return is_file($file) ? (string)file_get_contents($file) : '';
}

function mg_v4_check(string $label, bool $pass): void
{
    global $checks, $failures;
    $checks[] = [$label, $pass];
    if (!$pass) $failures[] = $label;
}

$helper = 'api/public/campaigns/_embed_attribution.php';
$helperText = mg_v4_text($helper);
mg_v4_check('embed attribution helper exists', $helperText !== '');
mg_v4_check('helper extracts embed attribution', str_contains($helperText, 'mg_public_campaign_embed_attribution') && str_contains($helperText, 'origin_host') && str_contains($helperText, 'page_url'));
mg_v4_check('helper merges metadata', str_contains($helperText, 'mg_public_campaign_metadata_with_embed'));

foreach (['signup.php','engage.php','contest-entry.php','qr-pickup.php'] as $endpoint) {
    $path = 'api/public/campaigns/' . $endpoint;
    $text = mg_v4_text($path);
    mg_v4_check($endpoint . ' requires attribution helper', str_contains($text, '_embed_attribution.php'));
    mg_v4_check($endpoint . ' extracts embed attribution', str_contains($text, 'mg_public_campaign_embed_attribution($input)'));
    mg_v4_check($endpoint . ' stores attribution in metadata', str_contains($text, 'mg_public_campaign_metadata_with_embed') || str_contains($text, "'embed_attribution' => $"));
    mg_v4_check($endpoint . ' passes attribution to CRM/event contexts', str_contains($text, 'mg_merchant_crm_record_event') && str_contains($text, 'embed_attribution'));
    mg_v4_check($endpoint . ' returns embed_attribution when useful', str_contains($text, "'embed_attribution'"));
}

$routerText = mg_v4_text('api/public/campaigns/embed-submit.php');
mg_v4_check('embed-submit routes submissions to public endpoints', str_contains($routerText, 'signup.php') && str_contains($routerText, 'contest-entry.php') && str_contains($routerText, 'qr-pickup.php') && str_contains($routerText, 'engage.php'));

$widgetText = mg_v4_text('assets/js/microgifter-campaign-embed.js');
foreach (['embed_source','embed_origin','page_url','embed_mode'] as $field) {
    mg_v4_check('widget sends ' . $field, str_contains($widgetText, $field));
}

$apiText = mg_v4_text('api/merchant/campaign-embed-leads.php');
mg_v4_check('merchant leads API exists', $apiText !== '');
mg_v4_check('merchant leads API uses existing CRM/campaign tables', str_contains($apiText, 'merchant_crm_contact_events') && str_contains($apiText, 'campaign_contacts') && str_contains($apiText, 'campaigns'));
mg_v4_check('merchant leads API returns totals and rows', str_contains($apiText, "'totals'") && str_contains($apiText, "'rows'"));
mg_v4_check('merchant leads API has no SQL migration requirement', str_contains($apiText, "'sql_required' => null"));

$pageText = mg_v4_text('merchant-campaign-embed-leads.php');
mg_v4_check('merchant leads page exists', $pageText !== '');
mg_v4_check('merchant leads page uses app shell/sidebar', str_contains($pageText, 'mg-app-shell') && str_contains($pageText, 'app-sidebar.php'));
mg_v4_check('merchant leads page links campaigns, QA/analytics/CRM surfaces', str_contains($pageText, 'merchant-campaigns.php') && str_contains($pageText, 'merchant-campaign-embed-analytics.php'));

$navText = mg_v4_text('includes/merchant-workspace.php');
mg_v4_check('merchant sidebar includes Embed Leads', str_contains($navText, 'campaign_embed_leads') && str_contains($navText, 'Embed Leads'));

$sqlFiles = glob($root . '/database/*campaign*embed*lead*attribution*v4*.sql') ?: [];
mg_v4_check('no new campaign embed lead attribution SQL migration', count($sqlFiles) === 0);

foreach ($checks as [$label, $pass]) {
    echo ($pass ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
}

if ($failures) {
    fwrite(STDERR, PHP_EOL . 'Campaign Embed Lead Attribution v4 validation failed:' . PHP_EOL . '- ' . implode(PHP_EOL . '- ', $failures) . PHP_EOL);
    exit(1);
}

echo PHP_EOL . 'Campaign Embed Lead Attribution v4 validation passed.' . PHP_EOL;
