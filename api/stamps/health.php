<?php
declare(strict_types=1);
require_once __DIR__ . '/_health.php';
$user = mg_require_api_user();
if (!mg_api_user_has_permission($user, 'admin.stamps.view') && !mg_api_user_has_permission($user, 'admin.stamps.manage') && !mg_api_user_has_permission($user, 'admin.commerce.view')) mg_fail('Permission denied.', 403);
mg_require_method('GET');
$pdo = mg_db();
mg_ok(mg_stamp_system_health($pdo));
