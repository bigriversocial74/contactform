<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}
require_once dirname(__DIR__) . '/api/db.php';
$sql = file_get_contents(dirname(__DIR__) . '/database/stage_5h_notifications_messaging_alerts.sql');
if (!is_string($sql) || trim($sql) === '') {
    fwrite(STDERR, "Stage 5H migration not found.\n");
    exit(1);
}
try {
    mg_db()->exec($sql);
    echo "Stage 5H notifications and messaging schema applied.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
