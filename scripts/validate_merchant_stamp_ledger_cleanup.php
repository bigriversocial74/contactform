<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes = 0;

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . ltrim($path, '/');
    if (!is_file($full)) {
        throw new RuntimeException('Missing required file: ' . $path);
    }
    $content = file_get_contents($full);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to read required file: ' . $path);
    }
    return $content;
};

$expect = static function (bool $condition, string $label) use (&$failures, &$passes): void {
    if ($condition) {
        $passes++;
        echo "PASS: {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL: {$label}\n";
};

try {
    $page = $read('merchant-stamps.php');
    $view = $read('includes/merchant-stamps-view.php');
    $css = $read('assets/css/merchant-stamps-ledger.css');
    $js = $read('assets/js/merchant-stamps.js');

    $expect(
        str_contains($page, 'merchant-stamps-ledger.css?v=20260711-ledger-cleanup'),
        'Stamp ledger page cache-busts the cleaned layout stylesheet'
    );

    foreach ([
        'data-stamp-tab="ledger"',
        'data-stamp-tab="history"',
        'data-stamp-tab="adjustments"',
        'data-stamp-tab="tools"',
    ] as $marker) {
        $expect(str_contains($view, $marker), 'Consolidated stamp tab exists: ' . $marker);
    }

    foreach (['>Purchases</a>', '>Sends</a>', '>Failed Sends</a>', '>Export</a>', 'Export Ledger'] as $removedLabel) {
        $expect(!str_contains($view, $removedLabel), 'Removed legacy stamp navigation label: ' . $removedLabel);
    }

    $expect(
        str_contains($view, 'data-stamp-open-buy>Buy Stamps</a>')
        && str_contains($view, 'data-stamp-buy-panel hidden'),
        'Buy Stamps is available as a button-backed section instead of a top-level tab'
    );

    $expect(
        !str_contains($view, 'mg-stamp-ledger-side')
        && str_contains($view, 'mg-stamp-ledger-main-panel')
        && str_contains($view, 'mg-stamp-tools-grid'),
        'Right sidebar is removed and its content is consolidated into the full-width Tools section'
    );

    $expect(
        str_contains($view, 'data-stamp-tab-panel="history"')
        && str_contains($view, 'data-stamp-tab-panel="adjustments"')
        && str_contains($view, 'data-stamp-tab-panel="tools"'),
        'History, adjustments, and tools content each have dedicated tab panels'
    );

    $expect(
        str_contains($css, '.mg-stamp-tab-panels')
        && str_contains($css, 'width: 100%')
        && str_contains($css, '.mg-stamp-tools-grid')
        && !str_contains($css, 'grid-template-columns:minmax(0,1.55fr)'),
        'Stamp workspace uses full-width panels instead of the previous sidebar grid'
    );

    $expect(
        str_contains($js, 'function activateTab')
        && str_contains($js, 'function openBuyPanel')
        && str_contains($js, '[data-stamp-tab-open]')
        && str_contains($js, '[data-stamp-close-buy]'),
        'Stamp controller manages tabs and Buy Stamps panel state'
    );

    $expect(
        str_contains($js, '/api/stamps/ledger.php')
        && str_contains($js, '/api/stamps/bundles.php')
        && str_contains($js, '/api/stamps/purchases.php'),
        'Existing live ledger, bundle, and receipt endpoints remain connected'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Merchant Stamp Ledger cleanup validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Merchant Stamp Ledger cleanup validation passed: {$passes} checks.\n";
