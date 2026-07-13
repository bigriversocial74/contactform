<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'page' => $root . '/merchant-reviews.php',
    'view' => $root . '/includes/merchant-reviews-view.php',
    'css' => $root . '/assets/css/merchant-reviews-cleanup.css',
];

$content = [];
foreach ($files as $key => $path) {
    $value = file_get_contents($path);
    if (!is_string($value) || trim($value) === '') {
        fwrite(STDERR, "Missing layout cleanup target: {$path}\n");
        exit(1);
    }
    $content[$key] = $value;
}

$checks = [
    'cleanup stylesheet loads after shared reviews stylesheet' =>
        str_contains($content['page'], '/assets/css/merchant-reviews-cleanup.css?v=1.0.0')
        && strpos($content['page'], 'merchant-reviews-cleanup.css') > strpos($content['page'], 'reviews-case-studies-management.css'),
    'filter toolbar has dedicated review class' => str_contains($content['view'], 'rcs-review-toolbar'),
    'filter toolbar remains accessible search region' => str_contains($content['view'], 'role="search"') && str_contains($content['view'], 'Customer review filters'),
    'all four desktop controls remain present' =>
        str_contains($content['view'], 'data-review-search')
        && str_contains($content['view'], 'data-review-status')
        && str_contains($content['view'], 'data-review-rating')
        && str_contains($content['view'], 'data-review-refresh'),
    'desktop toolbar defines one four-column row' => str_contains($content['css'], 'grid-template-columns:minmax(320px,1fr) minmax(170px,.34fr) minmax(150px,.3fr) auto'),
    'filter controls share one consistent height' => str_contains($content['css'], 'height:42px'),
    'kpis are tightened into compact cards' =>
        str_contains($content['css'], 'min-height:82px')
        && str_contains($content['css'], 'padding:12px 14px')
        && str_contains($content['css'], 'font-size:23px'),
    'review cards use CRM-style record treatment' =>
        str_contains($content['css'], '.rcs-review::before')
        && str_contains($content['css'], '.rcs-actions')
        && str_contains($content['css'], 'background:var(--mr-soft)'),
    'review metadata uses compact chips' => str_contains($content['css'], '.rcs-meta span') && str_contains($content['css'], 'border-radius:999px'),
    'empty state is short and operational' =>
        str_contains($content['view'], 'Try a broader search, status, or rating.')
        && str_contains($content['css'], 'min-height:88px'),
    'hidden empty state cannot consume layout space' => str_contains($content['css'], '.rcs-empty[hidden]{display:none!important}'),
    'tablet layout reduces to two columns' => str_contains($content['css'], '@media(max-width:860px)') && str_contains($content['css'], 'grid-template-columns:1fr 1fr'),
    'mobile toolbar stacks safely' => str_contains($content['css'], '@media(max-width:620px)') && str_contains($content['css'], '.rcs-review-toolbar{grid-template-columns:1fr}'),
];

$failures = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failures[] = $label;
}

if ($failures !== []) {
    fwrite(STDERR, 'Merchant reviews layout cleanup failed: ' . implode('; ', $failures) . PHP_EOL);
    exit(1);
}

echo 'Merchant reviews layout cleanup: ' . count($checks) . '/' . count($checks) . " checks passed.\n";
