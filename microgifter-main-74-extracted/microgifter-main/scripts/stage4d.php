<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/db.php';
$contents = file_get_contents(dirname(__DIR__) . '/database/stage_4d_digital_fulfillment_media.sql');
if (!is_string($contents) || trim($contents) === '') {
    throw new RuntimeException('Migration not found.');
}
mg_db()->exec($contents);
echo "Stage 4D schema applied.\n";
