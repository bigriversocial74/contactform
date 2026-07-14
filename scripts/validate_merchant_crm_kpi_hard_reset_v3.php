<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$read = static function (string $relative) use ($root, &$failures): string {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        $failures[] = 'Missing file: ' . $relative;
        return '';
    }
    $content = file_get_contents($path);
    if (!is_string($content)) {
        $failures[] = 'Unreadable file: ' . $relative;
        return '';
    }
    return $content;
};

$page = $read('merchant-crm.php');
$css = $read('assets/css/merchant-crm-kpi-hard-reset.css');
$js = $read('assets/js/merchant-crm-kpi-hard-reset.js');

$checks = [
    'page loads unique KPI hard reset stylesheet last' =>
        str_contains($page, '/assets/css/merchant-crm-kpi-hard-reset.css?v=3.0.0'),
    'page loads KPI icon removal runtime' =>
        str_contains($page, '/assets/js/merchant-crm-kpi-hard-reset.js?v=3.0.0'),
    'desktop KPI cards use four fixed internal rows' =>
        str_contains($css, 'grid-template-rows: 30px 38px 20px 28px !important'),
    'desktop KPI cards have explicit contained height' =>
        str_contains($css, 'height: 144px !important')
        && str_contains($css, 'overflow: hidden !important'),
    'KPI values, metadata, and charts use separate rows' =>
        str_contains($css, 'grid-row: 2 !important')
        && str_contains($css, 'grid-row: 3 !important')
        && str_contains($css, 'grid-row: 4 !important'),
    'sparklines are width-contained' =>
        str_contains($css, 'max-width: 100% !important')
        && str_contains($css, 'height: 28px !important'),
    'legacy decorative KPI icons are hidden and removed' =>
        str_contains($css, '.mg-crm-kpi-icon')
        && str_contains($css, 'display: none !important')
        && str_contains($js, "icon.remove();"),
    'mobile CRM remains outside hard reset scope' =>
        str_contains($css, '@media (min-width: 981px)'),
];

foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) {
        $failures[] = $label;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Merchant CRM KPI hard reset validation failed:\n- " . implode("\n- ", array_unique($failures)) . PHP_EOL);
    exit(1);
}

echo 'Merchant CRM KPI hard reset v3 contract passed.' . PHP_EOL;
