<?php
declare(strict_types=1);
require_once __DIR__ . '/_stamps.php';
$user = mg_require_api_user();
mg_require_method('GET');
mg_ok(['actions' => mg_stamp_action_rows(mg_db())]);
