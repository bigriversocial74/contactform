<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
function must_contain(string $path, string $needle, array &$failures): void
{
    $source = file_get_contents(dirname(__DIR__) . '/' . $path);
    if (!is_string($source) || !str_contains($source, $needle)) $failures[] = $path . ' missing ' . $needle;
}
function must_not_contain(string $path, string $needle, array &$failures): void
{
    $source = file_get_contents(dirname(__DIR__) . '/' . $path);
    if (is_string($source) && str_contains($source, $needle)) $failures[] = $path . ' still contains ' . $needle;
}
must_contain('merchant-canvas.php', 'mg-canvas-grid-full', $failures);
must_contain('assets/css/merchant-canvas.css', '.mg-store-canvas .mg-account-sidebar', $failures);
must_contain('assets/css/merchant-canvas.css', 'grid-template-columns:minmax(0,1fr)', $failures);
must_contain('assets/css/merchant-canvas.css', '.mg-canvas-side-panel{display:none}', $failures);
must_not_contain('merchant-canvas.php', 'mg-canvas-kpis', $failures);
must_not_contain('merchant-canvas.php', 'mg-canvas-side-panel', $failures);
if ($failures) {
    fwrite(STDERR, "Store Canvas layout validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Store Canvas layout validation passed.\n";
