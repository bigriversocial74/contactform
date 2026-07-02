<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recordings.php';

mg_require_method('GET');
$user = mg_screen_recordings_require_api(false);
$pdo = mg_db();
mg_screen_recordings_require_schema($pdo);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.list', 'user:' . (int)$user['id'], 120, 60);

$query = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$items = mg_screen_recordings_list($pdo, $query, $status);
mg_ok([
    'items' => $items,
    'count' => count($items),
    'schema' => mg_screen_recordings_schema_ready($pdo),
], 'Screen recordings loaded.');
