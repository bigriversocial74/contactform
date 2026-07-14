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

$profileCss = $read('assets/css/public-profile-polish.css');
$crmPage = $read('merchant-crm.php');
$crmCss = $read('assets/css/merchant-crm-kpi-cleanup.css');

$checks = [
    'public profile hides detached delete-cover shortcut' =>
        str_contains($profileCss, '.mg-profile-owner-tools a[href="/account.php"]:last-of-type')
        && str_contains($profileCss, 'display:none!important'),
    'Merchant CRM loads KPI cleanup stylesheet' =>
        str_contains($crmPage, '/assets/css/merchant-crm-kpi-cleanup.css?v=1.0.0'),
    'desktop KPI decorative icon pills are removed' =>
        str_contains($crmCss, '.mg-crm-desktop-kpis .mg-crm-kpi-icon')
        && str_contains($crmCss, 'display: none !important'),
    'desktop KPI content expands into reclaimed space' =>
        str_contains($crmCss, '.mg-crm-desktop-kpis .mg-crm-kpi-copy')
        && str_contains($crmCss, 'width: 100%'),
    'mobile CRM remains outside cleanup scope' =>
        str_contains($crmCss, '@media (min-width: 981px)'),
];

foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) {
        $failures[] = $label;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Profile cover / CRM KPI cleanup validation failed:\n- " . implode("\n- ", array_unique($failures)) . PHP_EOL);
    exit(1);
}

echo 'Profile cover / CRM KPI cleanup contract passed.' . PHP_EOL;
