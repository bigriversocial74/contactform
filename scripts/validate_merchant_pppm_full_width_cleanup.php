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
    $page = $read('merchant-pppm.php');
    $view = $read('includes/merchant-pppm-view.php');
    $layout = $read('assets/css/pppm-ops-extra.css');

    $expect(
        str_contains($page, '$merchantView=\'pppm\';')
        && str_contains($page, "require __DIR__.'/includes/merchant-workspace.php';"),
        'Merchant PPPM page continues to use the shared merchant workspace'
    );

    $expect(
        !str_contains($view, 'Gift operations center')
        && !str_contains($view, 'PPPM Lifecycle')
        && !str_contains($view, 'mg-pppm-hero'),
        'PPPM lifecycle hero is removed'
    );

    $expect(
        !str_contains($view, 'Review Gift Lifecycle')
        && str_contains($view, '<div class="mg-pppm-commandbar">')
        && str_contains($view, '<nav class="mg-pppm-tabs"'),
        'Review Gift Lifecycle button is removed while the lifecycle tabs remain'
    );

    $expect(
        !str_contains($view, 'mg-pppm-side')
        && !str_contains($view, 'Lifecycle Readiness')
        && !str_contains($view, 'Quick actions'),
        'Right sidebar content is removed'
    );

    $expect(
        str_contains($view, 'id="pppm-overview"')
        && str_contains($view, 'id="pppm-items-panel"')
        && str_contains($view, 'data-pppm-list')
        && str_contains($view, 'data-order-list'),
        'PPPM item and order activity remain intact'
    );

    $expect(
        preg_match('/\.mg-pppm-layout\s*\{[^}]*display:block;[^}]*width:100%;/s', $layout) === 1,
        'PPPM activity layout is full width'
    );

    $expect(
        preg_match('/\.mg-pppm-panel\s*\{[^}]*width:100%;[^}]*max-width:100%;/s', $layout) === 1,
        'Main PPPM panel fills the available workspace'
    );

    $expect(
        str_contains($view, '/assets/css/pppm-ops-extra.css?v=2.0.0'),
        'PPPM layout stylesheet cache version is updated'
    );
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
    echo 'FAIL: ' . $error->getMessage() . "\n";
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Merchant PPPM full-width validation failed: %d failure(s), %d pass(es).\n", count($failures), $passes));
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Merchant PPPM full-width validation passed: {$passes} checks.\n";
