<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'store-history.php',
    'api/customer-store/history.php',
    'assets/js/store-history.js',
    'assets/css/store-history.css',
    'includes/account-sidebar.php',
];
$failures = [];
foreach ($required as $path) {
    if (!is_file($root . '/' . $path)) $failures[] = "Missing {$path}";
}
function marker(string $path, string $needle, array &$failures): void
{
    $source = file_get_contents(dirname(__DIR__) . '/' . $path);
    if (!is_string($source) || !str_contains($source, $needle)) $failures[] = "{$path} missing {$needle}";
}
marker('store-history.php', 'data-store-history', $failures);
marker('api/customer-store/history.php', 'mg_store_sessions', $failures);
marker('api/customer-store/history.php', 'mg_store_session_events', $failures);
marker('api/customer-store/history.php', 'mg_customer_store_history', $failures);
marker('assets/js/store-history.js', '/api/customer-store/history.php', $failures);
marker('includes/account-sidebar.php', '/store-history.php', $failures);
if ($failures) {
    fwrite(STDERR, "Customer Store History validation failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Customer Store History validation passed.\n";
