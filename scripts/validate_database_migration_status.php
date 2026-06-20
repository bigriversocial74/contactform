<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/includes/migrations.php';

try {
    $pdo = mg_db();
    $status = mg_migration_status($pdo);
    fwrite(STDOUT, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    if (!$status['ready']) {
        fwrite(STDERR, 'Database migration status is not ready.' . PHP_EOL);
        exit(1);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
