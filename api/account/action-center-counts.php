<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';

mg_require_method('GET');
$user=mg_require_api_user();
mg_ok(['counts'=>mg_action_center_counts(mg_db(),(int)$user['id'])]);
