#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/includes/delivery-operations.php';

$mode = 'observe';
$limit = null;
$errors = [];
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--observe') $mode = 'observe';
    elseif ($argument === '--process') $mode = 'process';
    elseif ($argument === '--help' || $argument === '-h') {
        echo "Microgifter delivery worker\n\n";
        echo "  --observe       Read-only queue and health summary (default)\n";
        echo "  --process       Process a bounded delivery batch\n";
        echo "  --limit=N       Lower the configured batch limit for this run\n";
        exit(0);
    } elseif (str_starts_with($argument, '--limit=')) {
        $raw = substr($argument, 8);
        if ($raw === '' || !ctype_digit($raw) || (int)$raw < 1) $errors[] = 'The --limit value must be a positive integer.';
        else $limit = (int)$raw;
    } else $errors[] = 'Unknown argument: ' . $argument;
}

if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(2);
}

$result = mg_delivery_run(mg_db(), $mode, $limit);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit((int)($result['exit_code'] ?? 1));
