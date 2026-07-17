<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'template' => $root . '/includes/gift-action-center.php',
    'style' => $root . '/assets/css/gift-action-center-pagination.css',
    'runtime' => $root . '/assets/js/gift-action-center-runtime-v4.js',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$template = (string) file_get_contents($files['template']);
$style = (string) file_get_contents($files['style']);
$runtime = (string) file_get_contents($files['runtime']);

$checks = [
    'pagination shell' => str_contains($template, 'data-gift-feed-pagination'),
    'load more control' => str_contains($template, 'data-gift-load-more'),
    'end state' => str_contains($template, 'No more gifts to show.'),
    'one pagination runtime' => str_contains($template, 'gift-action-center-runtime-v4.js?v=4.0.0')
        && !str_contains($template, 'gift-action-center-pagination.js'),
    '15 item API page' => str_contains($runtime, "PAGE_SIZE=15"),
    'cursor state' => str_contains($runtime, "page:{has_more:false,next_cursor:null}"),
    'cursor request' => str_contains($runtime, "&cursor=")
        && str_contains($runtime, "encodeURIComponent(state.page.next_cursor)"),
    'load more appends' => str_contains($runtime, "loadMore.addEventListener('click',()=>load(false))"),
    'refresh resets' => str_contains($runtime, "refresh.addEventListener('click',()=>load(true))"),
    'API counts stay authoritative' => str_contains($runtime, 'setCounts(data.counts||state.counts)'),
    'desktop list uses page scroll' => str_contains($style, 'max-height:none!important')
        && str_contains($style, 'overflow:visible!important'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, "Gift Action Center pagination validation failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo 'Gift Action Center pagination validation passed: ' . count($checks) . '/' . count($checks) . ".\n";
