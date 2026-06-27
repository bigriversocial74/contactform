<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = [
    'api/store/schema-status.php' => ['stage_20_agent_store_canvas','mg_store_sessions','missing_columns','session_read'],
    'merchant-canvas.php' => ['data-store-schema-panel'],
    'assets/js/merchant-canvas.js' => ['/api/store/schema-status.php','schemaReady'],
    'assets/css/merchant-canvas.css' => ['mg-canvas-schema-panel'],
];
foreach ($checks as $path => $needles) {
    $file = $root . '/' . $path;
    if (!is_file($file)) {
        $failures[] = 'Missing ' . $path;
        continue;
    }
    $source = file_get_contents($file);
    foreach ($needles as $needle) {
        if (!is_string($source) || !str_contains($source, $needle)) $failures[] = $path . ' missing ' . $needle;
    }
}
if ($failures) {
    fwrite(STDERR, "Store Canvas schema status validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Store Canvas schema status validation passed.\n";
