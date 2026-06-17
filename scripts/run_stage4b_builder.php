<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/db.php';
$sql = file_get_contents(dirname(__DIR__) . '/database/stage_4b_builder_persistence.sql');
if (!is_string($sql) || trim($sql) === '') {
    fwrite(STDERR, "Missing Stage 4B builder migration.\n");
    exit(1);
}

try {
    mg_db()->exec($sql);
    echo "Stage 4B builder migration complete.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
