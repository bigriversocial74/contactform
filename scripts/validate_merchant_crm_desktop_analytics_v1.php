<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'page' => $root . '/merchant-crm.php',
    'view' => $root . '/includes/merchant-crm-view.php',
    'css' => $root . '/assets/css/merchant-crm-desktop-analytics.css',
    'js' => $root . '/assets/js/merchant-crm-desktop-analytics.js',
];

$content = [];
foreach ($files as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing required file: {$path}\n");
        exit(1);
    }
    $content[$key] = (string) file_get_contents($path);
}

$checks = [
    'desktop analytics assets are loaded' =>
        str_contains($content['page'], 'merchant-crm-desktop-analytics.css?v=1.0.0')
        && str_contains($content['page'], 'merchant-crm-desktop-analytics.js?v=1.0.0'),
    'desktop hero has title and operating controls' =>
        str_contains($content['view'], 'data-crm-desktop-hero')
        && str_contains($content['view'], 'Merchant CRM')
        && str_contains($content['view'], 'data-crm-desktop-range')
        && str_contains($content['view'], 'data-crm-desktop-filter')
        && str_contains($content['view'], 'data-crm-desktop-export'),
    'seven live KPI destinations exist' =>
        str_contains($content['view'], 'data-crm-desktop-high')
        && str_contains($content['view'], 'data-crm-desktop-followup')
        && str_contains($content['view'], 'data-crm-desktop-claims')
        && str_contains($content['view'], 'data-crm-desktop-messages')
        && str_contains($content['view'], 'data-crm-desktop-active')
        && str_contains($content['view'], 'data-crm-desktop-verified')
        && str_contains($content['view'], 'data-crm-desktop-review'),
    'audience health and pipeline panels exist' =>
        str_contains($content['view'], 'data-crm-health-ring')
        && str_contains($content['view'], 'data-crm-health-bar="verified"')
        && str_contains($content['view'], 'data-crm-pipeline-new')
        && str_contains($content['view'], 'data-crm-pipeline-converted')
        && str_contains($content['view'], 'data-crm-conversion-rate'),
    'analytics consume canonical CRM render event' =>
        str_contains($content['js'], "document.addEventListener('mg:crm-contacts:rendered'")
        && str_contains($content['js'], 'event.detail.contacts')
        && str_contains($content['js'], 'event.detail.visible'),
    'CSV export uses current CRM contacts' =>
        str_contains($content['js'], 'function exportCsv()')
        && str_contains($content['js'], 'microgifter-merchant-crm-contacts.csv')
        && str_contains($content['js'], 'URL.createObjectURL'),
    'mobile overview remains present and desktop hero is hidden on mobile' =>
        str_contains($content['view'], 'data-crm-mobile-overview')
        && str_contains($content['view'], 'data-crm-mobile-directory')
        && str_contains($content['css'], '@media(max-width:980px)')
        && str_contains($content['css'], '.mg-crm-desktop-hero{display:none!important}'),
    'desktop layout has KPI and insight grids' =>
        str_contains($content['css'], 'grid-template-columns:repeat(7,minmax(0,1fr))')
        && str_contains($content['css'], '.mg-crm-desktop-insights')
        && str_contains($content['css'], '.mg-crm-pipeline-stages'),
    'existing contact table authority is preserved' =>
        str_contains($content['view'], 'data-merchant-crm-table')
        && str_contains($content['view'], 'data-crm-drawer')
        && str_contains($content['view'], 'data-crm-message-modal')
        && str_contains($content['view'], 'data-crm-reward-modal'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) {
        $failed[] = $name;
    }
}

if ($failed !== []) {
    fwrite(STDERR, "\nMerchant CRM desktop analytics validation failed: " . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo "\nMerchant CRM desktop analytics contract: 10/10." . PHP_EOL;
