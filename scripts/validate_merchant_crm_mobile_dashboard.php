<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'page' => 'merchant-crm.php',
    'view' => 'includes/merchant-crm-view.php',
    'css' => 'assets/css/merchant-crm-mobile-dashboard.css',
    'contract_css' => 'assets/css/merchant-crm-mobile-dashboard-contract.css',
    'js' => 'assets/js/merchant-crm-mobile-dashboard.js',
    'directory_js' => 'assets/js/merchant-crm-directory.js',
    'identity_js' => 'assets/js/merchant-crm-identity-duplicates.js',
];

$files = [];
foreach ($paths as $key => $path) {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content) || trim($content) === '') {
        fwrite(STDERR, "Missing validation target: {$path}\n");
        exit(1);
    }
    $files[$key] = $content;
}

$overviewStart = strpos($files['view'], 'data-crm-mobile-overview-body');
$identityPanel = strpos($files['view'], 'data-crm-duplicates-panel');
$overviewEnd = strpos($files['view'], 'mg-crm-mobile-directory');
$searchPosition = strpos($files['view'], 'data-crm-mobile-search');
$tablePosition = strpos($files['view'], 'data-merchant-crm-table');
$mobileStatStart = strpos($files['view'], 'data-crm-contact-stat-strip');
$mobileStatEnd = $mobileStatStart === false ? false : strpos($files['view'], '</section>', $mobileStatStart);
$mobileStatMarkup = $mobileStatStart !== false && $mobileStatEnd !== false
    ? substr($files['view'], $mobileStatStart, $mobileStatEnd - $mobileStatStart)
    : '';

$checks = [
    'mobile dashboard styles are loaded last' => str_contains($files['page'], 'merchant-crm-mobile-dashboard.css?v=1.0.0')
        && str_contains($files['page'], 'merchant-crm-mobile-dashboard-contract.css?v=1.0.0'),
    'mobile dashboard and unified directory runtimes load after identity runtime' => strpos($files['page'], 'merchant-crm-identity-duplicates.js?v=1.1.0') < strpos($files['page'], 'merchant-crm-mobile-dashboard.js?v=1.1.0')
        && strpos($files['page'], 'merchant-crm-mobile-dashboard.js?v=1.1.0') < strpos($files['page'], 'merchant-crm-directory.js?v=1.0.0'),
    'mobile contract retains exactly five stat articles' => substr_count($mobileStatMarkup, '<article') === 5,
    'possible duplicates uses a mobile-only semantic tile' => str_contains($files['view'], 'mg-crm-mobile-duplicate-stat')
        && str_contains($files['view'], 'role="group"'),
    'Merchant CRM accordion is present and open by default' => str_contains($files['view'], 'data-crm-mobile-overview-toggle')
        && str_contains($files['view'], 'aria-expanded="true"')
        && str_contains($files['view'], 'data-crm-mobile-overview-body'),
    'review identities remains inside the accordion body' => $overviewStart !== false
        && $identityPanel !== false
        && $overviewEnd !== false
        && $overviewStart < $identityPanel
        && $identityPanel < $overviewEnd,
    'contact search appears before the contact table' => $searchPosition !== false
        && $tablePosition !== false
        && $searchPosition < $tablePosition,
    'mobile stat grid is two columns' => str_contains($files['css'], 'grid-template-columns:repeat(2,minmax(0,1fr))!important'),
    'mobile contact cards remove the avatar track' => str_contains($files['css'], '.mg-crm-contact-avatar')
        && str_contains($files['css'], 'display:none!important'),
    'mobile contact cards use compact named grid areas' => str_contains($files['css'], "'contact engagement'")
        && str_contains($files['css'], "'campaign engagement'")
        && str_contains($files['css'], "'actions actions'"),
    'semantic duplicate tile receives matching mobile presentation' => str_contains($files['contract_css'], '.mg-crm-mobile-duplicate-stat::before')
        && str_contains($files['contract_css'], '.mg-crm-mobile-duplicate-stat>strong'),
    'accordion runtime controls hidden state and aria state' => str_contains($files['js'], 'function setAccordion(open)')
        && str_contains($files['js'], "setAttribute('aria-expanded'"),
    'unified directory runtime filters rendered CRM rows for mobile and desktop' => str_contains($files['directory_js'], "querySelectorAll('.mg-crm-contact-row')")
        && str_contains($files['directory_js'], 'searchableText(row)')
        && str_contains($files['directory_js'], '[desktopInput, mobileInput]'),
    'search survives asynchronous CRM rerenders without a DOM-wide observer' => str_contains($files['directory_js'], 'mg:crm-contacts:rendered')
        && !str_contains($files['directory_js'], 'MutationObserver')
        && !str_contains($files['js'], 'MutationObserver'),
    'all duplicate count displays remain synchronized' => str_contains($files['identity_js'], "qsa('[data-crm-duplicate-count]')")
        && str_contains($files['identity_js'], 'setDuplicateCounts'),
];

$failures = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failures[] = $label;
}

if ($failures !== []) {
    fwrite(STDERR, 'Merchant CRM mobile dashboard validation failed: ' . implode(', ', $failures) . PHP_EOL);
    exit(1);
}

echo 'Merchant CRM mobile dashboard contract: ' . count($checks) . '/' . count($checks) . " checks passed.\n";
