<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$targets = [
    'page' => $root . '/merchant-crm.php',
    'fix' => $root . '/assets/css/merchant-crm-mobile-card-regression-fix.css',
    'view' => $root . '/includes/merchant-crm-view.php',
];

$files = [];
foreach ($targets as $key => $path) {
    $content = file_get_contents($path);
    if (!is_string($content) || trim($content) === '') {
        fwrite(STDERR, "Missing validation target: {$path}\n");
        exit(1);
    }
    $files[$key] = $content;
}

$checks = [
    'regression stylesheet loads after mobile dashboard layers' =>
        strpos($files['page'], 'merchant-crm-mobile-dashboard-contract.css?v=1.0.0')
        < strpos($files['page'], 'merchant-crm-mobile-card-regression-fix.css?v=1.0.0'),
    'nested merchant shell removes duplicate mobile top offset' =>
        str_contains($files['fix'], '.mg-app-shell.mg-merchant-app')
        && str_contains($files['fix'], 'padding-top:0!important'),
    'mobile merchant workspace starts close to the header' =>
        str_contains($files['fix'], '.mg-merchant-main')
        && str_contains($files['fix'], 'padding:10px 10px 20px!important'),
    'contact card uses a non-overlapping positioned layout' =>
        str_contains($files['fix'], '.mg-crm-contact-row')
        && str_contains($files['fix'], 'display:block!important')
        && str_contains($files['fix'], 'position:relative!important'),
    'identity and campaign copy reserve room for counters' =>
        str_contains($files['fix'], '.mg-crm-campaign-cell')
        && str_contains($files['fix'], 'margin-right:122px!important'),
    'profile avatar remains removed on mobile' =>
        str_contains($files['fix'], '.mg-crm-contact-avatar')
        && str_contains($files['fix'], 'display:none!important'),
    'engagement counters float in the upper right' =>
        str_contains($files['fix'], '.mg-crm-engagement-cell')
        && str_contains($files['fix'], 'position:absolute!important')
        && str_contains($files['fix'], 'top:14px!important')
        && str_contains($files['fix'], 'right:14px!important'),
    'engagement counters use a compact two by two grid' =>
        str_contains($files['fix'], 'grid-template-columns:repeat(2,50px)!important')
        && str_contains($files['fix'], 'min-height:38px!important')
        && str_contains($files['fix'], 'span:nth-child(n+5)'),
    'action controls occupy one four-column footer row' =>
        str_contains($files['fix'], '.mg-crm-actions-cell')
        && str_contains($files['fix'], 'border-top:1px solid #edf2f7!important')
        && str_contains($files['fix'], 'grid-template-columns:repeat(4,minmax(0,1fr))!important'),
    'action labels cannot overflow the mobile footer' =>
        str_contains($files['fix'], '.mg-crm-icon-btn span')
        && str_contains($files['fix'], 'display:none!important'),
    'existing CRM accordion remains present' =>
        str_contains($files['view'], 'data-crm-mobile-overview-toggle')
        && str_contains($files['view'], 'data-crm-mobile-search'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Merchant CRM mobile card regression validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Merchant CRM mobile card regression contract: ' . count($checks) . '/' . count($checks) . " checks passed.\n";
