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
    $js = $read('assets/js/merchant-crm-desktop-analytics.js');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

$checks = [
    'desktop analytics assets load from Merchant CRM' =>
        str_contains($page, 'merchant-crm-desktop-analytics.css')
        && str_contains($page, 'merchant-crm-desktop-analytics.js'),
    'desktop hero contains KPI and insight surfaces' =>
        str_contains($view, 'data-crm-desktop-hero')
        && str_contains($view, 'mg-crm-desktop-kpis')
        && str_contains($view, 'mg-crm-desktop-insights')
        && str_contains($view, 'data-crm-health-ring')
        && str_contains($view, 'data-crm-pipeline-new'),
    'analytics consume canonical CRM contacts and export current rows' =>
        str_contains($js, 'mg:crm-contacts:rendered')
        && str_contains($js, 'latestContacts')
        && str_contains($js, 'latestVisible')
        && str_contains($js, 'microgifter-merchant-crm-contacts.csv'),
    'desktop styling includes KPI, health, pipeline, and mobile boundary' =>
        str_contains($css, '.mg-crm-desktop-kpis')
        && str_contains($css, '.mg-crm-health-ring')
        && str_contains($css, '.mg-crm-pipeline-stages')
        && str_contains($css, '@media(max-width:980px)'),
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
