<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path)
    ? (file_get_contents($root . '/' . $path) ?: '')
    : '';

$css = $read('assets/css/public-campaign-layout-cleanup-v1.css');
$footer = $read('includes/footer.php');
$foundation = $read('assets/css/campaign-landing-foundation.css');
$compact = $read('assets/css/public-campaign-compact-layout-v2.css');

$checks = [
    'shared cleanup stylesheet exists' => $css !== '',
    'campaign pages load cleanup stylesheet late' => str_contains($footer, "if ((\$page_section ?? '') === 'campaign')")
        && str_contains($footer, '/assets/css/public-campaign-layout-cleanup-v1.css?v=1.0.0'),
    'decorative campaign background image is removed' => str_contains($css, '.mg-rl-page .mg-rl-bg')
        && str_contains($css, 'display: none !important')
        && str_contains($css, 'background-image: none !important'),
    'campaign page keeps neutral surface' => str_contains($css, 'background: #f2f5f8 !important'),
    'desktop participation column aligns with campaign canvas' => str_contains($css, 'top: 72px !important')
        && str_contains($css, 'align-self: start !important')
        && str_contains($css, 'bottom: auto !important'),
    'legacy expanded-details offset is explicitly overridden' => str_contains($css, ':has(> .mg-rl-join-desktop .mg-campaign-user-details[open])'),
    'supporting cards receive real interior padding' => str_contains($css, 'padding: 22px 24px !important')
        && str_contains($css, 'height: auto !important')
        && str_contains($css, 'min-height: 210px'),
    'supporting card typography has vertical rhythm' => str_contains($css, 'margin: 0 0 10px !important')
        && str_contains($css, 'gap: 12px !important')
        && str_contains($css, 'line-height: 1.45 !important'),
    'tablet and mobile spacing remains responsive' => str_contains($css, '@media (max-width: 1180px)')
        && str_contains($css, '@media (max-width: 720px)')
        && str_contains($css, 'padding: 18px !important'),
    'existing campaign foundation and compact layout remain intact' => $foundation !== '' && $compact !== '',
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}

$score = round((count($checks) - count($failed)) / count($checks) * 100);
echo 'Public campaign layout cleanup score: ' . $score . '/100' . PHP_EOL;

if ($failed !== []) {
    fwrite(STDERR, 'Public campaign layout cleanup validation failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Public campaign layout cleanup contract passed at 100/100.\n";
