<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recordings.php';

mg_require_method('GET');
$user = mg_screen_recordings_require_api(false);
$pdo = mg_db();
mg_screen_recordings_require_schema($pdo);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.read', 'user:' . (int)$user['id'], 120, 60);

$recordingId = max(0, (int)($_GET['id'] ?? 0));
if ($recordingId < 1) mg_fail('Recording id is required.', 422);
$row = mg_screen_recordings_fetch($pdo, $recordingId);
mg_ok(['recording' => mg_screen_recordings_public_record($row)], 'Recording loaded.');
