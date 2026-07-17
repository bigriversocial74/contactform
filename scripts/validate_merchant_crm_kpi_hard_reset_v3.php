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
$css = $read('assets/css/merchant-crm-kpi-authoritative-v1.css');

$checks = [
    'page loads the authoritative KPI stylesheet' =>
        str_contains($page, '/assets/css/merchant-crm-kpi-authoritative-v1.css?v=1.0.0'),
    'obsolete KPI hard reset runtime is no longer loaded' =>
        !str_contains($page, '/assets/js/merchant-crm-kpi-hard-reset.js')
        && !str_contains($page, '/assets/css/merchant-crm-kpi-hard-reset.css'),
    'desktop KPI cards use four contained internal rows' =>
        str_contains($css, 'grid-template-rows: minmax(28px, auto) 40px minmax(30px, auto) 30px !important'),
    'desktop KPI cards have an explicit stable height' =>
        str_contains($css, 'height: 154px !important')
        && str_contains($css, 'overflow: hidden !important'),
    'KPI values metadata and charts use separate rows' =>
        str_contains($css, 'grid-row: 2 !important')
        && str_contains($css, 'grid-row: 3 !important')
        && str_contains($css, 'grid-row: 4 !important'),
    'sparklines remain width-contained' =>
        str_contains($css, 'max-width: 100% !important')
        && str_contains($css, 'height: 30px !important'),
    'legacy decorative KPI icons are hidden without DOM mutation' =>
        str_contains($css, '.mg-crm-kpi-icon')
        && str_contains($css, 'display: none !important'),
    'mobile CRM remains outside authoritative KPI scope' =>
        str_contains($css, '@media (min-width: 981px)')
        && !str_contains($css, '@media (max-width: 980px)'),
];

foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) {
        $failures[] = $label;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Merchant CRM KPI compatibility validation failed:\n- " . implode("\n- ", array_unique($failures)) . PHP_EOL);
    exit(1);
}

echo 'Merchant CRM KPI compatibility contract passed.' . PHP_EOL;
