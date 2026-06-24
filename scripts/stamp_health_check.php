<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/stamps/_health.php';
$pdo = mg_db();
$result = mg_stamp_system_health($pdo);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(!empty($result['ok']) ? 0 : 1);
