<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/admin-screen-recordings.php';

mg_require_method('POST');
$user = mg_screen_recordings_require_api(true);
$input = mg_input();
mg_require_csrf_for_write($input);
$pdo = mg_db();
mg_screen_recordings_require_schema($pdo);
if (function_exists('mg_rate_limit')) mg_rate_limit('admin.screen_recordings.delete', 'user:' . (int)$user['id'], 30, 300);

$recordingId = max(0, (int)($input['recording_id'] ?? $input['id'] ?? 0));
if ($recordingId < 1) mg_fail('Recording id is required.', 422);
mg_screen_recordings_fetch($pdo, $recordingId);
mg_screen_recordings_soft_delete($pdo, $recordingId);
mg_audit('admin_screen_recording.archive', 'admin_screen_recording', ['recording_id' => $recordingId], (int)$user['id']);
mg_ok(['recording_id' => $recordingId], 'Recording archived.');
