<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/db.php';
$sql = file_get_contents(dirname(__DIR__) . '/database/stage_9c_microgift_lifecycle.sql');
if (!is_string($sql)) { exit(1); }
mg_db()->exec($sql);
echo "Stage 9C Microgift lifecycle schema applied.\n";
