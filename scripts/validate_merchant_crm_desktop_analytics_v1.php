<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        throw new RuntimeException('Missing required file: ' . $relative);
    }
    $content = file_get_contents($path);
    if (!is_string($content) || trim($content) === '') {
        throw new RuntimeException('Empty required file: ' . $relative);
    }
    return $content;
};

try {
    $page = $read('merchant-crm.php');
    $view = $read('includes/merchant-crm-view.php');
    $css = $read('assets/css/merchant-crm-desktop-analytics.css');
    $layoutCss = $read('assets/css/merchant-crm-desktop-layout-fix.css');
    $js = $read('assets/js/merchant-crm-desktop-analytics.js');
    $searchJs = $read('assets/js/merchant-crm-desktop-search.js');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

$checks = [
    'desktop analytics and layout assets load from Merchant CRM' =>
        str_contains($page, 'merchant-crm-desktop-analytics.css')
        && str_contains($page, 'merchant-crm-desktop-analytics.js')
        && str_contains($page, 'merchant-crm-desktop-layout-fix.css')
        && str_contains($page, 'merchant-crm-desktop-search.js'),
    'desktop hero contains KPI and insight surfaces' =>
        str_contains($view, 'data-crm-desktop-hero')
        && str_contains($view, 'mg-crm-desktop-kpis')
        && str_contains($view, 'mg-crm-desktop-insights')
        && str_contains($view, 'data-crm-health-ring')
        && str_contains($view, 'data-crm-pipeline-new'),
    'reporting controls use a stable window and button structure' =>
        str_contains($view, 'mg-crm-desktop-window')
        && str_contains($view, 'data-crm-desktop-range')
        && str_contains($view, 'data-crm-desktop-filter')
        && str_contains($view, 'data-crm-desktop-export'),
    'KPI charts are wrapped and contained' =>
        str_contains($view, 'mg-crm-kpi-chart')
        && str_contains($layoutCss, '.mg-crm-kpi-chart')
        && str_contains($layoutCss, 'overflow:hidden')
        && str_contains($layoutCss, '.mg-crm-kpi-spark'),
    'desktop search replaces the legacy desktop stat row' =>
        str_contains($view, 'data-crm-desktop-directory')
        && str_contains($view, 'data-crm-desktop-search')
        && str_contains($searchJs, "querySelectorAll('.mg-crm-contact-row')")
        && str_contains($searchJs, 'mg:crm-contacts:rendered')
        && str_contains($layoutCss, '.mg-crm-contact-stat-strip')
        && str_contains($layoutCss, 'display:none!important'),
    'analytics consume canonical CRM contacts and export current rows' =>
        str_contains($js, 'mg:crm-contacts:rendered')
        && str_contains($js, 'latestContacts')
        && str_contains($js, 'latestVisible')
        && str_contains($js, 'microgifter-merchant-crm-contacts.csv'),
    'desktop styling keeps analytics responsive and preserves mobile boundary' =>
        str_contains($css, '.mg-crm-desktop-kpis')
        && str_contains($layoutCss, 'grid-template-columns:repeat(4')
        && str_contains($layoutCss, '@media (min-width:1450px)')
        && str_contains($layoutCss, '@media (max-width:980px)'),
    'existing mobile and contact operations remain present' =>
        str_contains($view, 'data-crm-mobile-overview')
        && str_contains($view, 'data-merchant-crm-table')
        && str_contains($view, 'data-crm-drawer')
        && str_contains($view, 'data-crm-message-modal')
        && str_contains($view, 'data-crm-reward-modal'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) {
        $failed[] = $name;
    }
}

if ($failed !== []) {
    fwrite(STDERR, 'Merchant CRM desktop analytics validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Merchant CRM desktop analytics contract: 10/10.' . PHP_EOL;
