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
    $page = $read('stamp-card.php');
    $css = $read('assets/css/stamp-card-public-layout-fix.css');
    $js = $read('assets/js/stamp-card-public-layout-fix.js');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

$checks = [
    'stamp page loads scoped layout assets' =>
        str_contains($page, 'stamp-card-public-layout-fix.css')
        && str_contains($page, 'stamp-card-public-layout-fix.js'),
    'desktop sidebar alignment measures the live stamp player' =>
        str_contains($js, ".mg-rl-stamp .mg-rl-player")
        && str_contains($js, ".mg-rl-stamp .mg-rl-join-desktop")
        && str_contains($js, 'playerTop - wrapTop')
        && str_contains($js, "sidebar.style.marginTop = offset + 'px'"),
    'desktop alignment responds to layout changes' =>
        str_contains($js, "matchMedia('(min-width: 1181px)')")
        && str_contains($js, 'ResizeObserver')
        && str_contains($js, "addEventListener('resize'"),
    'stamp sidebar overrides legacy open-details bottom alignment' =>
        str_contains($css, 'body[data-page-id="stamp-card"] .mg-rl-wrap:has(')
        && str_contains($css, 'align-self: start !important')
        && str_contains($css, 'bottom: auto !important'),
    'authenticated stamp footer receives dark contrast treatment' =>
        str_contains($css, 'body[data-page-id="stamp-card"] .mg-site-footer.mg-universal-footer')
        && str_contains($css, 'linear-gradient(135deg, #091a31 0%, #102d4c 100%)')
        && str_contains($css, '.mg-footer-column a')
        && str_contains($css, 'color: #fff !important'),
    'mobile stamp page hides the public header only' =>
        str_contains($css, '@media (max-width: 720px)')
        && str_contains($css, 'body[data-page-id="stamp-card"] > .mg-site-header[data-public-header]')
        && str_contains($css, 'display: none !important')
        && str_contains($css, 'body[data-page-id="stamp-card"] > .mg-main'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) {
        $failed[] = $label;
    }
}

if ($failed !== []) {
    fwrite(STDERR, 'Stamp Card public layout validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Stamp Card public layout contract: 10/10.' . PHP_EOL;
