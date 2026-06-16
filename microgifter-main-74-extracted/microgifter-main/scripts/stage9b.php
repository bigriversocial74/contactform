<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/db.php';

$file = 'stage_9b_microgift_engine.sql';
$sql = file_get_contents(dirname(__DIR__) . '/database/' . $file);
if (!is_string($sql) || trim($sql) === '') {
    fwrite(STDERR, "FAILED: Missing Stage 9B migration.\n");
    exit(1);
}

try {
    mg_db()->exec($sql);
    echo "Stage 9B Microgift Engine schema applied.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
