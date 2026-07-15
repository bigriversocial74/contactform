<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'template' => $root . '/includes/gift-action-center.php',
    'style' => $root . '/assets/css/gift-action-center-pagination.css',
    'script' => $root . '/assets/js/gift-action-center-pagination.js',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$template = file_get_contents($files['template']);
$style = file_get_contents($files['style']);
$script = file_get_contents($files['script']);

$checks = [
    'pagination shell' => str_contains($template, 'data-gift-feed-pagination'),
    'load more control' => str_contains($template, 'data-gift-load-more'),
    'end state' => str_contains($template, 'No more gifts to show.'),
    'pagination assets' => str_contains($template, 'gift-action-center-pagination.css') && str_contains($template, 'gift-action-center-pagination.js'),
    '15 item batch' => str_contains($script, 'const batchSize = 15;'),
    'folder-specific progress' => str_contains($script, 'visibleByFolder'),
    'mutation refresh' => str_contains($script, 'MutationObserver'),
    'desktop list uses page scroll' => str_contains($style, 'max-height:none!important') && str_contains($style, 'overflow:visible!important'),
    'hidden gift rows stay hidden' => str_contains($style, '.mg-gift-list > [hidden]'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, "Gift Action Center pagination validation failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "Gift Action Center pagination validation passed.\n";
