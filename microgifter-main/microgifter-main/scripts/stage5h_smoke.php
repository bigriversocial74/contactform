<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}
$required = [
    'api/communications/dashboard.php',
    'api/communications/preferences.php',
    'api/communications/operational-status.php',
    'api/communications/thread-settings.php',
    'includes/communications-workspace.php',
    'assets/js/communications.js',
    'assets/css/communications.css',
];
foreach ($required as $file) {
    if (!is_file(dirname(__DIR__) . '/' . $file)) {
        fwrite(STDERR, "Missing {$file}\n");
        exit(1);
    }
}
echo "Stage 5H communications files present.\n";
