<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/db.php';
$sql = file_get_contents(dirname(__DIR__) . '/database/stage_3_pppm_delivery_assignment.sql');
if (!is_string($sql) || trim($sql) === '') {
    exit(1);
}
mg_db()->exec($sql);
echo "PPPM delivery migration complete.\n";
