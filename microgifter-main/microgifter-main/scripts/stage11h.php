<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/db.php';

$path = dirname(__DIR__) . '/database/stage_11h_backend_hardening.sql';
$sql = file_get_contents($path);
if (!is_string($sql) || trim($sql) === '') {
    throw new RuntimeException('Stage 11H migration is missing or empty.');
}

mg_db()->exec($sql);
fwrite(STDOUT, "Stage 11H backend hardening migration applied.\n");
